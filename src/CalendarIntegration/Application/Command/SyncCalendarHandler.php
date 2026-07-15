<?php

declare(strict_types=1);

namespace CalendarIntegration\Application\Command;

use CalendarIntegration\Domain\CalendarLinkRepository;
use CalendarIntegration\Domain\CalendarEventMapping;
use CalendarIntegration\Domain\CalendarEventMappingRepository;
use CalendarIntegration\Domain\CalendarGateway;
use DoseEvent\Domain\DoseEventRepository;
use Medication\Domain\MedicationRepository;
use Profile\Domain\ProfileRepository;
use Schedule\Domain\ScheduleRepository;
use Shared\Domain\Result;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\UserId;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        #[Autowire(service: 'CalendarIntegration\Infrastructure\LoggerGoogleCalendarGateway')]
        private readonly CalendarGateway $googleGateway,
        #[Autowire(service: 'CalendarIntegration\Infrastructure\LoggerMicrosoftCalendarGateway')]
        private readonly CalendarGateway $microsoftGateway
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
            if ($profile !== null && $profile->accountId()->equals($accountId)) {
                $profiles[] = $profile;
            }
        } else {
            $profiles = $this->profileRepository->findByAccountId($accountId);
        }

        $now = new \DateTimeImmutable();
        $to = $now->modify('+14 days');

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
                $gateway = $link->provider() === 'google' ? $this->googleGateway : $this->microsoftGateway;

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

                    // Check if mapping already exists
                    $mapping = $this->mappingRepository->findByDoseEventAndProvider($event->id()->value(), $link->provider());

                    $externalEventId = $gateway->upsertEvent(
                        $link->refreshToken(),
                        $title,
                        $start,
                        $end,
                        $description
                    );

                    if ($mapping === null) {
                        $newMapping = CalendarEventMapping::create(
                            $event->id()->value(),
                            $link->provider(),
                            $externalEventId
                        );
                        $this->mappingRepository->save($newMapping);
                    }
                }
            }
        }

        return Result::success();
    }
}
