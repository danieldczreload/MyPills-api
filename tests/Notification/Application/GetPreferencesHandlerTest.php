<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application;

use Notification\Application\Query\GetPreferencesHandler;
use Notification\Application\Query\GetPreferencesQuery;
use Notification\Domain\NotificationPreferences;
use Notification\Domain\NotificationPreferencesRepository;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\UserId;

final class GetPreferencesHandlerTest extends TestCase
{
    public function testReturnsExistingPreferences(): void
    {
        $userId = new UserId('00000000-0000-0000-0000-000000000001');
        $prefs = new NotificationPreferences(
            'pref-1',
            $userId,
            true,
            false,
            true,
            false,
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            true,
            10
        );

        $repository = $this->createMock(NotificationPreferencesRepository::class);
        $repository->method('findByAccountId')->with($userId)->willReturn($prefs);

        $handler = new GetPreferencesHandler($repository);
        $result = $handler(new GetPreferencesQuery($userId->value()));

        self::assertTrue($result->isSuccess());
        /** @var array<string, mixed> $data */
        $data = $result->getValue();
        self::assertTrue($data['doseRemindersEnabled']);
        self::assertTrue($data['inAppBannersEnabled']);
        self::assertSame(10, $data['reminderMinutesBefore']);
    }

    public function testCreatesDefaultPreferencesIfNotFound(): void
    {
        $userId = new UserId('00000000-0000-0000-0000-000000000002');
        $repository = $this->createMock(NotificationPreferencesRepository::class);
        $repository->method('findByAccountId')->with($userId)->willReturn(null);
        $repository->expects(self::once())->method('save');

        $handler = new GetPreferencesHandler($repository);
        $result = $handler(new GetPreferencesQuery($userId->value()));

        self::assertTrue($result->isSuccess());
        /** @var array<string, mixed> $data */
        $data = $result->getValue();
        self::assertTrue($data['doseRemindersEnabled']);
        self::assertTrue($data['inAppBannersEnabled']);
        self::assertSame(0, $data['reminderMinutesBefore']);
    }
}
