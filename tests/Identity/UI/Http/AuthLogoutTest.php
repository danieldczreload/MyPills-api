<?php

declare(strict_types=1);

namespace App\Tests\Identity\UI\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class AuthLogoutTest extends WebTestCase
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

    public function testLogoutRevokesRefreshToken(): void
    {
        $client = self::createClient();
        $client->request(
            'POST',
            '/api/v1/auth/google',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encode(['idToken' => 'valid-logout-' . bin2hex(random_bytes(4)) . '@example.com'])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $auth = $this->decodeResponse($client->getResponse()->getContent());
        $refreshToken = $this->stringValue($auth, 'refreshToken');
        $accessToken = $this->stringValue($auth, 'token');

        $client->request(
            'POST',
            '/api/v1/auth/logout',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $accessToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            $this->encode(['refreshToken' => $refreshToken])
        );
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $client->request(
            'POST',
            '/api/v1/auth/refresh',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encode(['refreshToken' => $refreshToken])
        );
        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testLogoutIsIdempotentWithoutCredentials(): void
    {
        $client = self::createClient();
        $client->request('POST', '/api/v1/auth/logout');
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }
}
