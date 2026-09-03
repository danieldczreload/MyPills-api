<?php

declare(strict_types=1);

namespace DoseEvent\UI\Cli;

use DoseEvent\Application\Command\ExpandDoseEventsCommand as ExpandDoseEvents;
use Shared\Application\Bus\CommandBus;
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
        private readonly CommandBus $commandBus
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting DoseEvent expansion...');

        $result = $this->commandBus->dispatch(new ExpandDoseEvents());

        if ($result->isFailure()) {
            $output->writeln(sprintf('<error>Failed to expand dose events: %s</error>', $result->getFailure()->getMessage()));

            return Command::FAILURE;
        }

        /** @var array{schedulesScanned: int, doseEventsCreated: int, profilesQueuedForCalendarSync: int} $data */
        $data = $result->getValue();
        $output->writeln(sprintf('Found %d schedules.', $data['schedulesScanned']));
        $output->writeln(sprintf(
            '<info>Created %d dose event(s); queued calendar sync for %d profile(s).</info>',
            $data['doseEventsCreated'],
            $data['profilesQueuedForCalendarSync']
        ));
        $output->writeln('DoseEvent expansion complete.');

        return Command::SUCCESS;
    }
}
