<?php

declare(strict_types=1);

namespace App\Tests\Shared\UI\Http;

use Shared\Application\Bus\CommandBus;
use Shared\Application\Bus\QueryBus;
use Shared\Domain\Failure;
use Shared\Domain\Result;
use Shared\UI\Http\ApiController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Shared\Infrastructure\Security\CurrentUserContext;

final class ApiControllerTest extends KernelTestCase
{
    private CommandBus $commandBus;
    private QueryBus $queryBus;
    private CurrentUserContext $currentUserContext;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(CommandBus::class);
        $this->queryBus = $this->createMock(QueryBus::class);
        $this->currentUserContext = new CurrentUserContext();
    }

    public function testRespondSuccess(): void
    {
        $controller = new class ($this->commandBus, $this->queryBus, $this->currentUserContext) extends ApiController {
            /**
             * @template T
             * @param Result<T> $result
             */
            public function testRespond(Result $result): JsonResponse
            {
                return $this->respond($result, Response::HTTP_CREATED);
            }
        };

        $result = Result::success(['foo' => 'bar']);
        $response = $controller->testRespond($result);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame('{"foo":"bar"}', $response->getContent());
    }

    /**
     * @dataProvider provideFailureCases
     * @param array<string, mixed> $expectedDetails
     */
    public function testRespondWithFailure(Failure $failure, int $expectedStatusCode, string $expectedType, string $expectedMessage, array $expectedDetails): void
    {
        $controller = new class ($this->commandBus, $this->queryBus, $this->currentUserContext) extends ApiController {
            public function testRespondFailure(Failure $failure): JsonResponse
            {
                return $this->respondWithFailure($failure);
            }
        };

        $response = $controller->testRespondFailure($failure);
        self::assertSame($expectedStatusCode, $response->getStatusCode());

        $content = $response->getContent();
        self::assertNotFalse($content);
        $data = json_decode($content, true);

        self::assertIsArray($data);
        /** @var array{error: array{type: string, message: string, details: array<string, mixed>}} $data */

        self::assertSame($expectedType, $data['error']['type']);
        self::assertSame($expectedMessage, $data['error']['message']);
        self::assertSame($expectedDetails, $data['error']['details']);
    }

    /**
     * @return array<string, array{0: Failure, 1: int, 2: string, 3: string, 4: array<string, mixed>}>
     */
    public function provideFailureCases(): array
    {
        return [
            'not found' => [
                Failure::notFound('User not found'),
                Response::HTTP_NOT_FOUND,
                'NOT_FOUND',
                'User not found',
                [],
            ],
            'validation' => [
                Failure::validation('Invalid input', ['field' => 'email']),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'VALIDATION',
                'Invalid input',
                ['field' => 'email'],
            ],
            'unauthorized' => [
                Failure::unauthorized('Invalid credentials'),
                Response::HTTP_UNAUTHORIZED,
                'UNAUTHORIZED',
                'Invalid credentials',
                [],
            ],
            'forbidden' => [
                Failure::forbidden('Access denied'),
                Response::HTTP_FORBIDDEN,
                'FORBIDDEN',
                'Access denied',
                [],
            ],
            'conflict' => [
                Failure::conflict('Resource already exists'),
                Response::HTTP_CONFLICT,
                'CONFLICT',
                'Resource already exists',
                [],
            ],
            'bad request' => [
                Failure::badRequest('Bad request'),
                Response::HTTP_BAD_REQUEST,
                'BAD_REQUEST',
                'Bad request',
                [],
            ],
        ];
    }

    public function testServerErrorRedactionInProd(): void
    {
        $controller = new class ($this->commandBus, $this->queryBus, $this->currentUserContext, 'prod') extends ApiController {
            public function testRespondFailure(Failure $failure): JsonResponse
            {
                return $this->respondWithFailure($failure);
            }
        };

        $failure = Failure::server('Database driver timeout', ['query' => 'SELECT 1']);
        $response = $controller->testRespondFailure($failure);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $content = $response->getContent();
        self::assertNotFalse($content);
        $data = json_decode($content, true);

        self::assertIsArray($data);
        /** @var array{error: array{type: string, message: string, details: array<string, mixed>}} $data */

        self::assertSame('SERVER_ERROR', $data['error']['type']);
        self::assertSame('An unexpected error occurred.', $data['error']['message']);
        self::assertSame([], $data['error']['details']);
    }

    public function testServerErrorNoRedactionInDev(): void
    {
        $controller = new class ($this->commandBus, $this->queryBus, $this->currentUserContext, 'dev') extends ApiController {
            public function testRespondFailure(Failure $failure): JsonResponse
            {
                return $this->respondWithFailure($failure);
            }
        };

        $failure = Failure::server('Database driver timeout', ['query' => 'SELECT 1']);
        $response = $controller->testRespondFailure($failure);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $content = $response->getContent();
        self::assertNotFalse($content);
        $data = json_decode($content, true);

        self::assertIsArray($data);
        /** @var array{error: array{type: string, message: string, details: array<string, mixed>}} $data */

        self::assertSame('SERVER_ERROR', $data['error']['type']);
        self::assertSame('Database driver timeout', $data['error']['message']);
        self::assertSame(['query' => 'SELECT 1'], $data['error']['details']);
    }
}
