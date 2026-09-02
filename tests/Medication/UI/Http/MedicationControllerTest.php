<?php

declare(strict_types=1);

namespace App\Tests\Medication\UI\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class MedicationControllerTest extends WebTestCase
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

    public function testMedicationControllerLifecycle(): void
    {
        $client = self::createClient();

        // 1. Authenticate
        $client->request(
            'POST',
            '/api/v1/auth/google',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encode(['idToken' => 'valid-medctrl-' . bin2hex(random_bytes(4)) . '@example.com'])
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
                'name' => 'Medication User',
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
                'name' => 'Lisinopril',
                'instructions' => 'In morning',
                'form' => 'tablet',
                'colorToken' => 'emerald',
                'clientId' => 'med-cli-uuid-1',
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $medData = $this->decodeResponse($client->getResponse()->getContent());
        $medId = $this->stringValue($medData, 'id');
        self::assertSame('Lisinopril', $medData['name']);

        // 4. List Medications
        $client->request('GET', '/api/v1/profiles/' . $profileId . '/medications', [], [], $headers);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $medList = $this->decodeListResponse($client->getResponse()->getContent());
        self::assertCount(1, $medList);

        // 5. Update Medication
        $client->request(
            'PATCH',
            '/api/v1/profiles/' . $profileId . '/medications/' . $medId,
            [],
            [],
            $headers,
            $this->encode([
                'name' => 'Lisinopril 20mg',
                'instructions' => 'With food',
                'form' => 'capsule',
                'colorToken' => 'amber',
            ])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $updatedMed = $this->decodeResponse($client->getResponse()->getContent());
        self::assertSame('Lisinopril 20mg', $updatedMed['name']);
        self::assertArrayNotHasKey('dosage', $updatedMed);

        // 6. Delete Medication
        $client->request('DELETE', '/api/v1/profiles/' . $profileId . '/medications/' . $medId, [], [], $headers);
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }
}
