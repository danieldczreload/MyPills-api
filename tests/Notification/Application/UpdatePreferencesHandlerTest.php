<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application;

use Notification\Application\Command\UpdatePreferencesCommand;
use Notification\Application\Command\UpdatePreferencesHandler;
use Notification\Domain\NotificationPreferences;
use Notification\Domain\NotificationPreferencesRepository;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\UserId;

final class UpdatePreferencesHandlerTest extends TestCase
{
    public function testUpdatesAllPreferenceFields(): void
    {
        $userId = new UserId('00000000-0000-0000-0000-000000000001');
        $prefs = NotificationPreferences::createDefault($userId);

        $repository = $this->createMock(NotificationPreferencesRepository::class);
        $repository->method('findByAccountId')->with($userId)->willReturn($prefs);
        $repository->expects(self::once())->method('save')->with(self::callback(function (NotificationPreferences $p) {
            return $p->doseRemindersEnabled() === false
                && $p->inAppBannersEnabled() === true
                && $p->reminderMinutesBefore() === 15;
        }));

        $handler = new UpdatePreferencesHandler($repository);
        $result = $handler(new UpdatePreferencesCommand(
            $userId->value(),
            doseRemindersEnabled: false,
            inAppBannersEnabled: true,
            reminderMinutesBefore: 15
        ));

        self::assertTrue($result->isSuccess());
        /** @var array<string, mixed> $data */
        $data = $result->getValue();
        self::assertFalse($data['doseRemindersEnabled']);
        self::assertTrue($data['inAppBannersEnabled']);
        self::assertSame(15, $data['reminderMinutesBefore']);
    }
}
