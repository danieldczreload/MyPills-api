<?php

declare(strict_types=1);

namespace App\Tests\Integration\Persistence;

use CalendarIntegration\Domain\CalendarEventMapping;
use CalendarIntegration\Domain\CalendarLink;
use CalendarIntegration\Infrastructure\Persistence\DoctrineCalendarEventMappingRepository;
use CalendarIntegration\Infrastructure\Persistence\DoctrineCalendarLinkRepository;
use DoseEvent\Domain\DoseEvent;
use DoseEvent\Infrastructure\Persistence\DoctrineDoseEventRepository;
use Identity\Domain\Account;
use Identity\Domain\AccountOAuthLink;
use Identity\Domain\RefreshToken;
use Identity\Infrastructure\Persistence\DoctrineAccountRepository;
use Identity\Infrastructure\Persistence\DoctrineRefreshTokenRepository;
use Medication\Domain\Medication;
use Medication\Infrastructure\Persistence\DoctrineMedicationRepository;
use Notification\Domain\DeviceToken;
use Notification\Domain\NotificationPreferences;
use Notification\Infrastructure\Persistence\DoctrineDeviceTokenRepository;
use Notification\Infrastructure\Persistence\DoctrineDueDoseReminderRepository;
use Notification\Infrastructure\Persistence\DoctrineNotificationPreferencesRepository;
use Profile\Domain\PatientProfile;
use Profile\Domain\Tombstone;
use Profile\Infrastructure\Persistence\DoctrineProfileRepository;
use Profile\Infrastructure\Persistence\DoctrineTombstoneRepository;
use Schedule\Domain\DailyIntervalSchedule;
use Schedule\Domain\DailySchedule;
use Shared\Domain\ValueObject\Dose;
use Schedule\Domain\SpecificDaysSchedule;
use Schedule\Domain\ValueObject\TimeOfDay;
use Schedule\Infrastructure\Persistence\DoctrineScheduleRepository;
use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\Email;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;
use Shared\Domain\ValueObject\TaxonomyGroupId;
use Shared\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Taxonomy\Domain\TaxonomyGroup;
use Taxonomy\Infrastructure\Persistence\DoctrineTaxonomyGroupRepository;

