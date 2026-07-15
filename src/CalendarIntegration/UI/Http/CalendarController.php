<?php

declare(strict_types=1);

namespace CalendarIntegration\UI\Http;

use CalendarIntegration\Application\Command\ConnectCalendarCommand;
use CalendarIntegration\Application\Command\DisconnectCalendarCommand;
use CalendarIntegration\Application\Command\SyncCalendarCommand;
use Shared\UI\Http\ApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1', name: 'api_calendar_')]
final class CalendarController extends ApiController
{
    #[Route('/calendars/google/connect', name: 'connect_google', methods: ['POST'])]
    public function connectGoogle(Request $request): JsonResponse
    {
        /** @var array{profileId?: mixed, refreshToken?: mixed} $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $profileId = is_string($data['profileId'] ?? null) ? $data['profileId'] : '';
        $refreshToken = is_string($data['refreshToken'] ?? null) ? $data['refreshToken'] : '';

        $command = new ConnectCalendarCommand(
            $profileId,
            $this->getAuthenticatedUserId()->value(),
            'google',
            $refreshToken
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result);
    }

    #[Route('/calendars/microsoft/connect', name: 'connect_microsoft', methods: ['POST'])]
    public function connectMicrosoft(Request $request): JsonResponse
    {
        /** @var array{profileId?: mixed, refreshToken?: mixed} $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $profileId = is_string($data['profileId'] ?? null) ? $data['profileId'] : '';
        $refreshToken = is_string($data['refreshToken'] ?? null) ? $data['refreshToken'] : '';

        $command = new ConnectCalendarCommand(
            $profileId,
            $this->getAuthenticatedUserId()->value(),
            'microsoft',
            $refreshToken
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result);
    }

    #[Route('/calendars/{provider}', name: 'disconnect', methods: ['DELETE'])]
    public function disconnect(string $provider, Request $request): JsonResponse
    {
        $profileId = (string) $request->query->get('profileId');
        if ($profileId === '') {
            return $this->respondWithFailure(\Shared\Domain\Failure::validation('Parameter "profileId" is required.'));
        }

        $command = new DisconnectCalendarCommand(
            $profileId,
            $this->getAuthenticatedUserId()->value(),
            $provider
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result, Response::HTTP_NO_CONTENT);
    }

    #[Route('/calendars/sync', name: 'sync', methods: ['POST'])]
    public function sync(Request $request): JsonResponse
    {
        $profileId = $request->query->get('profileId') !== null ? (string) $request->query->get('profileId') : null;

        $command = new SyncCalendarCommand(
            $this->getAuthenticatedUserId()->value(),
            $profileId
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result);
    }
}
