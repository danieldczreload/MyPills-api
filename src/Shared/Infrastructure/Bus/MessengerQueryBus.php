<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Bus;

use Shared\Application\Bus\QueryBus;
use Shared\Domain\Result;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class MessengerQueryBus implements QueryBus
{
    public function __construct(
        private readonly MessageBusInterface $queryBus
    ) {
    }

    /**
     * @return Result<mixed>
     */
    public function ask(object $query): Result
    {
        try {
            $envelope = $this->queryBus->dispatch($query);
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
            throw new \RuntimeException(sprintf('Query "%s" was not handled.', $query::class));
        }

        $result = $handledStamp->getResult();

        if (!$result instanceof Result) {
            throw new \RuntimeException(sprintf('Query handler must return an instance of %s, got "%s".', Result::class, get_debug_type($result)));
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
