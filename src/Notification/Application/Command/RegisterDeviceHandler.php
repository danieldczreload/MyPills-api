<?php

declare(strict_types=1);

namespace Notification\Application\Command;

use Notification\Domain\DeviceToken;
use Notification\Domain\DeviceTokenRepository;
use Shared\Domain\Result;
use Shared\Domain\Failure;
use Shared\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RegisterDeviceHandler
{
    public function __construct(
        private readonly DeviceTokenRepository $deviceTokenRepository
    ) {
    }

    /**
     * @return Result<array<string, mixed>>
     */
    public function __invoke(RegisterDeviceCommand $command): Result
    {
        if (trim($command->fcmToken) === '') {
            return Result::failure(Failure::validation('fcmToken cannot be empty.'));
        }

        $existing = $this->deviceTokenRepository->findByToken($command->fcmToken);

        if ($existing !== null) {
            if ($existing->accountId()->value() === $command->accountId) {
                return Result::success([
                    'id' => $existing->id(),
                    'token' => $existing->token(),
                    'platform' => $existing->platform(),
                    'locale' => $existing->locale(),
                ]);
            }
            $this->deviceTokenRepository->delete($existing);
        }

        $deviceToken = DeviceToken::create(
            new UserId($command->accountId),
            $command->fcmToken,
            $command->platform,
            $command->locale
        );

        $this->deviceTokenRepository->save($deviceToken);

        return Result::success([
            'id' => $deviceToken->id(),
            'token' => $deviceToken->token(),
            'platform' => $deviceToken->platform(),
            'locale' => $deviceToken->locale(),
        ]);
    }
}
