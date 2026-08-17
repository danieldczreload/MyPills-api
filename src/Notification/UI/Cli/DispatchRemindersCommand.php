<?php

declare(strict_types=1);

namespace Notification\UI\Cli;

use Notification\Application\Command\DispatchDueRemindersCommand;
use Shared\Application\Bus\CommandBus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:reminders:dispatch',
    description: 'Dispatches due dose reminders via FCM push notifications and in-app alerts.'
)]
final class DispatchRemindersCommand extends Command
{
    public function __construct(
        private readonly CommandBus $commandBus
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Scanning for due dose reminders...');

        $command = new DispatchDueRemindersCommand();
        $result = $this->commandBus->dispatch($command);

        if ($result->isFailure()) {
            $output->writeln(sprintf('<error>Failed to dispatch reminders: %s</error>', $result->getFailure()->getMessage()));
            return Command::FAILURE;
        }

        /** @var array{dispatched: int} $data */
        $data = $result->getValue();
        $output->writeln(sprintf('<info>Successfully evaluated and dispatched %d dose reminder(s).</info>', $data['dispatched']));

        return Command::SUCCESS;
    }
}
