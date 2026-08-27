<?php

declare(strict_types=1);

namespace App\Tests\Notification\UI\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class NotificationControllerEndpointsTest extends WebTestCase
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
            throw new \RuntimeException('Response is empty.');
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException('Expected JSON object.');
        }

        /** @var array<string, mixed> $data */
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

    public function testNotificationEndpointsLifecycle(): void
    {
        $client = self::createClient();

        // 1. Authenticate
        $client->request(
            'POST',
            '/api/v1/auth/google',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encode(['idToken' => 'valid-notifuser-' . bin2hex(random_bytes(4)) . '@example.com'])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $authData = $this->decodeResponse($client->getResponse()->getContent());
        $token = $this->stringValue($authData, 'token');

        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ];

        // 2. GET /notifications/preferences
        $client->request('GET', '/api/v1/notifications/preferences', [], [], $headers);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $prefs = $this->decodeResponse($client->getResponse()->getContent());
        self::assertTrue($prefs['doseRemindersEnabled']);

        // 3. PATCH /notifications/preferences with invalid JSON
        $client->request('PATCH', '/api/v1/notifications/preferences', [], [], $headers, 'invalid-json');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 4. PATCH /notifications/preferences with non-object JSON
        $client->request('PATCH', '/api/v1/notifications/preferences', [], [], $headers, '"not an object"');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 5. PATCH /notifications/preferences with valid updates
        $client->request(
            'PATCH',
            '/api/v1/notifications/preferences',
            [],
            [],
            $headers,
            $this->encode([
                'doseRemindersEnabled' => false,
                'missedDoseNudgesEnabled' => true,
                'reminderMinutesBefore' => 15,
            ])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $updatedPrefs = $this->decodeResponse($client->getResponse()->getContent());
        self::assertFalse($updatedPrefs['doseRemindersEnabled']);
        self::assertSame(15, $updatedPrefs['reminderMinutesBefore']);

        // 6. POST /devices with invalid JSON
        $client->request('POST', '/api/v1/devices', [], [], $headers, 'invalid-json');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 7. POST /devices with non-object JSON
        $client->request('POST', '/api/v1/devices', [], [], $headers, '12345');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 8. POST /devices valid
        $client->request(
            'POST',
            '/api/v1/devices',
            [],
            [],
            $headers,
            $this->encode([
                'fcmToken' => 'fcm-device-1234567890',
                'platform' => 'android',
                'locale' => 'en',
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $deviceData = $this->decodeResponse($client->getResponse()->getContent());
        $deviceId = $this->stringValue($deviceData, 'id');

        // 9. POST /notifications/test-push with invalid JSON
        $client->request('POST', '/api/v1/notifications/test-push', [], [], $headers, 'invalid-json');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 10. POST /notifications/test-push with valid payload
        $client->request(
            'POST',
            '/api/v1/notifications/test-push',
            [],
            [],
            $headers,
            $this->encode([
                'title' => 'Test Notification',
                'body' => 'This is a test push',
                'data' => ['key' => 'value'],
            ])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 11. DELETE /devices/{deviceId}
        $client->request('DELETE', '/api/v1/devices/' . $deviceId, [], [], $headers);
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        // 12. Create Profile to test invalid JSON on cancellation routes
        $client->request(
            'POST',
            '/api/v1/profiles',
            [],
            [],
            $headers,
            $this->encode([
                'name' => 'Notification User',
                'birthDate' => '1990-01-01T00:00:00Z',
                'gender' => 'other',
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $profile = $this->decodeResponse($client->getResponse()->getContent());
        $profileId = $this->stringValue($profile, 'id');

        // 13. POST /profiles/{id}/notifications/{doseEventId}/cancel with invalid JSON
        $client->request('POST', '/api/v1/profiles/' . $profileId . '/notifications/fake-dose/cancel', [], [], $headers, 'invalid-json');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 14. POST /profiles/{id}/notifications/cancel-recurring with invalid JSON
        $client->request('POST', '/api/v1/profiles/' . $profileId . '/notifications/cancel-recurring', [], [], $headers, 'invalid-json');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 15. POST /profiles/{id}/schedules/{sid}/cancel-recurring with invalid JSON
        $client->request('POST', '/api/v1/profiles/' . $profileId . '/schedules/fake-sched/cancel-recurring', [], [], $headers, 'invalid-json');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }
}
