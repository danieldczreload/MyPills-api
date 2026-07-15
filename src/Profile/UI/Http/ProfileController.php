<?php

declare(strict_types=1);

namespace Profile\UI\Http;

use Profile\Application\Command\CreateProfileCommand;
use Profile\Application\Command\UpdateProfileCommand;
use Profile\Application\Command\DeleteProfileCommand;
use Profile\Application\Query\GetProfilesQuery;
use Shared\UI\Http\ApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1', name: 'api_profile_')]
final class ProfileController extends ApiController
{
    #[Route('/profiles', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $query = new GetProfilesQuery($this->getAuthenticatedUserId()->value());
        $result = $this->queryBus->ask($query);

        return $this->respond($result);
    }

    #[Route('/profiles', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var array{name?: mixed, birthDate?: mixed, gender?: mixed, photoUrl?: mixed} $data */
        $data = json_decode($request->getContent(), true) ?? [];

        $birthDateStr = is_string($data['birthDate'] ?? null) ? $data['birthDate'] : '';
        try {
            $birthDate = new \DateTimeImmutable($birthDateStr);
        } catch (\Exception) {
            return $this->respondWithFailure(\Shared\Domain\Failure::validation('Invalid birthDate format.'));
        }

        $name = is_string($data['name'] ?? null) ? $data['name'] : '';
        $gender = is_string($data['gender'] ?? null) ? $data['gender'] : '';
        $photoUrl = isset($data['photoUrl']) && is_string($data['photoUrl']) ? $data['photoUrl'] : null;

        $command = new CreateProfileCommand(
            $this->getAuthenticatedUserId()->value(),
            $name,
            $birthDate,
            $gender,
            $photoUrl
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result, Response::HTTP_CREATED);
    }

    #[Route('/profiles/{id}', name: 'update', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        /** @var array{name?: mixed, birthDate?: mixed, gender?: mixed, photoUrl?: mixed} $data */
        $data = json_decode($request->getContent(), true) ?? [];

        $birthDateStr = is_string($data['birthDate'] ?? null) ? $data['birthDate'] : '';
        try {
            $birthDate = new \DateTimeImmutable($birthDateStr);
        } catch (\Exception) {
            return $this->respondWithFailure(\Shared\Domain\Failure::validation('Invalid birthDate format.'));
        }

        $name = is_string($data['name'] ?? null) ? $data['name'] : '';
        $gender = is_string($data['gender'] ?? null) ? $data['gender'] : '';
        $photoUrl = isset($data['photoUrl']) && is_string($data['photoUrl']) ? $data['photoUrl'] : null;

        $command = new UpdateProfileCommand(
            $id,
            $this->getAuthenticatedUserId()->value(),
            $name,
            $birthDate,
            $gender,
            $photoUrl
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result);
    }

    #[Route('/profiles/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $command = new DeleteProfileCommand($id, $this->getAuthenticatedUserId()->value());
        $result = $this->commandBus->dispatch($command);

        return $this->respond($result, Response::HTTP_NO_CONTENT);
    }

    #[Route('/profiles/{id}/sync', name: 'sync', methods: ['GET'])]
    public function sync(string $id, Request $request): JsonResponse
    {
        $sinceStr = $request->query->get('since');
        if ($sinceStr === null || $sinceStr === '') {
            $since = new \DateTimeImmutable('@0');
        } else {
            try {
                $since = new \DateTimeImmutable((string) $sinceStr);
            } catch (\Exception) {
                return $this->respondWithFailure(\Shared\Domain\Failure::validation('Invalid since date format.'));
            }
        }

        $query = new \Profile\Application\Query\SyncProfileQuery(
            $id,
            $this->getAuthenticatedUserId()->value(),
            $since
        );

        $result = $this->queryBus->ask($query);

        return $this->respond($result);
    }
}
