<?php

declare(strict_types=1);

namespace App\Tests\Shared\UI\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class FeaturesIntegrationTest extends WebTestCase
{
    /**
     * @param array<string, mixed> $payload
     */
    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(string|false $content): array
    {
        if ($content === false) {
            throw new \RuntimeException('Test response did not contain valid content.');
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException('Test response did not contain a JSON object or array.');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeListResponse(string|false $content): array
    {
        $data = $this->decodeResponse($content);

        /** @var list<array<string, mixed>> $data */
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function stringValue(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value)) {
            throw new \RuntimeException(sprintf('Test response field "%s" is not a string.', $key));
        }

        return $value;
    }

    public function testCompleteUserJourney(): void
    {
        $client = self::createClient();
        $scheduleStart = (new \DateTimeImmutable('+1 day'))->setTime(0, 0);
        $scheduleEnd = $scheduleStart->modify('+14 days');
        $doseRangeEnd = $scheduleStart->modify('+4 days');
        $scheduleStartIso = $scheduleStart->format(\DateTimeInterface::ATOM);
        $scheduleEndIso = $scheduleEnd->format(\DateTimeInterface::ATOM);
        $doseRangeEndIso = $doseRangeEnd->format(\DateTimeInterface::ATOM);

        // 0. Identity - Google Auth, Microsoft Auth, Get Me, and Refresh Token
        $client->request(
            'POST',
            '/api/v1/auth/google',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encode(['idToken' => 'valid-john@example.com'])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $authData = $this->decodeResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('token', $authData);
        self::assertArrayHasKey('refreshToken', $authData);
        $authToken = $this->stringValue($authData, 'token');
        $refreshToken = $this->stringValue($authData, 'refreshToken');

        // Headers generated from real authenticated JWT token
        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $authToken,
            'CONTENT_TYPE' => 'application/json',
        ];

        // Microsoft Auth
        $client->request(
            'POST',
            '/api/v1/auth/microsoft',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encode(['idToken' => 'valid-msjohn@example.com'])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $msAuthData = $this->decodeResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('token', $msAuthData);

        // Get Me
        $client->request('GET', '/api/v1/me', [], [], $headers);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $meData = $this->decodeResponse($client->getResponse()->getContent());
        self::assertSame('john@example.com', $meData['email']);

        // Refresh Token
        $client->request(
            'POST',
            '/api/v1/auth/refresh',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encode(['refreshToken' => $refreshToken])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $refreshedAuth = $this->decodeResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('token', $refreshedAuth);

        // 1. Create Profile

        $client->request(
            'POST',
            '/api/v1/profiles',
            [],
            [],
            $headers,
            $this->encode([
                'name' => 'John Doe',
                'birthDate' => '1990-01-01T00:00:00Z',
                'gender' => 'male',
                'photoUrl' => 'https://example.com/photo.jpg',
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $profileData = $this->decodeResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('id', $profileData);
        $profileId = $this->stringValue($profileData, 'id');
        self::assertSame('John Doe', $profileData['name']);

        // 2. Get Profiles
        $client->request('GET', '/api/v1/profiles', [], [], $headers);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $profilesList = $this->decodeListResponse($client->getResponse()->getContent());
        self::assertNotEmpty($profilesList);

        // 3. Update Profile
        $client->request(
            'PATCH',
            '/api/v1/profiles/' . $profileId,
            [],
            [],
            $headers,
            $this->encode([
                'name' => 'John Doe Updated',
                'birthDate' => '1990-01-01T00:00:00Z',
                'gender' => 'male',
                'photoUrl' => 'https://example.com/photo2.jpg',
            ])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $updatedProfile = $this->decodeResponse($client->getResponse()->getContent());
        self::assertSame('John Doe Updated', $updatedProfile['name']);

        // 4. Create Medication
        $medicationClientId = 'med-client-uuid-' . bin2hex(random_bytes(4));
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/medications',
            [],
            [],
            $headers,
            $this->encode([
                'name' => 'Ibuprofen',
                'dosage' => '400mg',
                'instructions' => 'Take with food',
                'photoUrl' => 'https://example.com/ibuprofen.jpg',
                'clientId' => $medicationClientId,
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $medData = $this->decodeResponse($client->getResponse()->getContent());
        $medicationId = $this->stringValue($medData, 'id');
        self::assertSame('Ibuprofen', $medData['name']);
        self::assertSame($medicationClientId, $medData['clientId']);

        // Test Idempotent creation of Medication
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/medications',
            [],
            [],
            $headers,
            $this->encode([
                'name' => 'Ibuprofen',
                'dosage' => '400mg',
                'instructions' => 'Take with food',
                'photoUrl' => 'https://example.com/ibuprofen.jpg',
                'clientId' => $medicationClientId,
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $medDataDup = $this->decodeResponse($client->getResponse()->getContent());
        self::assertSame($medicationId, $medDataDup['id']);

        // 5. Get Medications
        $client->request('GET', '/api/v1/profiles/' . $profileId . '/medications', [], [], $headers);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $medsList = $this->decodeListResponse($client->getResponse()->getContent());
        self::assertNotEmpty($medsList);

        // 5b. Update Medication
        $client->request(
            'PATCH',
            '/api/v1/profiles/' . $profileId . '/medications/' . $medicationId,
            [],
            [],
            $headers,
            $this->encode([
                'name' => 'Ibuprofen 600mg',
                'dosage' => '600mg',
                'instructions' => 'Take after meals',
                'photoUrl' => 'https://example.com/ibuprofen-600.jpg',
            ])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $updatedMed = $this->decodeResponse($client->getResponse()->getContent());
        self::assertSame('Ibuprofen 600mg', $updatedMed['name']);
        self::assertSame('600mg', $updatedMed['dosage']);


        // 6. Create Daily Schedule
        $scheduleClientId = 'sch-client-uuid-' . bin2hex(random_bytes(4));
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/schedules',
            [],
            [],
            $headers,
            $this->encode([
                'medicationId' => $medicationId,
                'type' => 'daily',
                'startDate' => $scheduleStartIso,
                'endDate' => $scheduleEndIso,
                'timesOfDay' => [
                    ['hour' => 8, 'minute' => 0],
                    ['hour' => 20, 'minute' => 0],
                ],
                'clientId' => $scheduleClientId,
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $schData = $this->decodeResponse($client->getResponse()->getContent());
        $scheduleId = $this->stringValue($schData, 'id');
        self::assertSame($scheduleClientId, $schData['clientId']);

        // Test Idempotent creation of Schedule
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/schedules',
            [],
            [],
            $headers,
            $this->encode([
                'medicationId' => $medicationId,
                'type' => 'daily',
                'startDate' => $scheduleStartIso,
                'endDate' => $scheduleEndIso,
                'timesOfDay' => [
                    ['hour' => 8, 'minute' => 0],
                    ['hour' => 20, 'minute' => 0],
                ],
                'clientId' => $scheduleClientId,
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $schDataDup = $this->decodeResponse($client->getResponse()->getContent());
        self::assertSame($scheduleId, $schDataDup['id']);

        // 7. Get Schedules
        $client->request('GET', '/api/v1/profiles/' . $profileId . '/schedules', [], [], $headers);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $schedulesList = $this->decodeListResponse($client->getResponse()->getContent());
        self::assertNotEmpty($schedulesList);

        // 8. Get Dose Events (pre-expanded from schedule creation)
        $client->request(
            'GET',
            '/api/v1/profiles/' . $profileId . '/dose-events?from=' . rawurlencode($scheduleStartIso) . '&to=' . rawurlencode($doseRangeEndIso),
            [],
            [],
            $headers
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $doseEvents = $this->decodeListResponse($client->getResponse()->getContent());
        self::assertNotEmpty($doseEvents);
        $firstDoseEvent = $doseEvents[0];
        $doseEventId = $this->stringValue($firstDoseEvent, 'id');
        $doseEventScheduledAt = $this->stringValue($firstDoseEvent, 'scheduledAt');

        // 9. Track Dose (mark taken)
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/dose-events',
            [],
            [],
            $headers,
            $this->encode([
                'id' => $doseEventId,
                'scheduleId' => $scheduleId,
                'scheduledAt' => $doseEventScheduledAt,
                'status' => 'taken',
                'takenAt' => (new \DateTimeImmutable($doseEventScheduledAt))->modify('+5 minutes')->format(\DateTimeInterface::ATOM),
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $trackedDose = $this->decodeResponse($client->getResponse()->getContent());
        self::assertSame('taken', $trackedDose['status']);

        // 10. Notification - Register Device Token
        $fcmToken = 'fake-fcm-token-' . bin2hex(random_bytes(8));
        $client->request(
            'POST',
            '/api/v1/devices',
            [],
            [],
            $headers,
            $this->encode([
                'fcmToken' => $fcmToken,
                'platform' => 'android',
                'locale' => 'en-US',
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $deviceData = $this->decodeResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('id', $deviceData);
        self::assertArrayNotHasKey('token', $deviceData);
        $deviceId = $this->stringValue($deviceData, 'id');

        // 11. Notification - Update Preferences
        $client->request(
            'PATCH',
            '/api/v1/notifications/preferences',
            [],
            [],
            $headers,
            $this->encode([
                'doseRemindersEnabled' => true,
                'missedDoseNudgesEnabled' => false,
                'refillAlertsEnabled' => true,
                'weeklyStreakSummariesEnabled' => true,
            ])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $prefs = $this->decodeResponse($client->getResponse()->getContent());
        self::assertTrue($prefs['doseRemindersEnabled']);
        self::assertFalse($prefs['missedDoseNudgesEnabled']);

        // Partial update: update only doseRemindersEnabled, ensuring missedDoseNudgesEnabled remains false.
        $client->request(
            'PATCH',
            '/api/v1/notifications/preferences',
            [],
            [],
            $headers,
            $this->encode([
                'doseRemindersEnabled' => false,
            ])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $partialPrefs = $this->decodeResponse($client->getResponse()->getContent());
        self::assertFalse($partialPrefs['doseRemindersEnabled']);
        self::assertFalse($partialPrefs['missedDoseNudgesEnabled']);

        // Test push rejects oversized input at the HTTP boundary.
        $client->request(
            'POST',
            '/api/v1/notifications/test-push',
            [],
            [],
            $headers,
            $this->encode([
                'title' => str_repeat('x', 201),
                'body' => 'Delivery check',
            ])
        );
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 12. CalendarIntegration - Connect Google and Microsoft
        $googleCodeVerifier = str_repeat('g', 43);
        $googleCodeChallenge = rtrim(strtr(base64_encode(hash('sha256', $googleCodeVerifier, true)), '+/', '-_'), '=');
        $client->request(
            'POST',
            '/api/v1/calendars/google/authorize',
            [],
            [],
            $headers,
            $this->encode([
                'profileId' => $profileId,
                'codeChallenge' => $googleCodeChallenge,
            ])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $googleAuthorization = $this->decodeResponse($client->getResponse()->getContent());

        $client->request(
            'POST',
            '/api/v1/calendars/google/connect',
            [],
            [],
            $headers,
            $this->encode([
                'profileId' => $profileId,
                'code' => 'mock-google-code',
                'state' => $this->stringValue($googleAuthorization, 'state'),
                'codeVerifier' => $googleCodeVerifier,
            ])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // Authorization state is single-use.
        $client->request(
            'POST',
            '/api/v1/calendars/google/connect',
            [],
            [],
            $headers,
            $this->encode([
                'profileId' => $profileId,
                'code' => 'mock-google-code',
                'state' => $this->stringValue($googleAuthorization, 'state'),
                'codeVerifier' => $googleCodeVerifier,
            ])
        );
        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());

        $microsoftCodeVerifier = str_repeat('m', 43);
        $microsoftCodeChallenge = rtrim(strtr(base64_encode(hash('sha256', $microsoftCodeVerifier, true)), '+/', '-_'), '=');
        $client->request(
            'POST',
            '/api/v1/calendars/microsoft/authorize',
            [],
            [],
            $headers,
            $this->encode([
                'profileId' => $profileId,
                'codeChallenge' => $microsoftCodeChallenge,
            ])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $microsoftAuthorization = $this->decodeResponse($client->getResponse()->getContent());

        $client->request(
            'POST',
            '/api/v1/calendars/microsoft/connect',
            [],
            [],
            $headers,
            $this->encode([
                'profileId' => $profileId,
                'code' => 'mock-microsoft-code',
                'state' => $this->stringValue($microsoftAuthorization, 'state'),
                'codeVerifier' => $microsoftCodeVerifier,
            ])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/v1/calendars?profileId=' . $profileId, [], [], $headers);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $calendarConnections = $this->decodeListResponse($client->getResponse()->getContent());
        self::assertCount(2, $calendarConnections);
        self::assertSame('active', $calendarConnections[0]['status']);

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
        $client->request('GET', '/api/v1/profiles/' . $profileId . '/sync?since=' . rawurlencode($scheduleStartIso), [], [], $headers);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $syncData = $this->decodeResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('medications', $syncData);
        self::assertArrayHasKey('schedules', $syncData);
        self::assertArrayHasKey('doseEvents', $syncData);
        self::assertArrayHasKey('tombstones', $syncData);

        // 15. Tear down / Deregistrations & Deletions
        // Deregister Device
        $client->request('DELETE', '/api/v1/devices/' . $deviceId, [], [], $headers);
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
