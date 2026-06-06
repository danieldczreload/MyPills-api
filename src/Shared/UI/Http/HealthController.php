<?php

declare(strict_types=1);

namespace Shared\UI\Http;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    #[Route('/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        try {
            $this->connection->executeQuery('SELECT 1');
            $dbStatus = 'ok';
        } catch (\Throwable) {
            $dbStatus = 'error';
        }

        $statusCode = $dbStatus === 'ok' ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR;

        return new JsonResponse([
            'db' => $dbStatus,
            'version' => '1.0.0',
        ], $statusCode);
    }
}
