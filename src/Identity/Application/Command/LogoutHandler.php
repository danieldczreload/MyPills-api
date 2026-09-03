<?php

declare(strict_types=1);

namespace Identity\Application\Command;

use Identity\Domain\RefreshTokenRepository;
use Shared\Domain\Result;
use Shared\Domain\ValueObject\UserId;
use Shared\Infrastructure\Security\JwtService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class LogoutHandler
{
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly JwtService $jwtService
    ) {
    }

    /**
     * @return Result<null>
     */
    public function __invoke(LogoutCommand $command): Result
    {
        $accountId = $this->accountIdFromAccessToken($command->accessToken);
        if ($accountId !== null) {
            $this->refreshTokenRepository->deleteByAccountId($accountId);

            return Result::success();
        }

        if ($command->refreshToken !== '') {
            $token = $this->refreshTokenRepository->findByToken($command->refreshToken);
            if ($token !== null) {
                $this->refreshTokenRepository->delete($token);
            }
        }

        return Result::success();
    }

    private function accountIdFromAccessToken(string $accessToken): ?UserId
    {
        if ($accessToken === '') {
            return null;
        }

        $payload = $this->jwtService->decodeToken($accessToken);
        if ($payload === null) {
            return null;
        }

        $sub = $payload['sub'] ?? null;
        if (!is_string($sub) && !is_int($sub)) {
            return null;
        }

        $accountId = (string) $sub;
        if ($accountId === '') {
            return null;
        }

        return new UserId($accountId);
    }
}
