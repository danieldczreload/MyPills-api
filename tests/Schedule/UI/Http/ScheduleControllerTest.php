<?php

declare(strict_types=1);

namespace App\Tests\Schedule\UI\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ScheduleControllerTest extends WebTestCase
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

    public function testScheduleValidationAndLifecycle(): void
    {
        $client = self::createClient();

        // 1. Authenticate
        $client->request(
            'POST',
            '/api/v1/auth/google',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encode(['idToken' => 'valid-schedctrl-' . bin2hex(random_bytes(4)) . '@example.com'])
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
                'name' => 'Schedule Patient',
                'birthDate' => '1990-01-01T00:00:00Z',
                'gender' => 'male',
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
                'name' => 'Amoxicillin',
                'dosage' => '500mg',
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $med = $this->decodeResponse($client->getResponse()->getContent());
        $medicationId = $this->stringValue($med, 'id');

        // 4. Invalid startDate
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/schedules',
            [],
            [],
            $headers,
            $this->encode([
                'medicationId' => $medicationId,
                'type' => 'daily',
                'startDate' => 'not-a-date',
            ])
        );
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 5. Invalid endDate
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/schedules',
            [],
            [],
            $headers,
            $this->encode([
                'medicationId' => $medicationId,
                'type' => 'daily',
                'startDate' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'endDate' => 'bad-end-date',
            ])
        );
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 6. Valid Daily Interval schedule
        $now = new \DateTimeImmutable();
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/schedules',
            [],
            [],
            $headers,
            $this->encode([
                'medicationId' => $medicationId,
                'type' => 'daily_interval',
                'startDate' => $now->format(\DateTimeInterface::ATOM),
                'everyHours' => 8,
                'startAt' => ['hour' => 8, 'minute' => 0],
                'endAt' => ['hour' => 22, 'minute' => 0],
                'clientId' => 'sched-cli-int-1',
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $schedData = $this->decodeResponse($client->getResponse()->getContent());
        $schedId = $this->stringValue($schedData, 'id');

        // 7. Delete schedule
        $client->request('DELETE', '/api/v1/profiles/' . $profileId . '/schedules/' . $schedId, [], [], $headers);
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }
}
