<?php

declare(strict_types=1);

namespace Identity\Application\Command;

use Identity\Domain\AccountRepository;
use Identity\Domain\RefreshToken;
use Identity\Domain\RefreshTokenRepository;
use Shared\Domain\Result;
use Shared\Domain\Failure;
use Shared\Infrastructure\Security\JwtService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RefreshTokenHandler
{
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly JwtService $jwtService
    ) {
    }

    /**
     * @return Result<array{token: string, refreshToken: string}>
     */
    public function __invoke(RefreshTokenCommand $command): Result
    {
        $token = $this->refreshTokenRepository->findByToken($command->refreshToken);

        if ($token === null) {
            return Result::failure(Failure::unauthorized('Invalid refresh token.'));
        }

        if ($token->isExpired()) {
            $this->refreshTokenRepository->delete($token);
            return Result::failure(Failure::unauthorized('Expired refresh token.'));
        }

        $account = $this->accountRepository->findById($token->accountId());

        if ($account === null) {
            return Result::failure(Failure::notFound('Account not found.'));
        }

        // Rotate: delete the old one
        $this->refreshTokenRepository->delete($token);

        // Generate new JWT
        $jwt = $this->jwtService->createToken([
            'sub' => $account->id()->value(),
            'email' => $account->email()->value(),
        ]);

        // Generate new Refresh Token
        $newRefreshTokenString = bin2hex(random_bytes(32));
        $newRefreshToken = RefreshToken::create($account->id(), $newRefreshTokenString);
        $this->refreshTokenRepository->save($newRefreshToken);

        return Result::success([
            'token' => $jwt,
            'refreshToken' => $newRefreshTokenString,
        ]);
    }
}
