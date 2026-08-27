<?php

declare(strict_types=1);

namespace App\Tests\Profile\UI\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ProfileControllerTest extends WebTestCase
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

    public function testProfileControllerValidationAndSync(): void
    {
        $client = self::createClient();

        // 1. Authenticate
        $client->request(
            'POST',
            '/api/v1/auth/google',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encode(['idToken' => 'valid-profctrl-' . bin2hex(random_bytes(4)) . '@example.com'])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $authData = $this->decodeResponse($client->getResponse()->getContent());
        $token = $this->stringValue($authData, 'token');

        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ];

        // 2. POST /profiles with invalid birthDate
        $client->request(
            'POST',
            '/api/v1/profiles',
            [],
            [],
            $headers,
            $this->encode([
                'name' => 'Bad Date',
                'birthDate' => 'invalid-date',
                'gender' => 'male',
            ])
        );
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 3. POST /profiles valid
        $client->request(
            'POST',
            '/api/v1/profiles',
            [],
            [],
            $headers,
            $this->encode([
                'name' => 'Profile One',
                'birthDate' => '1995-05-15T00:00:00Z',
                'gender' => 'female',
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $profile = $this->decodeResponse($client->getResponse()->getContent());
        $profileId = $this->stringValue($profile, 'id');

        // 4. PATCH /profiles/{id} with invalid birthDate
        $client->request(
            'PATCH',
            '/api/v1/profiles/' . $profileId,
            [],
            [],
            $headers,
            $this->encode([
                'name' => 'Updated Name',
                'birthDate' => 'bad-date',
                'gender' => 'female',
            ])
        );
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 5. GET /profiles/{id}/sync without since parameter
        $client->request('GET', '/api/v1/profiles/' . $profileId . '/sync', [], [], $headers);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 6. GET /profiles/{id}/sync with invalid since parameter
        $client->request('GET', '/api/v1/profiles/' . $profileId . '/sync?since=invalid-date', [], [], $headers);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 7. GET /profiles/{id}/sync with valid since parameter
        $client->request('GET', '/api/v1/profiles/' . $profileId . '/sync?since=2026-01-01T00:00:00Z', [], [], $headers);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 8. DELETE /profiles/{id}
        $client->request('DELETE', '/api/v1/profiles/' . $profileId, [], [], $headers);
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }
}
