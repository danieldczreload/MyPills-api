<?php

declare(strict_types=1);

namespace App\Tests\Notification\Domain;

use Notification\Domain\DeviceToken;
use Notification\Domain\DueDoseReminder;
use Notification\Domain\NotificationPreferences;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\UserId;

final class NotificationDomainTest extends TestCase
{
    public function testDeviceTokenCreationAndMetadata(): void
    {
        $userId = UserId::generate();
        $token = DeviceToken::create($userId, 'test-token', 'android', 'es-MX');

        self::assertTrue($token->accountId()->equals($userId));
        self::assertSame('test-token', $token->token());
        self::assertSame('android', $token->platform());
        self::assertSame('es-MX', $token->locale());
        self::assertNotEmpty($token->id());

        $token->updateMetadata('ios', 'en-US');
        self::assertSame('ios', $token->platform());
        self::assertSame('en-US', $token->locale());
    }

    public function testCanonicalizeLocaleNormalizesUnderscoreAndCase(): void
    {
        self::assertSame('es-MX', DeviceToken::canonicalizeLocale('es_MX'));
        self::assertSame('es-MX', DeviceToken::canonicalizeLocale('ES-mx'));
        self::assertSame('es-SV', DeviceToken::canonicalizeLocale('es_sv'));
        self::assertSame('en', DeviceToken::canonicalizeLocale('EN'));
        self::assertNull(DeviceToken::canonicalizeLocale('invalid_locale_123'));
        self::assertNull(DeviceToken::canonicalizeLocale('es-MEX'));
        self::assertNull(DeviceToken::canonicalizeLocale(''));
    }

    public function testDueDoseReminderGetters(): void
    {
        $doseId = DoseEventId::generate();
        $userId = UserId::generate();
        $scheduledAt = new \DateTimeImmutable('2026-08-01 12:00:00');

        $reminder = new DueDoseReminder(
            $doseId,
            $userId,
            'Ibuprofen',
            '400mg',
            $scheduledAt,
            15,
            true,
            true
        );

        self::assertTrue($reminder->doseEventId->equals($doseId));
        self::assertTrue($reminder->accountId->equals($userId));
        self::assertSame('Ibuprofen', $reminder->medicationName);
        self::assertSame('400mg', $reminder->dosage);
        self::assertSame($scheduledAt, $reminder->scheduledAt);
        self::assertSame(15, $reminder->reminderMinutesBefore);
        self::assertTrue($reminder->doseRemindersEnabled);
        self::assertTrue($reminder->inAppBannersEnabled);
    }

    public function testNotificationPreferencesDefaultAndQuietHours(): void
    {
        $userId = UserId::generate();
        $prefs = NotificationPreferences::createDefault($userId);

        self::assertTrue($prefs->accountId()->equals($userId));
        self::assertTrue($prefs->doseRemindersEnabled());
        self::assertTrue($prefs->inAppBannersEnabled());
        self::assertSame(0, $prefs->reminderMinutesBefore());

        $prefs->update(
            doseRemindersEnabled: false,
            inAppBannersEnabled: false,
            reminderMinutesBefore: 15
        );

        self::assertFalse($prefs->doseRemindersEnabled());
        self::assertFalse($prefs->inAppBannersEnabled());
        self::assertSame(15, $prefs->reminderMinutesBefore());
    }
}
