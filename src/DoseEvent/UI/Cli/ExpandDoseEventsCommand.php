<?php

declare(strict_types=1);

namespace DoseEvent\UI\Cli;

use DoseEvent\Domain\DoseEventRepository;
use DoseEvent\Domain\DoseEventExpander;
use Doctrine\ORM\EntityManagerInterface;
use Schedule\Infrastructure\Persistence\ScheduleDoctrineEntity;
use Schedule\Domain\ScheduleRepository;
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
        private readonly DoseEventExpander $doseEventExpander
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting DoseEvent expansion...');

        // Fetch all schedules
        $scheduleEntities = $this->entityManager->getRepository(ScheduleDoctrineEntity::class)->findAll();
        $output->writeln(sprintf('Found %d schedules.', count($scheduleEntities)));

        $now = new \DateTimeImmutable();
        $to = $now->modify('+14 days');

        foreach ($scheduleEntities as $entity) {
            $schedule = $this->scheduleRepository->findById(new \Shared\Domain\ValueObject\ScheduleId($entity->getId()));
            if ($schedule === null) {
                continue;
            }

            $occurrences = $this->doseEventExpander->expand($schedule, $now, $to);
            $existing = $this->doseEventRepository->findByScheduleIdsAndRange([$schedule->id()], $now, $to);
            $existingTimes = array_map(static fn ($e) => $e->scheduledAt()->format(\DateTimeInterface::ATOM), $existing);

            $newCount = 0;
            foreach ($occurrences as $occurrence) {
                $formattedTime = $occurrence->scheduledAt()->format(\DateTimeInterface::ATOM);
                if (!in_array($formattedTime, $existingTimes, true)) {
                    $this->doseEventRepository->save($occurrence);
                    $newCount++;
                }
            }

            if ($newCount > 0) {
                $output->writeln(sprintf('  Schedule %s: Created %d new dose events.', $schedule->id()->value(), $newCount));
            }
        }

        $output->writeln('DoseEvent expansion complete.');

        return Command::SUCCESS;
    }
}
