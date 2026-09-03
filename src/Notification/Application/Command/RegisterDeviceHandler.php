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

        if (strlen($command->fcmToken) > 4096 || preg_match('/[\x00-\x1F\x7F]/', $command->fcmToken) === 1) {
            return Result::failure(Failure::validation('fcmToken has an invalid length or format.'));
        }

        if (!in_array($command->platform, ['android', 'ios'], true)) {
            return Result::failure(Failure::validation('platform must be android or ios.'));
        }

        $locale = DeviceToken::canonicalizeLocale($command->locale);
        if ($locale === null) {
            return Result::failure(Failure::validation('locale must use a valid locale such as es-MX.'));
        }

        $existing = $this->deviceTokenRepository->findByToken($command->fcmToken);

        if ($existing !== null) {
            if ($existing->accountId()->value() === $command->accountId) {
                if ($existing->platform() !== $command->platform || $existing->locale() !== $locale) {
                    $existing->updateMetadata($command->platform, $locale);
                    $this->deviceTokenRepository->save($existing);
                }

                return Result::success([
                    'id' => $existing->id(),
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
            $locale
        );

        $this->deviceTokenRepository->save($deviceToken);

        return Result::success([
            'id' => $deviceToken->id(),
            'platform' => $deviceToken->platform(),
            'locale' => $deviceToken->locale(),
        ]);
    }
}
