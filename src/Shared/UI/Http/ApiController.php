<?php

declare(strict_types=1);

namespace Shared\UI\Http;

use Shared\Application\Bus\CommandBus;
use Shared\Application\Bus\QueryBus;
use Shared\Domain\Failure;
use Shared\Domain\Result;
use Shared\Infrastructure\Security\CurrentUserContext;
use Shared\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiController extends AbstractController
{
    public function __construct(
        protected readonly CommandBus $commandBus,
        protected readonly QueryBus $queryBus,
        protected readonly CurrentUserContext $currentUserContext,
        #[Autowire('%kernel.environment%')] protected readonly string $env = 'prod'
    ) {
    }

    protected function getAuthenticatedUserId(): UserId
    {
        $userId = $this->currentUserContext->getUserId();
        if ($userId === null) {
            throw new \LogicException('No authenticated user found.');
        }
        return $userId;
    }

    /**
     * @template T
     * @param Result<T> $result
     * @param int $successStatusCode
     * @return JsonResponse
     */
    protected function respond(Result $result, int $successStatusCode = Response::HTTP_OK): JsonResponse
    {
        if ($result->isSuccess()) {
            $value = $result->getValue();

            return new JsonResponse($value, $successStatusCode);
        }

        return $this->respondWithFailure($result->getFailure());
    }

    protected function respondWithFailure(Failure $failure): JsonResponse
    {
        $statusCode = match ($failure->getType()) {
            'NOT_FOUND' => Response::HTTP_NOT_FOUND,
            'VALIDATION' => Response::HTTP_UNPROCESSABLE_ENTITY,
            'UNAUTHORIZED' => Response::HTTP_UNAUTHORIZED,
            'FORBIDDEN' => Response::HTTP_FORBIDDEN,
            'CONFLICT' => Response::HTTP_CONFLICT,
            'BAD_REQUEST', 'SYNC_PARTIAL_FAILURE' => Response::HTTP_BAD_REQUEST,
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };

        $type = $failure->getType();
        $message = $failure->getMessage();
        $details = $failure->getDetails();

        if ($statusCode === Response::HTTP_INTERNAL_SERVER_ERROR) {
            $isProd = $this->env === 'prod';
            if ($isProd) {
                $message = 'An unexpected error occurred.';
                $details = [];
            }
        }

        return new JsonResponse([
            'error' => [
                'type' => $type,
                'message' => $message,
                'details' => $details,
            ],
        ], $statusCode);
    }
}
