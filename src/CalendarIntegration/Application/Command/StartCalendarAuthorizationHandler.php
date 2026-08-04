<?php

declare(strict_types=1);

namespace CalendarIntegration\Application\Command;

use CalendarIntegration\Application\CalendarProviderResolver;
use CalendarIntegration\Domain\CalendarAuthorizationRequest;
use CalendarIntegration\Domain\CalendarAuthorizationRequestRepository;
use CalendarIntegration\Domain\CalendarProviderName;
use Profile\Domain\ProfileRepository;
use Shared\Domain\Failure;
use Shared\Domain\Result;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class StartCalendarAuthorizationHandler
{
    public function __construct(
        private readonly CalendarAuthorizationRequestRepository $authorizationRequestRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly CalendarProviderResolver $providerResolver
    ) {
    }

    /**
     * @return Result<array{state: string, authorizationUrl: string, expiresAt: string}>
     */
    public function __invoke(StartCalendarAuthorizationCommand $command): Result
    {
        if (!CalendarProviderName::isSupported($command->provider)) {
            return Result::failure(Failure::validation('Invalid provider.'));
        }

        if (!preg_match('/^[A-Za-z0-9_-]{43,128}$/', $command->codeChallenge)) {
            return Result::failure(Failure::validation('Invalid PKCE code challenge.'));
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

        $state = self::randomToken();
        $expiresAt = new \DateTimeImmutable('+5 minutes');
        $this->authorizationRequestRepository->deleteExpired(new \DateTimeImmutable());
        $request = CalendarAuthorizationRequest::create(
            $accountId,
            $profileId,
            $command->provider,
            hash('sha256', $state),
            $command->codeChallenge,
            $expiresAt
        );

        $oauthClient = $this->providerResolver->resolveString($command->provider);
        $authorizationUrl = $oauthClient->authorizationUrl($state, $command->codeChallenge);
        $this->authorizationRequestRepository->save($request);

        return Result::success([
            'state' => $state,
            'authorizationUrl' => $authorizationUrl,
            'expiresAt' => $expiresAt->format(\DateTimeInterface::ATOM),
        ]);
    }

    private static function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