final class DoctrineRepositoriesTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testAccountAndRefreshTokenRepository(): void
    {
        $container = static::getContainer();
        /** @var DoctrineAccountRepository $accountRepo */
        $accountRepo = $container->get(DoctrineAccountRepository::class);
        /** @var DoctrineRefreshTokenRepository $refreshRepo */
        $refreshRepo = $container->get(DoctrineRefreshTokenRepository::class);

        $userId = UserId::generate();
        $email = new Email('test-' . uniqid() . '@example.com');
        $account = Account::create($userId, $email);

        $accountRepo->save($account);
        $found = $accountRepo->findById($userId);
        self::assertNotNull($found);
        self::assertTrue($found->email()->equals($email));

        $foundByEmail = $accountRepo->findByEmail($email);
        self::assertNotNull($foundByEmail);

        $link = AccountOAuthLink::create($userId, 'google', 'ext-' . uniqid());
        $accountRepo->saveLink($link);
        $foundLinked = $accountRepo->findLinked('google', $link->externalId());
        self::assertNotNull($foundLinked);

        // RefreshToken
        $rawToken = bin2hex(random_bytes(32));
        $refreshToken = RefreshToken::create($userId, $rawToken);
        $refreshRepo->save($refreshToken);

        $foundToken = $refreshRepo->findByToken($rawToken);
        self::assertNotNull($foundToken);
        self::assertTrue($foundToken->accountId()->equals($userId));

        $refreshRepo->delete($foundToken);
        self::assertNull($refreshRepo->findByToken($rawToken));
    }

    public function testProfileAndTombstoneRepository(): void
    {
        $container = static::getContainer();
        /** @var DoctrineProfileRepository $profileRepo */
        $profileRepo = $container->get(DoctrineProfileRepository::class);
        /** @var DoctrineTombstoneRepository $tombstoneRepo */
        $tombstoneRepo = $container->get(DoctrineTombstoneRepository::class);

        $userId = UserId::generate();
        $profileId = ProfileId::generate();
        $profile = PatientProfile::create($profileId, $userId, 'Jane Doe', new \DateTimeImmutable('1985-05-15'), 'female');

        $profileRepo->save($profile);
        $found = $profileRepo->findById($profileId);
        self::assertNotNull($found);
        self::assertSame('Jane Doe', $found->name());

        $byAccount = $profileRepo->findByAccountId($userId);
        self::assertCount(1, $byAccount);

        $tombstone = Tombstone::create($profileId, 'medication', 'med-123');
        $tombstoneRepo->save($tombstone);
        $tombstones = $tombstoneRepo->findByProfileIdSince($profileId, new \DateTimeImmutable('-1 hour'));
        self::assertNotEmpty($tombstones);

        $profileRepo->delete($profile);
        self::assertNull($profileRepo->findById($profileId));
    }

    public function testMedicationScheduleAndDoseEventRepositories(): void
    {
        $container = static::getContainer();
        /** @var DoctrineMedicationRepository $medRepo */
        $medRepo = $container->get(DoctrineMedicationRepository::class);
        /** @var DoctrineScheduleRepository $schedRepo */
        $schedRepo = $container->get(DoctrineScheduleRepository::class);
        /** @var DoctrineDoseEventRepository $doseRepo */
        $doseRepo = $container->get(DoctrineDoseEventRepository::class);

        $profileId = ProfileId::generate();
        $medId = MedicationId::generate();
        $medication = Medication::create($medId, $profileId, 'Paracetamol', 'After meals', null, 'client-med-1');

        $medRepo->save($medication);
        $foundMed = $medRepo->findById($medId);
        self::assertNotNull($foundMed);
        self::assertSame('Paracetamol', $foundMed->name());

        $byClientId = $medRepo->findByClientId('client-med-1');
        self::assertNotNull($byClientId);

        // Daily Schedule
        $dailyId = ScheduleId::generate();
        $now = new \DateTimeImmutable();
        $dailySched = new DailySchedule($dailyId, $medId, [new TimeOfDay(8, 0), new TimeOfDay(20, 0)], $now, null, 'client-sch-1', $now, $now, Dose::of(500, 'mg'));
        $schedRepo->save($dailySched);

        // DailyInterval Schedule
        $intervalId = ScheduleId::generate();
        $intervalSched = new DailyIntervalSchedule($intervalId, $medId, 6, new TimeOfDay(6, 0), new TimeOfDay(22, 0), $now, null, 'client-sch-2', $now, $now);
        $schedRepo->save($intervalSched);

        // SpecificDays Schedule
        $specId = ScheduleId::generate();
        $specSched = new SpecificDaysSchedule($specId, $medId, [1, 3, 5], [new TimeOfDay(9, 0)], $now, null, 'client-sch-3', $now, $now);
        $schedRepo->save($specSched);

        $foundDaily = $schedRepo->findById($dailyId);
        self::assertNotNull($foundDaily);
        self::assertSame('daily', $foundDaily->type());
        self::assertSame('500 mg', $foundDaily->dose()?->display());

        $foundInterval = $schedRepo->findById($intervalId);
        self::assertNotNull($foundInterval);
        self::assertSame('daily_interval', $foundInterval->type());

        $foundSpec = $schedRepo->findById($specId);
        self::assertNotNull($foundSpec);
        self::assertSame('specific_days', $foundSpec->type());

        $schedules = $schedRepo->findByMedicationIds([$medId]);
        self::assertCount(3, $schedules);

        // DoseEvent
        $doseId = DoseEventId::generate();
        $dose = DoseEvent::create($doseId, $medId, $dailyId, $now, 'pending', null, 'client-dose-1');
        $doseRepo->save($dose);

        $foundDose = $doseRepo->findById($doseId);
        self::assertNotNull($foundDose);

        $byClientDose = $doseRepo->findByClientId('client-dose-1');
        self::assertNotNull($byClientDose);

        $byRange = $doseRepo->findByScheduleIdsAndRange([$dailyId], $now->modify('-1 hour'), $now->modify('+1 hour'));
        self::assertNotEmpty($byRange);

        $schedRepo->delete($dailySched);
        $schedRepo->delete($intervalSched);
        $schedRepo->delete($specSched);
        $medRepo->delete($medication);
    }

    public function testNotificationAndDueDoseReminderRepositories(): void
    {
        $container = static::getContainer();
        /** @var DoctrineNotificationPreferencesRepository $prefRepo */
        $prefRepo = $container->get(DoctrineNotificationPreferencesRepository::class);
        /** @var DoctrineDeviceTokenRepository $deviceRepo */
        $deviceRepo = $container->get(DoctrineDeviceTokenRepository::class);
        /** @var DoctrineDueDoseReminderRepository $dueRepo */
        $dueRepo = $container->get(DoctrineDueDoseReminderRepository::class);

        $userId = UserId::generate();
        $prefs = NotificationPreferences::createDefault($userId);
        $prefRepo->save($prefs);

        $foundPrefs = $prefRepo->findByAccountId($userId);
        self::assertNotNull($foundPrefs);
        self::assertTrue($foundPrefs->doseRemindersEnabled());

        $prefs->update(reminderMinutesBefore: 15);
        $prefRepo->save($prefs);

        $devToken = DeviceToken::create($userId, 'fcm-token-' . uniqid(), 'android', 'es-MX');
        $deviceRepo->save($devToken);

        $foundToken = $deviceRepo->findByToken($devToken->token());
        self::assertNotNull($foundToken);

        $byId = $deviceRepo->findById($devToken->id());
        self::assertNotNull($byId);

        $byAccount = $deviceRepo->findByAccountId($userId);
        self::assertCount(1, $byAccount);

        $due = $dueRepo->findDueDoseReminders(new \DateTimeImmutable());
        self::assertSame([], $due);

        $deviceRepo->delete($devToken);
    }

    public function testCalendarLinkAndMappingRepositories(): void
    {
        $container = static::getContainer();
        /** @var DoctrineCalendarLinkRepository $linkRepo */
        $linkRepo = $container->get(DoctrineCalendarLinkRepository::class);
        /** @var DoctrineCalendarEventMappingRepository $mapRepo */
        $mapRepo = $container->get(DoctrineCalendarEventMappingRepository::class);
        /** @var \Shared\Domain\TokenVault $tokenVault */
        $tokenVault = $container->get(\Shared\Domain\TokenVault::class);

        $profileId = ProfileId::generate();
        $encryptedToken = $tokenVault->encrypt('refresh-token-secret');
        $link = CalendarLink::create($profileId, 'google', $encryptedToken);
        $linkRepo->save($link);

        $foundLink = $linkRepo->findByProfileAndProvider($profileId, 'google');
        self::assertNotNull($foundLink);

        $byProfile = $linkRepo->findByProfile($profileId);
        self::assertCount(1, $byProfile);

        $mapping = CalendarEventMapping::create('dose-123', 'google', 'g-evt-456');
        $mapRepo->save($mapping);

        $foundMaps = $mapRepo->findByDoseEvents(['dose-123'], 'google');
        self::assertNotEmpty($foundMaps);

        $byDoseId = $mapRepo->findByDoseEventId('dose-123');
        self::assertCount(1, $byDoseId);

        $byDoseIds = $mapRepo->findByDoseEventIds(['dose-123']);
        self::assertCount(1, $byDoseIds);

        $mapRepo->delete($mapping);
        $linkRepo->delete($link);
    }

    public function testTaxonomyGroupRepository(): void
    {
        $container = static::getContainer();
        /** @var DoctrineTaxonomyGroupRepository $taxRepo */
        $taxRepo = $container->get(DoctrineTaxonomyGroupRepository::class);

        $profileId = ProfileId::generate();
        $groupId = TaxonomyGroupId::generate();
        $group = TaxonomyGroup::create(
            $groupId,
            $profileId,
            'category',
            'Pain Relief',
            'Medications for pain',
            'pill_icon',
            0xFF0000,
            true,
            'client-tax-1'
        );

        $taxRepo->save($group);

        $found = $taxRepo->findById($groupId);
        self::assertNotNull($found);
        self::assertSame('Pain Relief', $found->name());
        self::assertSame('category', $found->type());
        self::assertSame('Medications for pain', $found->description());
        self::assertSame('pill_icon', $found->iconName());
        self::assertSame(0xFF0000, $found->colorValue());
        self::assertTrue($found->isCustom());
        self::assertSame('client-tax-1', $found->clientId());

        $byProfile = $taxRepo->findByProfileId($profileId);
        self::assertCount(1, $byProfile);

        $byClientId = $taxRepo->findByClientId('client-tax-1');
        self::assertNotNull($byClientId);
        self::assertSame($groupId->value(), $byClientId->id()->value());

        // Update group
        $group->update(
            type: 'tag',
            name: 'Updated Relief',
            description: 'Updated desc',
            iconName: 'new_icon',
            colorValue: 0x00FF00,
            isCustom: false
        );
        $taxRepo->save($group);

        $updatedFound = $taxRepo->findById($groupId);
        self::assertNotNull($updatedFound);
        self::assertSame('Updated Relief', $updatedFound->name());
        self::assertSame('tag', $updatedFound->type());
        self::assertSame('Updated desc', $updatedFound->description());
        self::assertSame('new_icon', $updatedFound->iconName());
        self::assertSame(0x00FF00, $updatedFound->colorValue());
        self::assertFalse($updatedFound->isCustom());

        // Delete group
        $taxRepo->delete($group);
        self::assertNull($taxRepo->findById($groupId));
        self::assertNull($taxRepo->findByClientId('client-tax-1'));
    }
}
