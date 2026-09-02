<?php

declare(strict_types=1);

namespace DoseEvent\UI\Cli;

use Doctrine\ORM\EntityManagerInterface;
use DoseEvent\Domain\DoseEventExpander;
use DoseEvent\Domain\DoseEventRepository;
use DoseEvent\Domain\DoseEventsExpandedEvent;
use Medication\Domain\MedicationRepository;
use Profile\Domain\ProfileRepository;
use Profile\Domain\ValueObject\Timezone;
use Schedule\Domain\ScheduleRepository;
use Schedule\Infrastructure\Persistence\ScheduleDoctrineEntity;
use Shared\Application\Bus\EventBus;
use Shared\Domain\ValueObject\ScheduleId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:dose-events:expand',
    description: 'Expands schedules into concrete DoseEvent occurrences for the next 14 days.'
)]
final class ExpandDoseEventsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ScheduleRepository $scheduleRepository,
        private readonly DoseEventRepository $doseEventRepository,
        private readonly DoseEventExpander $doseEventExpander,
        private readonly MedicationRepository $medicationRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly EventBus $eventBus
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting DoseEvent expansion...');

        $scheduleEntities = $this->entityManager->getRepository(ScheduleDoctrineEntity::class)->findAll();
        $output->writeln(sprintf('Found %d schedules.', count($scheduleEntities)));

        $now = new \DateTimeImmutable();
        $to = $now->modify('+14 days');
        /** @var array<string, string> $affectedProfiles */
        $affectedProfiles = [];

        foreach ($scheduleEntities as $entity) {
            $schedule = $this->scheduleRepository->findById(new ScheduleId($entity->getId()));
            if ($schedule === null) {
                continue;
            }

            $profileId = null;
            $timezone = new \DateTimeZone('UTC');
            $medication = $this->medicationRepository->findById($schedule->medicationId());
            if ($medication !== null) {
                $profile = $this->profileRepository->findById($medication->profileId());
                if ($profile !== null) {
                    $profileId = $profile->id()->value();
                    $timezone = Timezone::dateTimeZoneOrUtc($profile->timezone());
                }
            }

            $occurrences = $this->doseEventExpander->expand($schedule, $now, $to, $timezone);
            $existing = $this->doseEventRepository->findByScheduleIdsAndRange([$schedule->id()], $now, $to);
            $existingTimes = array_map(static fn ($e) => $e->scheduledAt()->format(\DateTimeInterface::ATOM), $existing);

            $newCount = 0;
            foreach ($occurrences as $occurrence) {
                $formattedTime = $occurrence->scheduledAt()->format(\DateTimeInterface::ATOM);
                if (!in_array($formattedTime, $existingTimes, true)) {
                    $this->doseEventRepository->save($occurrence);
                    ++$newCount;
                }
            }

            if ($newCount > 0) {
                $output->writeln(sprintf('  Schedule %s: Created %d new dose events.', $schedule->id()->value(), $newCount));
                if ($profileId !== null) {
                    $affectedProfiles[$profileId] = $schedule->id()->value();
                }
            }
        }

        foreach ($affectedProfiles as $profileId => $scheduleId) {
            $this->eventBus->publish(new DoseEventsExpandedEvent($profileId, $scheduleId));
        }

        $output->writeln('DoseEvent expansion complete.');

        return Command::SUCCESS;
    }
}
