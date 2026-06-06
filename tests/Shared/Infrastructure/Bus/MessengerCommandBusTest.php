<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Bus;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Result;
use Shared\Infrastructure\Bus\MessengerCommandBus;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\ValidationFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Validator\ConstraintViolationList;

final class MessengerCommandBusTest extends TestCase
{
    /** @var MessageBusInterface&\PHPUnit\Framework\MockObject\MockObject */
    private MessageBusInterface $messageBus;
    private MessengerCommandBus $commandBus;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->commandBus = new MessengerCommandBus($this->messageBus);
    }

    public function testDispatchSynchronousSuccess(): void
    {
        $command = new \stdClass();
        $expectedResult = Result::success('sync-result');

        $envelope = new Envelope($command, [
            new HandledStamp($expectedResult, 'handler_name'),
        ]);

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with($command)
            ->willReturn($envelope);

        $result = $this->commandBus->dispatch($command);

        self::assertSame($expectedResult, $result);
    }

    public function testDispatchAsynchronousSuccess(): void
    {
        $command = new \stdClass();

        $envelope = new Envelope($command, [
            new SentStamp('transport_name', 'sender_alias'),
        ]);

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with($command)
            ->willReturn($envelope);

        $result = $this->commandBus->dispatch($command);

        self::assertTrue($result->isSuccess());
        self::assertNull($result->getValue());
    }

    public function testDispatchCatchesDirectValidationException(): void
    {
        $command = new \stdClass();
        $violations = new ConstraintViolationList();
        $exception = new ValidationFailedException($command, $violations);

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with($command)
            ->willThrowException($exception);

        $result = $this->commandBus->dispatch($command);

        self::assertTrue($result->isFailure());
        self::assertSame('VALIDATION', $result->getFailure()->getType());
    }

    public function testDispatchCatchesWrappedValidationException(): void
    {
        $command = new \stdClass();
        $violations = new ConstraintViolationList();
        $validationException = new ValidationFailedException($command, $violations);
        $handlerException = new HandlerFailedException(new Envelope($command), [$validationException]);

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with($command)
            ->willThrowException($handlerException);

        $result = $this->commandBus->dispatch($command);

        self::assertTrue($result->isFailure());
        self::assertSame('VALIDATION', $result->getFailure()->getType());
    }

    public function testDispatchThrowsExceptionWhenNotHandledAndNotSent(): void
    {
        $command = new \stdClass();
        $envelope = new Envelope($command);

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with($command)
            ->willReturn($envelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Command "stdClass" was not handled.');

        $this->commandBus->dispatch($command);
    }

    public function testDispatchThrowsExceptionWhenHandlerReturnsNonResult(): void
    {
        $command = new \stdClass();
        $envelope = new Envelope($command, [
            new HandledStamp('not-a-result-object', 'handler_name'),
        ]);

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with($command)
            ->willReturn($envelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Command handler must return an instance of Shared\Domain\Result, got "string".');

        $this->commandBus->dispatch($command);
    }
}
