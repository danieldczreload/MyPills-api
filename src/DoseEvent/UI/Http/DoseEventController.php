<?php

declare(strict_types=1);

namespace DoseEvent\UI\Http;

use DoseEvent\Application\Command\TrackDoseCommand;
use DoseEvent\Application\Query\GetDoseEventsQuery;
use Shared\UI\Http\ApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1', name: 'api_dose_event_')]
final class DoseEventController extends ApiController
{
    #[Route('/profiles/{id}/dose-events', name: 'create', methods: ['POST'])]
    public function create(string $id, Request $request): JsonResponse
    {
        /** @var array{
         *     scheduleId?: mixed,
         *     scheduledAt?: mixed,
         *     status?: mixed,
         *     takenAt?: mixed,
         *     clientId?: mixed
         * } $data */
        $data = json_decode($request->getContent(), true) ?? [];

        $scheduledAtStr = is_string($data['scheduledAt'] ?? null) ? $data['scheduledAt'] : '';
        try {
            $scheduledAt = new \DateTimeImmutable($scheduledAtStr);
        } catch (\Exception) {
            return $this->respondWithFailure(\Shared\Domain\Failure::validation('Invalid scheduledAt format.'));
        }

        $takenAt = null;
        if (isset($data['takenAt']) && is_string($data['takenAt']) && $data['takenAt'] !== '') {
            try {
                $takenAt = new \DateTimeImmutable($data['takenAt']);
            } catch (\Exception) {
                return $this->respondWithFailure(\Shared\Domain\Failure::validation('Invalid takenAt format.'));
            }
        }

        $scheduleId = is_string($data['scheduleId'] ?? null) ? $data['scheduleId'] : '';
        $status = is_string($data['status'] ?? null) ? $data['status'] : '';
        $clientId = isset($data['clientId']) && is_string($data['clientId']) ? $data['clientId'] : null;

        $command = new TrackDoseCommand(
            $id,
            $this->getAuthenticatedUserId()->value(),
            $scheduleId,
            $scheduledAt,
            $status,
            $takenAt,
            $clientId
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result, Response::HTTP_CREATED);
    }

    #[Route('/profiles/{id}/dose-events', name: 'list', methods: ['GET'])]
    public function list(string $id, Request $request): JsonResponse
    {
        $fromStr = (string) $request->query->get('from');
        $toStr = (string) $request->query->get('to');

        if ($fromStr === '' || $toStr === '') {
            return $this->respondWithFailure(\Shared\Domain\Failure::validation('Parameters "from" and "to" are required.'));
        }

        try {
            $from = new \DateTimeImmutable($fromStr);
            $to = new \DateTimeImmutable($toStr);
        } catch (\Exception) {
            return $this->respondWithFailure(\Shared\Domain\Failure::validation('Invalid from or to date format.'));
        }

        $query = new GetDoseEventsQuery($id, $this->getAuthenticatedUserId()->value(), $from, $to);
        $result = $this->queryBus->ask($query);

        return $this->respond($result);
    }
}
