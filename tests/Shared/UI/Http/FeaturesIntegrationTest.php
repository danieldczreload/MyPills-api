<?php

declare(strict_types=1);

namespace App\Tests\Shared\UI\Http;

use Shared\Infrastructure\Security\JwtService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class FeaturesIntegrationTest extends WebTestCase
{
    private function createAuthHeaders(string $userId): array
    {
        /** @var JwtService $jwtService */
        $jwtService = self::getContainer()->get(JwtService::class);
        $token = $jwtService->createToken(['sub' => $userId]);
        return [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ];
    }

    public function testCompleteUserJourney(): void
    {
        $client = self::createClient();
        $userId = 'test-user-uuid-' . bin2hex(random_bytes(4));
        $headers = $this->createAuthHeaders($userId);

        // 1. Create Profile
        $client->request(
            'POST',
            '/api/v1/profiles',
            [],
            [],
            $headers,
            json_encode([
                'name' => 'John Doe',
                'birthDate' => '1990-01-01T00:00:00Z',
                'gender' => 'male',
                'photoUrl' => 'https://example.com/photo.jpg',
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $profileData = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($profileData);
        self::assertArrayHasKey('id', $profileData);
        $profileId = $profileData['id'];
        self::assertSame('John Doe', $profileData['name']);

        // 2. Get Profiles
        $client->request('GET', '/api/v1/profiles', [], [], $headers);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $profilesList = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($profilesList);
        self::assertNotEmpty($profilesList);

        // 3. Update Profile
        $client->request(
            'PATCH',
            '/api/v1/profiles/' . $profileId,
            [],
            [],
            $headers,
            json_encode([
                'name' => 'John Doe Updated',
                'birthDate' => '1990-01-01T00:00:00Z',
                'gender' => 'male',
                'photoUrl' => 'https://example.com/photo2.jpg',
            ])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $updatedProfile = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('John Doe Updated', $updatedProfile['name']);

        // 4. Create Medication
        $medicationClientId = 'med-client-uuid-' . bin2hex(random_bytes(4));
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/medications',
            [],
            [],
            $headers,
            json_encode([
                'name' => 'Ibuprofen',
                'dosage' => '400mg',
                'instructions' => 'Take with food',
                'photoUrl' => 'https://example.com/ibuprofen.jpg',
                'clientId' => $medicationClientId,
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $medData = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($medData);
        $medicationId = $medData['id'];
        self::assertSame('Ibuprofen', $medData['name']);
        self::assertSame($medicationClientId, $medData['clientId']);

        // Test Idempotent creation of Medication
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/medications',
            [],
            [],
            $headers,
            json_encode([
                'name' => 'Ibuprofen',
                'dosage' => '400mg',
                'instructions' => 'Take with food',
                'photoUrl' => 'https://example.com/ibuprofen.jpg',
                'clientId' => $medicationClientId,
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $medDataDup = json_decode($client->getResponse()->getContent(), true);
        self::assertSame($medicationId, $medDataDup['id']);

        // 5. Get Medications
        $client->request('GET', '/api/v1/profiles/' . $profileId . '/medications', [], [], $headers);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $medsList = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($medsList);
        self::assertNotEmpty($medsList);

        // 6. Create Daily Schedule
        $scheduleClientId = 'sch-client-uuid-' . bin2hex(random_bytes(4));
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/schedules',
            [],
            [],
            $headers,
            json_encode([
                'medicationId' => $medicationId,
                'type' => 'daily',
                'startDate' => '2026-07-01T00:00:00Z',
                'endDate' => '2026-07-15T00:00:00Z',
                'timesOfDay' => [
                    ['hour' => 8, 'minute' => 0],
                    ['hour' => 20, 'minute' => 0],
                ],
                'clientId' => $scheduleClientId,
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $schData = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($schData);
        $scheduleId = $schData['id'];
        self::assertSame($scheduleClientId, $schData['clientId']);

        // Test Idempotent creation of Schedule
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/schedules',
            [],
            [],
            $headers,
            json_encode([
                'medicationId' => $medicationId,
                'type' => 'daily',
                'startDate' => '2026-07-01T00:00:00Z',
                'endDate' => '2026-07-15T00:00:00Z',
                'timesOfDay' => [
                    ['hour' => 8, 'minute' => 0],
                    ['hour' => 20, 'minute' => 0],
                ],
                'clientId' => $scheduleClientId,
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $schDataDup = json_decode($client->getResponse()->getContent(), true);
        self::assertSame($scheduleId, $schDataDup['id']);

        // 7. Get Schedules
        $client->request('GET', '/api/v1/profiles/' . $profileId . '/schedules', [], [], $headers);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $schedulesList = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($schedulesList);
        self::assertNotEmpty($schedulesList);

        // 8. Get Dose Events (pre-expanded from schedule creation)
        $client->request(
            'GET',
            '/api/v1/profiles/' . $profileId . '/dose-events?from=2026-07-01T00:00:00Z&to=2026-07-05T00:00:00Z',
            [],
            [],
            $headers
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $doseEvents = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($doseEvents);
        self::assertNotEmpty($doseEvents);
        $firstDoseEvent = $doseEvents[0];
        $doseEventId = $firstDoseEvent['id'];
        $doseEventScheduledAt = $firstDoseEvent['scheduledAt'];

        // 9. Track Dose (mark taken)
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/dose-events',
            [],
            [],
            $headers,
            json_encode([
                'id' => $doseEventId,
                'scheduleId' => $scheduleId,
                'scheduledAt' => $doseEventScheduledAt,
                'status' => 'taken',
                'takenAt' => '2026-07-01T08:05:00Z',
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $trackedDose = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('taken', $trackedDose['status']);

        // 10. Notification - Register Device Token
        $fcmToken = 'fake-fcm-token-' . bin2hex(random_bytes(8));
        $client->request(
            'POST',
            '/api/v1/devices',
            [],
            [],
            $headers,
            json_encode([
                'fcmToken' => $fcmToken,
                'platform' => 'android',
                'locale' => 'en-US',
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $deviceData = json_decode($client->getResponse()->getContent(), true);
        self::assertSame($fcmToken, $deviceData['token']);

        // 11. Notification - Update Preferences
        $client->request(
            'PATCH',
            '/api/v1/notifications/preferences',
            [],
            [],
            $headers,
            json_encode([
                'doseRemindersEnabled' => true,
                'missedDoseNudgesEnabled' => false,
                'refillAlertsEnabled' => true,
                'weeklyStreakSummariesEnabled' => true,
            ])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $prefs = json_decode($client->getResponse()->getContent(), true);
        self::assertTrue($prefs['doseRemindersEnabled']);
        self::assertFalse($prefs['missedDoseNudgesEnabled']);

        // 12. CalendarIntegration - Connect Google and Microsoft
        $client->request(
            'POST',
            '/api/v1/calendars/google/connect',
            [],
            [],
            $headers,
            json_encode([
                'profileId' => $profileId,
                'refreshToken' => 'mock-google-refresh-token',
            ])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request(
            'POST',
            '/api/v1/calendars/microsoft/connect',
            [],
            [],
            $headers,
            json_encode([
                'profileId' => $profileId,
                'refreshToken' => 'mock-microsoft-refresh-token',
            ])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 13. CalendarIntegration - Force Resync
        $client->request(
            'POST',
            '/api/v1/calendars/sync?profileId=' . $profileId,
            [],
            [],
            $headers
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 14. Profile Sync Endpoint
        $client->request('GET', '/api/v1/profiles/' . $profileId . '/sync?since=2026-07-01T00:00:00Z', [], [], $headers);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $syncData = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($syncData);
        self::assertArrayHasKey('medications', $syncData);
        self::assertArrayHasKey('schedules', $syncData);
        self::assertArrayHasKey('doseEvents', $syncData);
        self::assertArrayHasKey('tombstones', $syncData);

        // 15. Tear down / Deregistrations & Deletions
        // Deregister Device
        $client->request('DELETE', '/api/v1/devices/' . $fcmToken, [], [], $headers);
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        // Disconnect Calendar
        $client->request('DELETE', '/api/v1/calendars/google?profileId=' . $profileId, [], [], $headers);
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $client->request('DELETE', '/api/v1/calendars/microsoft?profileId=' . $profileId, [], [], $headers);
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        // Delete Schedule
        $client->request('DELETE', '/api/v1/profiles/' . $profileId . '/schedules/' . $scheduleId, [], [], $headers);
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        // Delete Medication
        $client->request('DELETE', '/api/v1/profiles/' . $profileId . '/medications/' . $medicationId, [], [], $headers);
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        // Delete Profile
        $client->request('DELETE', '/api/v1/profiles/' . $profileId, [], [], $headers);
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }
}
