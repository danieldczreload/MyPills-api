<?php

declare(strict_types=1);

namespace App\Tests\Shared\UI\Http;

use Doctrine\DBAL\Connection;
use Shared\UI\Http\HealthController;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class HealthControllerTest extends WebTestCase
{
    public function testHealthEndpointReturnsSuccess(): void
    {
        $client = self::createClient();
        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $responseContent = $client->getResponse()->getContent();
        self::assertNotFalse($responseContent);

        $data = json_decode($responseContent, true);

        self::assertIsArray($data);
        self::assertSame('ok', $data['db'] ?? null);
        self::assertSame('1.0.0', $data['version'] ?? null);
    }

    public function testHealthEndpointReturnsFailureWhenDbDown(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('executeQuery')
            ->with('SELECT 1')
            ->willThrowException(new \RuntimeException('Database is down'));

        $controller = new HealthController($connection);
        $response = $controller();

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('content-type'));

        $responseContent = $response->getContent();
        self::assertNotFalse($responseContent);

        $data = json_decode($responseContent, true);

        self::assertIsArray($data);
        self::assertSame('error', $data['db'] ?? null);
        self::assertSame('1.0.0', $data['version'] ?? null);
    }
}
