<?php

declare(strict_types=1);

namespace Shared\UI\Http;

use Shared\Domain\Failure;
use Shared\Infrastructure\Storage\FileUploadService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1', name: 'api_upload_')]
final class UploadController extends ApiController
{
    public function __construct(
        \Shared\Application\Bus\CommandBus $commandBus,
        \Shared\Application\Bus\QueryBus $queryBus,
        \Shared\Infrastructure\Security\CurrentUserContext $currentUserContext,
        private readonly FileUploadService $fileUploadService
    ) {
        parent::__construct($commandBus, $queryBus, $currentUserContext);
    }

    #[Route('/uploads', name: 'upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            return $this->respondWithFailure(Failure::validation('No file uploaded or invalid form field "file".'));
        }

        $result = $this->fileUploadService->upload($file);

        return $this->respond($result, Response::HTTP_CREATED);
    }
}
