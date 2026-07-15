<?php

declare(strict_types=1);

namespace Schedule\UI\Http;

use Schedule\Application\Command\CreateScheduleCommand;
use Schedule\Application\Command\DeleteScheduleCommand;
use Schedule\Application\Query\GetSchedulesQuery;
use Shared\UI\Http\ApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1', name: 'api_schedule_')]
final class ScheduleController extends ApiController
{
    #[Route('/profiles/{id}/schedules', name: 'list', methods: ['GET'])]
    public function list(string $id): JsonResponse
    {
        $query = new GetSchedulesQuery($id, $this->getAuthenticatedUserId()->value());
        $result = $this->queryBus->ask($query);

        return $this->respond($result);
    }

    #[Route('/profiles/{id}/schedules', name: 'create', methods: ['POST'])]
    public function create(string $id, Request $request): JsonResponse
    {
        /** @var array{
         *     medicationId?: mixed,
         *     type?: mixed,
         *     startDate?: mixed,
         *     endDate?: mixed,
         *     timesOfDay?: mixed,
         *     everyHours?: mixed,
         *     startAt?: mixed,
         *     endAt?: mixed,
         *     daysOfWeek?: mixed,
         *     clientId?: mixed
         * } $data */
        $data = json_decode($request->getContent(), true) ?? [];

        $startDateStr = is_string($data['startDate'] ?? null) ? $data['startDate'] : '';
        try {
            $startDate = new \DateTimeImmutable($startDateStr);
        } catch (\Exception) {
            return $this->respondWithFailure(\Shared\Domain\Failure::validation('Invalid startDate format.'));
        }

        $endDate = null;
        if (isset($data['endDate']) && is_string($data['endDate']) && $data['endDate'] !== '') {
            try {
                $endDate = new \DateTimeImmutable($data['endDate']);
            } catch (\Exception) {
                return $this->respondWithFailure(\Shared\Domain\Failure::validation('Invalid endDate format.'));
            }
        }

        /** @var array<array{hour: int, minute: int}>|null $timesOfDay */
        $timesOfDay = isset($data['timesOfDay']) && is_array($data['timesOfDay']) ? $data['timesOfDay'] : null;
        /** @var array{hour: int, minute: int}|null $startAt */
        $startAt = isset($data['startAt']) && is_array($data['startAt']) ? $data['startAt'] : null;
        /** @var array{hour: int, minute: int}|null $endAt */
        $endAt = isset($data['endAt']) && is_array($data['endAt']) ? $data['endAt'] : null;
        /** @var array<int>|null $daysOfWeek */
        $daysOfWeek = isset($data['daysOfWeek']) && is_array($data['daysOfWeek']) ? $data['daysOfWeek'] : null;

        $medicationId = is_string($data['medicationId'] ?? null) ? $data['medicationId'] : '';
        $type = is_string($data['type'] ?? null) ? $data['type'] : '';
        $everyHours = isset($data['everyHours']) && (is_int($data['everyHours']) || is_numeric($data['everyHours'])) ? (int) $data['everyHours'] : null;
        $clientId = isset($data['clientId']) && is_string($data['clientId']) ? $data['clientId'] : null;

        $command = new CreateScheduleCommand(
            $id,
            $this->getAuthenticatedUserId()->value(),
            $medicationId,
            $type,
            $startDate,
            $endDate,
            $timesOfDay,
            $everyHours,
            $startAt,
            $endAt,
            $daysOfWeek,
            $clientId
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result, Response::HTTP_CREATED);
    }

    #[Route('/profiles/{id}/schedules/{sid}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id, string $sid): JsonResponse
    {
        $command = new DeleteScheduleCommand($sid, $id, $this->getAuthenticatedUserId()->value());
        $result = $this->commandBus->dispatch($command);

        return $this->respond($result, Response::HTTP_NO_CONTENT);
    }
}
