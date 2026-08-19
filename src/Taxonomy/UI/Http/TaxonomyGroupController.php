<?php

declare(strict_types=1);

namespace Taxonomy\UI\Http;

use Shared\UI\Http\ApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Taxonomy\Application\Command\CreateTaxonomyGroupCommand;
use Taxonomy\Application\Command\DeleteTaxonomyGroupCommand;
use Taxonomy\Application\Command\UpdateTaxonomyGroupCommand;
use Taxonomy\Application\Query\GetTaxonomyGroupsQuery;

#[Route('/api/v1', name: 'api_taxonomy_')]
final class TaxonomyGroupController extends ApiController
{
    #[Route('/profiles/{id}/taxonomy-groups', name: 'list', methods: ['GET'])]
    public function list(string $id): JsonResponse
    {
        $query = new GetTaxonomyGroupsQuery($id, $this->getAuthenticatedUserId()->value());
        $result = $this->queryBus->ask($query);

        return $this->respond($result);
    }

    #[Route('/profiles/{id}/taxonomy-groups', name: 'create', methods: ['POST'])]
    public function create(string $id, Request $request): JsonResponse
    {
        /** @var array{type?: mixed, name?: mixed, description?: mixed, iconName?: mixed, colorValue?: mixed, isCustom?: mixed, clientId?: mixed} $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $type = is_string($data['type'] ?? null) ? $data['type'] : 'category';
        $name = is_string($data['name'] ?? null) ? $data['name'] : '';
        $description = isset($data['description']) && is_string($data['description']) ? $data['description'] : null;
        $iconName = isset($data['iconName']) && is_string($data['iconName']) ? $data['iconName'] : null;
        $colorValue = isset($data['colorValue']) && is_numeric($data['colorValue']) ? (int) $data['colorValue'] : null;
        $isCustom = isset($data['isCustom']) ? (bool) $data['isCustom'] : true;
        $clientId = isset($data['clientId']) && is_string($data['clientId']) ? $data['clientId'] : null;

        $command = new CreateTaxonomyGroupCommand(
            $id,
            $this->getAuthenticatedUserId()->value(),
            $type,
            $name,
            $description,
            $iconName,
            $colorValue,
            $isCustom,
            $clientId
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result, Response::HTTP_CREATED);
    }

    #[Route('/profiles/{id}/taxonomy-groups/{groupId}', name: 'update', methods: ['PATCH'])]
    public function update(string $id, string $groupId, Request $request): JsonResponse
    {
        /** @var array{type?: mixed, name?: mixed, description?: mixed, iconName?: mixed, colorValue?: mixed, isCustom?: mixed} $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $type = is_string($data['type'] ?? null) ? $data['type'] : 'category';
        $name = is_string($data['name'] ?? null) ? $data['name'] : '';
        $description = isset($data['description']) && is_string($data['description']) ? $data['description'] : null;
        $iconName = isset($data['iconName']) && is_string($data['iconName']) ? $data['iconName'] : null;
        $colorValue = isset($data['colorValue']) && is_numeric($data['colorValue']) ? (int) $data['colorValue'] : null;
        $isCustom = isset($data['isCustom']) ? (bool) $data['isCustom'] : true;

        $command = new UpdateTaxonomyGroupCommand(
            $groupId,
            $id,
            $this->getAuthenticatedUserId()->value(),
            $type,
            $name,
            $description,
            $iconName,
            $colorValue,
            $isCustom
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result);
    }

    #[Route('/profiles/{id}/taxonomy-groups/{groupId}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id, string $groupId): JsonResponse
    {
        $command = new DeleteTaxonomyGroupCommand($groupId, $id, $this->getAuthenticatedUserId()->value());
        $result = $this->commandBus->dispatch($command);

        return $this->respond($result, Response::HTTP_NO_CONTENT);
    }
}
