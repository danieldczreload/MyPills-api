<?php

declare(strict_types=1);

namespace Identity\UI\Http;

use Identity\Application\Command\AuthenticateCommand;
use Identity\Application\Command\RefreshTokenCommand;
use Identity\Application\Query\GetMeQuery;
use Shared\UI\Http\ApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1', name: 'api_identity_')]
final class AuthController extends ApiController
{
    #[Route('/auth/google', name: 'auth_google', methods: ['POST'])]
    public function google(Request $request): JsonResponse
    {
        /** @var array{idToken?: mixed} $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $idToken = is_string($data['idToken'] ?? null) ? $data['idToken'] : '';

        $command = new AuthenticateCommand('google', $idToken);
        $result = $this->commandBus->dispatch($command);

        return $this->respond($result);
    }

    #[Route('/auth/microsoft', name: 'auth_microsoft', methods: ['POST'])]
    public function microsoft(Request $request): JsonResponse
    {
        /** @var array{idToken?: mixed} $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $idToken = is_string($data['idToken'] ?? null) ? $data['idToken'] : '';

        $command = new AuthenticateCommand('microsoft', $idToken);
        $result = $this->commandBus->dispatch($command);

        return $this->respond($result);
    }

    #[Route('/auth/refresh', name: 'auth_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        /** @var array{refreshToken?: mixed} $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $refreshToken = is_string($data['refreshToken'] ?? null) ? $data['refreshToken'] : '';

        $command = new RefreshTokenCommand($refreshToken);
        $result = $this->commandBus->dispatch($command);

        return $this->respond($result);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $query = new GetMeQuery($this->getAuthenticatedUserId());
        $result = $this->queryBus->ask($query);

        return $this->respond($result);
    }
}
