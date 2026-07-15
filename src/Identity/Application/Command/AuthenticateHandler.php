<?php

declare(strict_types=1);

namespace Identity\Application\Command;

use Identity\Domain\Account;
use Identity\Domain\AccountOAuthLink;
use Identity\Domain\AccountRepository;
use Identity\Domain\GoogleIdentityProvider;
use Identity\Domain\MicrosoftIdentityProvider;
use Identity\Domain\RefreshToken;
use Identity\Domain\RefreshTokenRepository;
use Shared\Domain\Result;
use Shared\Domain\ValueObject\Email;
use Shared\Domain\ValueObject\UserId;
use Shared\Infrastructure\Security\JwtService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AuthenticateHandler
{
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly GoogleIdentityProvider $googleProvider,
        private readonly MicrosoftIdentityProvider $microsoftProvider,
        private readonly JwtService $jwtService
    ) {
    }

    /**
     * @return Result<array{token: string, refreshToken: string}>
     */
    public function __invoke(AuthenticateCommand $command): Result
    {
        $provider = $command->provider;
        $idToken = $command->idToken;

        $providerService = $provider === 'google' ? $this->googleProvider : $this->microsoftProvider;
        $verifyResult = $providerService->verifyToken($idToken);

        if ($verifyResult->isFailure()) {
            return Result::failure($verifyResult->getFailure());
        }

        $externalUser = $verifyResult->getValue();

        // 1. Check if OAuth link already exists
        $account = $this->accountRepository->findLinked($provider, $externalUser->externalId);

        if ($account === null) {
            // 2. Check if email is already registered under another account
            $email = new Email($externalUser->email);
            $account = $this->accountRepository->findByEmail($email);

            if ($account === null) {
                // Create new account
                $account = Account::create(UserId::generate(), $email);
                $this->accountRepository->save($account);
            }

            // Create OAuth link
            $link = AccountOAuthLink::create($account->id(), $provider, $externalUser->externalId);
            $this->accountRepository->saveLink($link);
        }

        // 3. Generate JWT
        $jwt = $this->jwtService->createToken([
            'sub' => $account->id()->value(),
            'email' => $account->email()->value(),
        ]);

        // 4. Generate Refresh Token
        $refreshTokenString = bin2hex(random_bytes(32));
        $refreshToken = RefreshToken::create($account->id(), $refreshTokenString);
        $this->refreshTokenRepository->save($refreshToken);

        return Result::success([
            'token' => $jwt,
            'refreshToken' => $refreshTokenString,
        ]);
    }
}
