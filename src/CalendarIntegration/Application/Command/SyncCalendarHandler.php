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
     * @return Result<null>
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

        foreach ($profiles as $profile) {
            $links = $this->calendarLinkRepository->findByProfile($profile->id());
            if (count($links) === 0) {
                continue;
            }

            $medications = $this->medicationRepository->findByProfileId($profile->id());
            if (count($medications) === 0) {
                continue;
            }

            $medicationMap = [];
            foreach ($medications as $med) {
                $medicationMap[$med->id()->value()] = $med;
            }

            $medicationIds = array_map(static fn ($med) => $med->id(), $medications);
            $schedules = $this->scheduleRepository->findByMedicationIds($medicationIds);
            $scheduleIds = array_map(static fn ($sch) => $sch->id(), $schedules);

            if (count($scheduleIds) === 0) {
                continue;
            }

            $doseEvents = $this->doseEventRepository->findByScheduleIdsAndRange($scheduleIds, $now, $to);

            foreach ($links as $link) {
                $linkFailure = $this->syncLink($link, $doseEvents, $medicationMap);
                if ($linkFailure !== null) {
                    $linkFailures[] = $linkFailure;
                }
            }
        }

        if ($linkFailures !== []) {
            return Result::failure(Failure::custom(
                'SYNC_PARTIAL_FAILURE',
                'Some calendar connections could not be synchronized.',
                ['links' => $linkFailures]
            ));
        }

        return Result::success();
    }

    /**
     * @param \DoseEvent\Domain\DoseEvent[] $doseEvents
     * @param array<string, \Medication\Domain\Medication> $medicationMap
     *
     * @return array{provider: string, reason: string}|null Per-link failure detail, null on success.
     */
    private function syncLink(CalendarLink $link, array $doseEvents, array $medicationMap): ?array
    {
        if ($link->status() === CalendarLinkStatus::REAUTH_REQUIRED) {
            return $this->reauthRequired($link);
        }

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
            $link->markReauthorizationRequired();
            $this->calendarLinkRepository->save($link);

            return $this->reauthRequired($link);
        } catch (\Throwable) {
            return ['provider' => $link->provider(), 'reason' => 'REFRESH_FAILED'];
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

            $title = sprintf('Take Medication: %s (%s)', $med->name(), $med->dosage());
            $description = sprintf(
                "Instructions: %s\nStatus: %s\nScheduled At: %s",
                $med->instructions() ?? 'None',
                ucfirst($event->status()),
                $event->scheduledAt()->format(\DateTimeInterface::ATOM)
            );

            $start = $event->scheduledAt();
            $end = $start->modify('+30 minutes');

            $mappingKey = $event->id()->value() . ':' . $link->provider();
            $mapping = $mappings[$mappingKey] ?? null;

            try {
                $externalEventId = $gateway->upsertEvent(
                    $tokens->accessToken(),
                    $title,
                    $start,
                    $end,
                    $description,
                    $mapping?->externalEventId(),
                    self::idempotencyKey($event->id()->value(), $link->provider())
                );
            } catch (\Throwable) {
                return ['provider' => $link->provider(), 'reason' => 'UPSERT_FAILED'];
            }

            if ($mapping === null) {
                $mapping = CalendarEventMapping::create(
                    $event->id()->value(),
                    $link->provider(),
                    $externalEventId
                );
                $mappings[$mappingKey] = $mapping;
                $this->mappingRepository->save($mapping);
            } elseif ($mapping->externalEventId() !== $externalEventId) {
                $mapping->updateExternalEventId($externalEventId);
                $this->mappingRepository->save($mapping);
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
