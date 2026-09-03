<?php

declare(strict_types=1);

namespace CalendarIntegration\Application\Command;

use CalendarIntegration\Application\CalendarProviderResolver;
use CalendarIntegration\Domain\CalendarLinkRepository;
use CalendarIntegration\Domain\CalendarAuthorizationRevoked;
use CalendarIntegration\Domain\CalendarEventMapping;
use CalendarIntegration\Domain\CalendarEventMappingRepository;
use CalendarIntegration\Domain\CalendarLink;
use CalendarIntegration\Domain\CalendarLinkStatus;
use DoseEvent\Domain\DoseEventRepository;
use Medication\Domain\MedicationRepository;
use Profile\Domain\ProfileRepository;
use Profile\Domain\ValueObject\Timezone;
use Schedule\Domain\ScheduleRepository;
use Shared\Domain\Result;
use Shared\Domain\Failure;
use Shared\Domain\TokenVault;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SyncCalendarHandler
{
    public function __construct(
        private readonly CalendarLinkRepository $calendarLinkRepository,
        private readonly CalendarEventMappingRepository $mappingRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly MedicationRepository $medicationRepository,
        private readonly ScheduleRepository $scheduleRepository,
        private readonly DoseEventRepository $doseEventRepository,
        private readonly CalendarProviderResolver $providerResolver,
        private readonly TokenVault $tokenVault
    ) {
    }

    /**
     * @return Result<array{eventsCreated: int, eventsUpdated: int, linksSynced: int, skipped: array<int, array{profileId: string, reason: string}>}>
     */
    public function __invoke(SyncCalendarCommand $command): Result
    {
        $accountId = new UserId($command->accountId);

        if ($command->profileId !== null) {
            $profiles = [];
            $profile = $this->profileRepository->findById(new ProfileId($command->profileId));
            if ($profile === null) {
                return Result::failure(Failure::notFound('Profile not found.'));
            }

            if (!$profile->accountId()->equals($accountId)) {
                return Result::failure(Failure::forbidden('You do not own this profile.'));
            }

            $profiles[] = $profile;
        } else {
            $profiles = $this->profileRepository->findByAccountId($accountId);
        }

        $now = new \DateTimeImmutable();
        $to = $now->modify('+14 days');
        $linkFailures = [];
        $skipped = [];
        $eventsCreated = 0;
        $eventsUpdated = 0;
        $linksSynced = 0;

        foreach ($profiles as $profile) {
            $links = $this->calendarLinkRepository->findByProfile($profile->id());
            if (count($links) === 0) {
                continue;
            }

            $medications = $this->medicationRepository->findByProfileId($profile->id());
            if (count($medications) === 0) {
                foreach ($links as $link) {
                    $skipped[] = ['profileId' => $profile->id()->value(), 'reason' => 'NO_MEDICATIONS'];
                }
                continue;
            }

            $medicationMap = [];
            foreach ($medications as $med) {
                $medicationMap[$med->id()->value()] = $med;
            }

            $medicationIds = array_map(static fn ($med) => $med->id(), $medications);
            $schedules = $this->scheduleRepository->findByMedicationIds($medicationIds);
            $scheduleMap = [];
            foreach ($schedules as $schedule) {
                $scheduleMap[$schedule->id()->value()] = $schedule;
            }
            $scheduleIds = array_map(static fn ($sch) => $sch->id(), $schedules);

            if (count($scheduleIds) === 0) {
                foreach ($links as $link) {
                    $skipped[] = ['profileId' => $profile->id()->value(), 'reason' => 'NO_SCHEDULES'];
                }
                continue;
            }

            $doseEvents = $this->doseEventRepository->findByScheduleIdsAndRange($scheduleIds, $now, $to);
            if (count($doseEvents) === 0) {
                foreach ($links as $link) {
                    $skipped[] = ['profileId' => $profile->id()->value(), 'reason' => 'NO_UPCOMING_DOSE_EVENTS'];
                }
                continue;
            }

            foreach ($links as $link) {
                $linkFailure = $this->syncLink(
                    $link,
                    $doseEvents,
                    $medicationMap,
                    $scheduleMap,
                    $profile->timezone(),
                    $eventsCreated,
                    $eventsUpdated
                );
                if ($linkFailure !== null) {
                    $linkFailures[] = $linkFailure;
                } else {
                    ++$linksSynced;
                }
            }
        }

        if ($linkFailures !== []) {
            return Result::failure(Failure::custom(
                'SYNC_PARTIAL_FAILURE',
                'Some calendar connections could not be synchronized.',
                [
                    'links' => $linkFailures,
                    'eventsCreated' => $eventsCreated,
                    'eventsUpdated' => $eventsUpdated,
                    'linksSynced' => $linksSynced,
                ]
            ));
        }

        return Result::success([
            'eventsCreated' => $eventsCreated,
            'eventsUpdated' => $eventsUpdated,
            'linksSynced' => $linksSynced,
            'skipped' => $skipped,
        ]);
    }

    /**
     * @param \DoseEvent\Domain\DoseEvent[] $doseEvents
     * @param array<string, \Medication\Domain\Medication> $medicationMap
     * @param array<string, \Schedule\Domain\Schedule> $scheduleMap
     * @param-out int $eventsCreated
     * @param-out int $eventsUpdated
     *
     * @return array{provider: string, reason: string, detail?: string}|null Per-link failure detail, null on success.
     */
    private function syncLink(CalendarLink $link, array $doseEvents, array $medicationMap, array $scheduleMap, string $timeZone, int &$eventsCreated, int &$eventsUpdated): ?array
    {
        try {
            $gateway = $this->providerResolver->resolveString($link->provider());
        } catch (\InvalidArgumentException) {
            return ['provider' => $link->provider(), 'reason' => 'UNSUPPORTED_PROVIDER'];
        }

        try {
            $tokens = $gateway->refreshAccessToken(
                $this->tokenVault->decrypt($link->encryptedRefreshToken())
            );
        } catch (CalendarAuthorizationRevoked | \InvalidArgumentException) {
            // Access was revoked, or stored ciphertext is undecryptable (tampered row or rotated vault key).
            if ($link->status() !== CalendarLinkStatus::REAUTH_REQUIRED) {
                $link->markReauthorizationRequired();
                $this->calendarLinkRepository->save($link);
            }

            return $this->reauthRequired($link);
        } catch (\Throwable $exception) {
            return [
                'provider' => $link->provider(),
                'reason' => 'REFRESH_FAILED',
                'detail' => $exception->getMessage(),
            ];
        }

        if ($link->status() === CalendarLinkStatus::REAUTH_REQUIRED) {
            $link->markActive();
            $this->calendarLinkRepository->save($link);
        }

        $rotatedRefreshToken = $tokens->refreshToken();
        if ($rotatedRefreshToken !== null) {
            $link->updateEncryptedRefreshToken($this->tokenVault->encrypt($rotatedRefreshToken));
            $this->calendarLinkRepository->save($link);
        }

        $doseEventIds = array_map(static fn ($event) => $event->id()->value(), $doseEvents);
        $mappings = $this->mappingRepository->findByDoseEvents($doseEventIds, $link->provider());

        foreach ($doseEvents as $event) {
            $med = $medicationMap[$event->medicationId()->value()] ?? null;
            if ($med === null) {
                continue;
            }

            $schedule = $scheduleMap[$event->scheduleId()->value()] ?? null;
            $doseDisplay = $schedule?->dose()?->display();
            $title = $doseDisplay !== null
                ? sprintf('Take Medication: %s (%s)', $med->name(), $doseDisplay)
                : sprintf('Take Medication: %s', $med->name());
            $mappingKey = $event->id()->value() . ':' . $link->provider();
            $mapping = $mappings[$mappingKey] ?? null;

            try {
                $zone = Timezone::dateTimeZoneOrUtc($timeZone);
                $scheduledAt = $event->scheduledAt()->setTimezone($zone);
                $description = sprintf(
                    "Instructions: %s\nStatus: %s\nScheduled At: %s",
                    $med->instructions() ?? 'None',
                    ucfirst($event->status()),
                    $scheduledAt->format(\DateTimeInterface::ATOM)
                );

                $start = $scheduledAt;
                $end = $start->modify('+30 minutes');
                $externalEventId = $gateway->upsertEvent(
                    $tokens->accessToken(),
                    $title,
                    $start,
                    $end,
                    $description,
                    $mapping?->externalEventId(),
                    self::idempotencyKey($event->id()->value(), $link->provider()),
                    $zone->getName()
                );
            } catch (\Throwable $exception) {
                return [
                    'provider' => $link->provider(),
                    'reason' => 'UPSERT_FAILED',
                    'detail' => $exception->getMessage(),
                ];
            }

            if ($mapping === null) {
                $mapping = CalendarEventMapping::create(
                    $event->id()->value(),
                    $link->provider(),
                    $externalEventId
                );
                $mappings[$mappingKey] = $mapping;
                $this->mappingRepository->save($mapping);
                ++$eventsCreated;
            } elseif ($mapping->externalEventId() !== $externalEventId) {
                $mapping->updateExternalEventId($externalEventId);
                $this->mappingRepository->save($mapping);
                ++$eventsUpdated;
            }
        }

        $this->mappingRepository->flush();

        return null;
    }

    private static function idempotencyKey(string $doseEventId, string $provider): string
    {
        return substr(hash('sha256', 'calendar-event:' . $provider . ':' . $doseEventId), 0, 32);
    }

    /**
     * @return array{provider: string, reason: string}
     */
    private function reauthRequired(CalendarLink $link): array
    {
        return ['provider' => $link->provider(), 'reason' => 'REAUTH_REQUIRED'];
    }
}
