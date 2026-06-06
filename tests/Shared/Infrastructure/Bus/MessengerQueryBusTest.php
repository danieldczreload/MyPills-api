<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Bus;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Result;
use Shared\Infrastructure\Bus\MessengerQueryBus;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\ValidationFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Validator\ConstraintViolationList;

final class MessengerQueryBusTest extends TestCase
{
    /** @var MessageBusInterface&\PHPUnit\Framework\MockObject\MockObject */
    private MessageBusInterface $messageBus;
    private MessengerQueryBus $queryBus;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = new MessengerQueryBus($this->messageBus);
    }

    public function testAskSuccess(): void
    {
        $query = new \stdClass();
        $expectedResult = Result::success('query-result');

        $envelope = new Envelope($query, [
            new HandledStamp($expectedResult, 'handler_name'),
        ]);

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with($query)
            ->willReturn($envelope);

        $result = $this->queryBus->ask($query);

        self::assertSame($expectedResult, $result);
    }

    public function testAskCatchesDirectValidationException(): void
    {
        $query = new \stdClass();
        $violations = new ConstraintViolationList();
        $exception = new ValidationFailedException($query, $violations);

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with($query)
            ->willThrowException($exception);

        $result = $this->queryBus->ask($query);

        self::assertTrue($result->isFailure());
        self::assertSame('VALIDATION', $result->getFailure()->getType());
    }

    public function testAskCatchesWrappedValidationException(): void
    {
        $query = new \stdClass();
        $violations = new ConstraintViolationList();
        $validationException = new ValidationFailedException($query, $violations);
        $handlerException = new HandlerFailedException(new Envelope($query), [$validationException]);

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with($query)
            ->willThrowException($handlerException);

        $result = $this->queryBus->ask($query);

        self::assertTrue($result->isFailure());
        self::assertSame('VALIDATION', $result->getFailure()->getType());
    }

    public function testAskThrowsExceptionWhenNotHandled(): void
    {
        $query = new \stdClass();
        $envelope = new Envelope($query);

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with($query)
            ->willReturn($envelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Query "stdClass" was not handled.');

        $this->queryBus->ask($query);
    }

    public function testAskThrowsExceptionWhenHandlerReturnsNonResult(): void
    {
        $query = new \stdClass();
        $envelope = new Envelope($query, [
            new HandledStamp('non-result', 'handler_name'),
        ]);

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with($query)
            ->willReturn($envelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Query handler must return an instance of Shared\Domain\Result, got "string".');

        $this->queryBus->ask($query);
    }
}
