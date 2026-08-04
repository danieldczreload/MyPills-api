<?php

declare(strict_types=1);

namespace CalendarIntegration\Application\Command;

use CalendarIntegration\Application\CalendarProviderResolver;
use CalendarIntegration\Domain\CalendarAuthorizationRequestRepository;
use CalendarIntegration\Domain\CalendarLink;
use CalendarIntegration\Domain\CalendarLinkRepository;
use CalendarIntegration\Domain\CalendarProviderName;
use Profile\Domain\ProfileRepository;
use Shared\Domain\Failure;
use Shared\Domain\Result;
use Shared\Domain\TokenVault;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CompleteCalendarAuthorizationHandler
{
    public function __construct(
        private readonly CalendarAuthorizationRequestRepository $authorizationRequestRepository,
        private readonly CalendarLinkRepository $calendarLinkRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly TokenVault $tokenVault,
        private readonly CalendarProviderResolver $providerResolver
    ) {
    }

    /**
     * @return Result<array{profileId: string, provider: string, connected: bool}>
     */
    public function __invoke(CompleteCalendarAuthorizationCommand $command): Result
    {
        if (!CalendarProviderName::isSupported($command->provider)) {
            return Result::failure(Failure::validation('Invalid provider.'));
        }

        if ($command->code === '' || $command->state === '' || $command->codeVerifier === '') {
            return Result::failure(Failure::validation('Authorization code, state and PKCE verifier are required.'));
        }

        if (!preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $command->codeVerifier)) {
            return Result::failure(Failure::validation('Invalid PKCE code verifier.'));
        }

        $profileId = new ProfileId($command->profileId);
        $accountId = new UserId($command->accountId);
        $profile = $this->profileRepository->findById($profileId);

        if ($profile === null) {
            return Result::failure(Failure::notFound('Profile not found.'));
        }

        if (!$profile->accountId()->equals($accountId)) {
            return Result::failure(Failure::forbidden('You do not own this profile.'));
        }

        $authorizationRequest = $this->authorizationRequestRepository->findByStateHash(hash('sha256', $command->state));
        if (
            $authorizationRequest === null
            || !$authorizationRequest->isUsable(new \DateTimeImmutable())
            || !$authorizationRequest->accountId()->equals($accountId)
            || !$authorizationRequest->profileId()->equals($profileId)
            || $authorizationRequest->provider() !== $command->provider
        ) {
            return Result::failure(Failure::unauthorized('Invalid or expired calendar authorization state.'));
        }

        $expectedCodeChallenge = rtrim(strtr(base64_encode(hash('sha256', $command->codeVerifier, true)), '+/', '-_'), '=');
        if (!hash_equals($authorizationRequest->codeChallenge(), $expectedCodeChallenge)) {
            return Result::failure(Failure::unauthorized('Invalid PKCE code verifier.'));
        }

        if (!$this->authorizationRequestRepository->consume($authorizationRequest, new \DateTimeImmutable())) {
            return Result::failure(Failure::unauthorized('Calendar authorization state has already been used.'));
        }

        $oauthClient = $this->providerResolver->resolveString($command->provider);

        try {
            $tokens = $oauthClient->exchangeAuthorizationCode($command->code, $command->codeVerifier);
        } catch (\Throwable) {
            return Result::failure(Failure::badRequest('Calendar authorization could not be completed.'));
        }

        $link = $this->calendarLinkRepository->findByProfileAndProvider($profileId, $command->provider);
        $refreshToken = $tokens->refreshToken();

        if ($refreshToken === null && $link !== null) {
            try {
                $refreshToken = $this->tokenVault->decrypt($link->encryptedRefreshToken());
            } catch (\Throwable) {
                return Result::failure(Failure::server('Stored calendar authorization is invalid.'));
            }
        }

        if ($refreshToken === null) {
            return Result::failure(Failure::badRequest('The provider did not return a refresh token. Please authorize again.'));
        }

        $encryptedRefreshToken = $this->tokenVault->encrypt($refreshToken);
        if ($link === null) {
            $link = CalendarLink::create($profileId, $command->provider, $encryptedRefreshToken);
        } else {
            $link->updateEncryptedRefreshToken($encryptedRefreshToken);
            $link->markActive();
        }

        $this->calendarLinkRepository->save($link);

        return Result::success([
            'profileId' => $command->profileId,
            'provider' => $command->provider,
            'connected' => true,
        ]);
    }
}
