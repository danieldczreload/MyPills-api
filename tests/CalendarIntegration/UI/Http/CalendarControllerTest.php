<?php

declare(strict_types=1);

namespace App\Tests\CalendarIntegration\UI\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class CalendarControllerTest extends WebTestCase
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

    public function testCalendarControllerEndpoints(): void
    {
        $client = self::createClient();

        // 1. Authenticate
        $client->request(
            'POST',
            '/api/v1/auth/google',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encode(['idToken' => 'valid-caluser-' . bin2hex(random_bytes(4)) . '@example.com'])
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
                'name' => 'Calendar User',
                'birthDate' => '1995-05-05T00:00:00Z',
                'gender' => 'male',
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $profile = $this->decodeResponse($client->getResponse()->getContent());
        $profileId = $this->stringValue($profile, 'id');

        // 3. GET /calendars without profileId returns 422
        $client->request('GET', '/api/v1/calendars', [], [], $headers);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 4. GET /calendars with profileId
        $client->request('GET', '/api/v1/calendars?profileId=' . $profileId, [], [], $headers);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 5. POST /calendars/google/authorize with invalid JSON
        $client->request(
            'POST',
            '/api/v1/calendars/google/authorize',
            [],
            [],
            $headers,
            'invalid-json'
        );
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 6. POST /calendars/google/authorize with valid payload
        $client->request(
            'POST',
            '/api/v1/calendars/google/authorize',
            [],
            [],
            $headers,
            $this->encode([
                'profileId' => $profileId,
                'codeChallenge' => str_repeat('c', 43),
            ])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $authRes = $this->decodeResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('authorizationUrl', $authRes);

        // 7. POST /calendars/google/connect with legacy refreshToken fails
        $client->request(
            'POST',
            '/api/v1/calendars/google/connect',
            [],
            [],
            $headers,
            $this->encode([
                'profileId' => $profileId,
                'refreshToken' => 'some-refresh-token',
            ])
        );
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 8. DELETE /calendars/google without profileId returns 422
        $client->request('DELETE', '/api/v1/calendars/google', [], [], $headers);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 9. POST /calendars/sync with profileId
        $client->request('POST', '/api/v1/calendars/sync?profileId=' . $profileId, [], [], $headers);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 10. POST /calendars/sync without profileId (syncs all profiles)
        $client->request('POST', '/api/v1/calendars/sync', [], [], $headers);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }
}
