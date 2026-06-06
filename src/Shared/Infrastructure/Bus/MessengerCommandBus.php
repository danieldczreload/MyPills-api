<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Bus;

use Shared\Application\Bus\CommandBus;
use Shared\Domain\Result;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

final class MessengerCommandBus implements CommandBus
{
    public function __construct(
        private readonly MessageBusInterface $commandBus
    ) {
    }

    /**
     * @return Result<mixed>
     */
    public function dispatch(object $command): Result
    {
        try {
            $envelope = $this->commandBus->dispatch($command);
        } catch (\Symfony\Component\Messenger\Exception\ValidationFailedException $e) {
            return $this->handleValidationException($e);
        } catch (\Symfony\Component\Messenger\Exception\HandlerFailedException $e) {
            $nested = $e->getPrevious();
            if ($nested instanceof \Symfony\Component\Messenger\Exception\ValidationFailedException) {
                return $this->handleValidationException($nested);
            }
            throw $e;
        }

        /** @var HandledStamp|null $handledStamp */
        $handledStamp = $envelope->last(HandledStamp::class);

        if ($handledStamp === null) {
            /** @var SentStamp|null $sentStamp */
            $sentStamp = $envelope->last(SentStamp::class);

            if ($sentStamp !== null) {
                return Result::success();
            }

            throw new \RuntimeException(sprintf('Command "%s" was not handled.', $command::class));
        }

        $result = $handledStamp->getResult();

        if (!$result instanceof Result) {
            throw new \RuntimeException(sprintf('Command handler must return an instance of %s, got "%s".', Result::class, get_debug_type($result)));
        }

        return $result;
    }

    /**
     * @return Result<null>
     */
    private function handleValidationException(\Symfony\Component\Messenger\Exception\ValidationFailedException $e): Result
    {
        $details = [];
        foreach ($e->getViolations() as $violation) {
            $details[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        return Result::failure(\Shared\Domain\Failure::validation('Validation failed.', $details));
    }
}
