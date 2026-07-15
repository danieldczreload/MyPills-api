<?php

declare(strict_types=1);

namespace Identity\Infrastructure\Security;

use Identity\Domain\ExternalUser;
use Identity\Domain\MicrosoftIdentityProvider;
use Shared\Domain\Result;
use Shared\Domain\Failure;

final class FakeMicrosoftIdentityProvider implements MicrosoftIdentityProvider
{
    public function verifyToken(string $idToken): Result
    {
        if (str_starts_with($idToken, 'valid-')) {
            $email = substr($idToken, 6);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = 'microsoft-user@example.com';
            }
            $externalId = 'microsoft-' . md5($email);

            return Result::success(new ExternalUser(
                $externalId,
                $email,
                'Microsoft User'
            ));
        }

        return Result::failure(Failure::unauthorized('Invalid Microsoft ID token.'));
    }
}
