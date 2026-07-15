<?php

declare(strict_types=1);

namespace Medication\UI\Http;

use Medication\Application\Command\CreateMedicationCommand;
use Medication\Application\Command\UpdateMedicationCommand;
use Medication\Application\Command\DeleteMedicationCommand;
use Medication\Application\Query\GetMedicationsQuery;
use Shared\UI\Http\ApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1', name: 'api_medication_')]
final class MedicationController extends ApiController
{
    #[Route('/profiles/{id}/medications', name: 'list', methods: ['GET'])]
    public function list(string $id): JsonResponse
    {
        $query = new GetMedicationsQuery($id, $this->getAuthenticatedUserId()->value());
        $result = $this->queryBus->ask($query);

        return $this->respond($result);
    }

    #[Route('/profiles/{id}/medications', name: 'create', methods: ['POST'])]
    public function create(string $id, Request $request): JsonResponse
    {
        /** @var array{name?: mixed, dosage?: mixed, instructions?: mixed, photoUrl?: mixed, clientId?: mixed} $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $name = is_string($data['name'] ?? null) ? $data['name'] : '';
        $dosage = is_string($data['dosage'] ?? null) ? $data['dosage'] : '';
        $instructions = isset($data['instructions']) && is_string($data['instructions']) ? $data['instructions'] : null;
        $photoUrl = isset($data['photoUrl']) && is_string($data['photoUrl']) ? $data['photoUrl'] : null;
        $clientId = isset($data['clientId']) && is_string($data['clientId']) ? $data['clientId'] : null;

        $command = new CreateMedicationCommand(
            $id,
            $this->getAuthenticatedUserId()->value(),
            $name,
            $dosage,
            $instructions,
            $photoUrl,
            $clientId
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result, Response::HTTP_CREATED);
    }

    #[Route('/profiles/{id}/medications/{mid}', name: 'update', methods: ['PATCH'])]
    public function update(string $id, string $mid, Request $request): JsonResponse
    {
        /** @var array{name?: mixed, dosage?: mixed, instructions?: mixed, photoUrl?: mixed} $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $name = is_string($data['name'] ?? null) ? $data['name'] : '';
        $dosage = is_string($data['dosage'] ?? null) ? $data['dosage'] : '';
        $instructions = isset($data['instructions']) && is_string($data['instructions']) ? $data['instructions'] : null;
        $photoUrl = isset($data['photoUrl']) && is_string($data['photoUrl']) ? $data['photoUrl'] : null;

        $command = new UpdateMedicationCommand(
            $mid,
            $id,
            $this->getAuthenticatedUserId()->value(),
            $name,
            $dosage,
            $instructions,
            $photoUrl
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result);
    }

    #[Route('/profiles/{id}/medications/{mid}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id, string $mid): JsonResponse
    {
        $command = new DeleteMedicationCommand($mid, $id, $this->getAuthenticatedUserId()->value());
        $result = $this->commandBus->dispatch($command);

        return $this->respond($result, Response::HTTP_NO_CONTENT);
    }
}
