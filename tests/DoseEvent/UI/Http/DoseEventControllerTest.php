<?php

declare(strict_types=1);

namespace App\Tests\DoseEvent\UI\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class DoseEventControllerTest extends WebTestCase
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

    public function testDoseEventValidationAndLifecycle(): void
    {
        $client = self::createClient();

        // 1. Authenticate
        $client->request(
            'POST',
            '/api/v1/auth/google',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encode(['idToken' => 'valid-dosectrl-' . bin2hex(random_bytes(4)) . '@example.com'])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $authData = $this->decodeResponse($client->getResponse()->getContent());
        $token = $this->stringValue($authData, 'token');

        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
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
                'name' => 'Dose Patient',
                'birthDate' => '1990-01-01T00:00:00Z',
                'gender' => 'female',
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $profile = $this->decodeResponse($client->getResponse()->getContent());
        $profileId = $this->stringValue($profile, 'id');

        // 3. Create Medication
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/medications',
            [],
            [],
            $headers,
            $this->encode([
                'name' => 'Vitamin C',
                'dosage' => '1000mg',
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $med = $this->decodeResponse($client->getResponse()->getContent());
        $medicationId = $this->stringValue($med, 'id');

        // 4. Create Schedule
        $now = new \DateTimeImmutable();
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/schedules',
            [],
            [],
            $headers,
            $this->encode([
                'medicationId' => $medicationId,
                'type' => 'daily',
                'startDate' => $now->format(\DateTimeInterface::ATOM),
                'timesOfDay' => [['hour' => 9, 'minute' => 0]],
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $sched = $this->decodeResponse($client->getResponse()->getContent());
        $scheduleId = $this->stringValue($sched, 'id');

        // 5. POST /dose-events with invalid scheduledAt
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/dose-events',
            [],
            [],
            $headers,
            $this->encode([
                'scheduleId' => $scheduleId,
                'scheduledAt' => 'invalid-date',
                'status' => 'taken',
            ])
        );
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 6. POST /dose-events with invalid takenAt
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/dose-events',
            [],
            [],
            $headers,
            $this->encode([
                'scheduleId' => $scheduleId,
                'scheduledAt' => $now->format(\DateTimeInterface::ATOM),
                'takenAt' => 'invalid-taken-at',
                'status' => 'taken',
            ])
        );
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 7. GET /dose-events without from/to
        $client->request('GET', '/api/v1/profiles/' . $profileId . '/dose-events', [], [], $headers);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 8. GET /dose-events with invalid from/to
        $client->request('GET', '/api/v1/profiles/' . $profileId . '/dose-events?from=bad&to=bad', [], [], $headers);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 9. POST /dose-events valid create
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/dose-events',
            [],
            [],
            $headers,
            $this->encode([
                'scheduleId' => $scheduleId,
                'scheduledAt' => $now->format(\DateTimeInterface::ATOM),
                'status' => 'taken',
                'takenAt' => $now->format(\DateTimeInterface::ATOM),
                'clientId' => 'dose-client-uuid-1',
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
    }
}
