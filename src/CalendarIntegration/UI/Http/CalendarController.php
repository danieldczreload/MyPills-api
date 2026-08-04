<?php

declare(strict_types=1);

namespace CalendarIntegration\UI\Http;

use CalendarIntegration\Application\Command\CompleteCalendarAuthorizationCommand;
use CalendarIntegration\Application\Command\DisconnectCalendarCommand;
use CalendarIntegration\Application\Command\StartCalendarAuthorizationCommand;
use CalendarIntegration\Application\Command\SyncCalendarCommand;
use CalendarIntegration\Application\Query\GetCalendarConnectionsQuery;
use Shared\Domain\Failure;
use Shared\UI\Http\ApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1', name: 'api_calendar_')]
final class CalendarController extends ApiController
{
    #[Route('/calendars', name: 'connections', methods: ['GET'])]
    public function connections(Request $request): JsonResponse
    {
        $profileId = (string) $request->query->get('profileId');
        if ($profileId === '') {
            return $this->respondWithFailure(\Shared\Domain\Failure::validation('Parameter "profileId" is required.'));
        }

        $query = new GetCalendarConnectionsQuery(
            $profileId,
            $this->getAuthenticatedUserId()->value()
        );

        return $this->respond($this->queryBus->ask($query));
    }

    #[Route('/calendars/{provider}/authorize', name: 'authorize', methods: ['POST'])]
    public function authorize(string $provider, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->respondWithFailure(Failure::validation('Request body must contain valid JSON.'));
        }

        if (!is_array($data)) {
            return $this->respondWithFailure(Failure::validation('Request body must contain a JSON object.'));
        }

        $profileId = is_string($data['profileId'] ?? null) ? $data['profileId'] : '';
        $codeChallenge = is_string($data['codeChallenge'] ?? null) ? $data['codeChallenge'] : '';

        $command = new StartCalendarAuthorizationCommand(
            $profileId,
            $this->getAuthenticatedUserId()->value(),
            $provider,
            $codeChallenge
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result);
    }

    #[Route('/calendars/{provider}/connect', name: 'connect', methods: ['POST'])]
    public function connect(string $provider, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->respondWithFailure(Failure::validation('Request body must contain valid JSON.'));
        }

        if (!is_array($data)) {
            return $this->respondWithFailure(Failure::validation('Request body must contain a JSON object.'));
        }

        if (array_key_exists('refreshToken', $data)) {
            return $this->respondWithFailure(Failure::validation(
                'Refresh-token calendar connections are no longer accepted. Use the PKCE authorization flow.'
            ));
        }

        $profileId = is_string($data['profileId'] ?? null) ? $data['profileId'] : '';
        $accountId = $this->getAuthenticatedUserId()->value();

        $code = is_string($data['code'] ?? null) ? $data['code'] : '';
        $state = is_string($data['state'] ?? null) ? $data['state'] : '';
        $codeVerifier = is_string($data['codeVerifier'] ?? null) ? $data['codeVerifier'] : '';

        $command = new CompleteCalendarAuthorizationCommand(
            $profileId,
            $accountId,
            $provider,
            $code,
            $state,
            $codeVerifier
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
