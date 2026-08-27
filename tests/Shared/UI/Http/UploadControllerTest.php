<?php

declare(strict_types=1);

namespace App\Tests\Shared\UI\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

final class UploadControllerTest extends WebTestCase
{
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

    public function testUploadWithoutFileReturnsValidationFailure(): void
    {
        $client = self::createClient();

        // 1. Authenticate
        $client->request(
            'POST',
            '/api/v1/auth/google',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['idToken' => 'valid-uploaduser-' . bin2hex(random_bytes(4)) . '@example.com'], JSON_THROW_ON_ERROR)
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $authData = $this->decodeResponse($client->getResponse()->getContent());
        $token = $this->stringValue($authData, 'token');

        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ];

        // 2. Upload without file
        $client->request('POST', '/api/v1/uploads', [], [], $headers);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        // 3. Upload with valid image
        $tempFile = tempnam(sys_get_temp_dir(), 'upload_test_');
        file_put_contents($tempFile, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00`\x00`\x00\x00\xFF\xDB\x00C");

        $uploadedFile = new UploadedFile($tempFile, 'photo.jpg', 'image/jpeg', null, true);
        $client->request('POST', '/api/v1/uploads', [], ['file' => $uploadedFile], $headers);
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $res = $this->decodeResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('url', $res);
        self::assertSame('image/jpeg', $res['mimeType']);

        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
}
