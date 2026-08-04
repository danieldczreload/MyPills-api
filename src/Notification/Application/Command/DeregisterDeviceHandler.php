<?php

declare(strict_types=1);

namespace Notification\Application\Command;

use Notification\Domain\DeviceTokenRepository;
use Shared\Domain\Result;
use Shared\Domain\Failure;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeregisterDeviceHandler
{
    public function __construct(
        private readonly DeviceTokenRepository $deviceTokenRepository
    ) {
    }

    /**
     * @return Result<null>
     */
    public function __invoke(DeregisterDeviceCommand $command): Result
    {
        $deviceToken = $this->deviceTokenRepository->findById($command->deviceId);

        if ($deviceToken === null) {
            return Result::failure(Failure::notFound('Device token not found.'));
        }

        if ($deviceToken->accountId()->value() !== $command->accountId) {
            return Result::failure(Failure::forbidden('You do not own this device registration.'));
        }

        $this->deviceTokenRepository->delete($deviceToken);

        return Result::success();
    }
}
