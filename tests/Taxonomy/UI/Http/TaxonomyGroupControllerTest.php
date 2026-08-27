<?php

declare(strict_types=1);

namespace App\Tests\Taxonomy\UI\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class TaxonomyGroupControllerTest extends WebTestCase
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
            throw new \RuntimeException('Expected JSON array/object.');
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

    public function testTaxonomyGroupCrudLifecycle(): void
    {
        $client = self::createClient();

        // 1. Authenticate
        $client->request(
            'POST',
            '/api/v1/auth/google',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encode(['idToken' => 'valid-taxuser-' . bin2hex(random_bytes(4)) . '@example.com'])
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
                'name' => 'Taxonomy User',
                'birthDate' => '1992-03-10T00:00:00Z',
                'gender' => 'female',
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $profile = $this->decodeResponse($client->getResponse()->getContent());
        $profileId = $this->stringValue($profile, 'id');

        // 3. Create Taxonomy Group
        $clientUuid = 'tax-client-' . bin2hex(random_bytes(4));
        $client->request(
            'POST',
            '/api/v1/profiles/' . $profileId . '/taxonomy-groups',
            [],
            [],
            $headers,
            $this->encode([
                'type' => 'category',
                'name' => 'Cardiovascular',
                'description' => 'Heart medications',
                'iconName' => 'heart_pulse',
                'colorValue' => 0xFF5733,
                'isCustom' => true,
                'clientId' => $clientUuid,
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $createdGroup = $this->decodeResponse($client->getResponse()->getContent());
        $groupId = $this->stringValue($createdGroup, 'id');
        self::assertSame('Cardiovascular', $createdGroup['name']);
        self::assertSame('category', $createdGroup['type']);
        self::assertSame('heart_pulse', $createdGroup['iconName']);
        self::assertSame($clientUuid, $createdGroup['clientId']);

        // 4. List Taxonomy Groups
        $client->request('GET', '/api/v1/profiles/' . $profileId . '/taxonomy-groups', [], [], $headers);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $list = $this->decodeListResponse($client->getResponse()->getContent());
        self::assertCount(1, $list);
        self::assertSame($groupId, $list[0]['id']);

        // 5. Update Taxonomy Group
        $client->request(
            'PATCH',
            '/api/v1/profiles/' . $profileId . '/taxonomy-groups/' . $groupId,
            [],
            [],
            $headers,
            $this->encode([
                'type' => 'tag',
                'name' => 'Cardio & Heart',
                'description' => 'Updated desc',
                'iconName' => 'heart',
                'colorValue' => 0x33FF57,
                'isCustom' => false,
            ])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $updatedGroup = $this->decodeResponse($client->getResponse()->getContent());
        self::assertSame('Cardio & Heart', $updatedGroup['name']);
        self::assertSame('tag', $updatedGroup['type']);
        self::assertFalse($updatedGroup['isCustom']);

        // 6. Delete Taxonomy Group
        $client->request('DELETE', '/api/v1/profiles/' . $profileId . '/taxonomy-groups/' . $groupId, [], [], $headers);
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        // 7. Verify List is now empty
        $client->request('GET', '/api/v1/profiles/' . $profileId . '/taxonomy-groups', [], [], $headers);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $listAfterDelete = $this->decodeListResponse($client->getResponse()->getContent());
        self::assertCount(0, $listAfterDelete);
    }
}
