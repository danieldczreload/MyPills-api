<?php

declare(strict_types=1);

namespace App\Tests\Notification\UI\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class NotificationCancellationEndpointTest extends WebTestCase
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
            throw new \RuntimeException(sprintf('Field "%s" is not string', $key));
        }

        return $value;
    }

    public function testCancelIndividualAndRecurringNotificationsEndpoints(): void
    {
        $client = self::createClient();

        // 1. Authenticate
        $client->request(
            'POST',
            '/api/v1/auth/google',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encode(['idToken' => 'valid-cancellation-user@example.com'])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $authData = $this->decodeResponse($client->getResponse()->getContent());
        $authToken = $this->stringValue($authData, 'token');

        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $authToken,
            'CONTENT_TYPE' => 'application/json',
        ];

        // 2. Create Profile
        $client->request(
            'POST',
            '/api/v1/profiles',
            [],
            [],
            $headers,
            $this->encode([
                'name' => 'Cancel Test Patient',
                'birthDate' => '1992-05-10T00:00:00Z',
                'gender' => 'male',
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $profileData = $this->decodeResponse($client->getResponse()->getContent());
        $profileId = $this->stringValue($profileData, 'id');

        // 3. Register device token
        $client->request(
            'POST',
            '/api/v1/devices',
            [],
            [],
            $headers,
            $this->encode([
                'fcmToken' => 'fcm-token-' . bin2hex(random_bytes(6)),
                'platform' => 'android',
                'locale' => 'es-MX',
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        // 4. Connect Google Calendar
        $googleVerifier = str_repeat('g', 43);
        $googleChallenge = rtrim(strtr(base64_encode(hash('sha256', $googleVerifier, true)), '+/', '-_'), '=');
        $client->request(
            'POST',
            '/api/v1/calendars/google/authorize',
            [],
            [],
            $headers,
            $this->encode(['profileId' => $profileId, 'codeChallenge' => $googleChallenge])
        );
        $googleAuth = $this->decodeResponse($client->getResponse()->getContent());

        $client->request(
            'POST',
            '/api/v1/calendars/google/connect',
            [],
            [],
            $headers,
            $this->encode([
                'profileId' => $profileId,
                'code' => 'mock-code',
                'state' => $this->stringValue($googleAuth, 'state'),
                'codeVerifier' => $googleVerifier,
            ])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 5. Create Medication & Schedule
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/medications',
            [],
            [],
            $headers,
            $this->encode([
                'name' => 'Omeprazol',
                'dosage' => '20mg',
                'instructions' => 'En ayunas',
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $medData = $this->decodeResponse($client->getResponse()->getContent());
        $medicationId = $this->stringValue($medData, 'id');

        $scheduleStart = (new \DateTimeImmutable('+1 day'))->setTime(8, 0);
        $scheduleEnd = $scheduleStart->modify('+7 days');

        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/schedules',
            [],
            [],
            $headers,
            $this->encode([
                'medicationId' => $medicationId,
                'type' => 'daily',
                'startDate' => $scheduleStart->format(\DateTimeInterface::ATOM),
                'endDate' => $scheduleEnd->format(\DateTimeInterface::ATOM),
                'timesOfDay' => [['hour' => 8, 'minute' => 0]],
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $schedData = $this->decodeResponse($client->getResponse()->getContent());
        $scheduleId = $this->stringValue($schedData, 'id');

        // Sync calendar to create mappings
        $client->request('POST', '/api/v1/calendars/sync?profileId=' . $profileId, [], [], $headers);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 6. Get Dose Events
        $client->request(
            'GET',
            '/api/v1/profiles/' . $profileId . '/dose-events?from=' . rawurlencode($scheduleStart->format(\DateTimeInterface::ATOM)) . '&to=' . rawurlencode($scheduleEnd->format(\DateTimeInterface::ATOM)),
            [],
            [],
            $headers
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $doseList = $this->decodeListResponse($client->getResponse()->getContent());
        self::assertNotEmpty($doseList);
        $firstDoseId = $this->stringValue($doseList[0], 'id');

        // 7. Test Cancel Individual Notification via POST /api/v1/profiles/{id}/notifications/{doseEventId}/cancel
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/notifications/' . $firstDoseId . '/cancel',
            [],
            [],
            $headers,
            $this->encode([
                'cancelPush' => true,
                'cancelCalendar' => true,
            ])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $cancelRes = $this->decodeResponse($client->getResponse()->getContent());
        self::assertSame($firstDoseId, $cancelRes['doseEventId']);
        self::assertSame('skipped', $cancelRes['status']);
        self::assertTrue($cancelRes['pushCancelled']);
        self::assertGreaterThanOrEqual(1, $cancelRes['calendarEventsDeleted']);

        // 8. Test Cancel Individual Notification via DoseEvent alias route
        if (isset($doseList[1])) {
            $secondDoseId = $this->stringValue($doseList[1], 'id');
            $client->request(
                'POST',
                '/api/v1/profiles/' . $profileId . '/dose-events/' . $secondDoseId . '/cancel',
                [],
                [],
                $headers
            );
            self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
            $cancelRes2 = $this->decodeResponse($client->getResponse()->getContent());
            self::assertSame($secondDoseId, $cancelRes2['doseEventId']);
            self::assertSame('skipped', $cancelRes2['status']);
        }

        // 9. Test Cancel Recurring Notifications via /api/v1/profiles/{id}/notifications/cancel-recurring
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/notifications/cancel-recurring',
            [],
            [],
            $headers,
            $this->encode([
                'scheduleId' => $scheduleId,
                'cancelPush' => true,
                'cancelCalendar' => true,
            ])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $recurringRes = $this->decodeResponse($client->getResponse()->getContent());
        self::assertSame($profileId, $recurringRes['profileId']);
        self::assertSame($scheduleId, $recurringRes['scheduleId']);
        self::assertTrue($recurringRes['pushCancelled']);

        // 10. Test Cancel Recurring Notifications via Schedule-specific route
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/schedules/' . $scheduleId . '/cancel-recurring',
            [],
            [],
            $headers
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $schedCancelRes = $this->decodeResponse($client->getResponse()->getContent());
        self::assertSame($scheduleId, $schedCancelRes['scheduleId']);
    }
}
