<?php

declare(strict_types=1);

namespace Identity\Infrastructure\Security;

use Identity\Domain\ExternalUser;
use Identity\Domain\GoogleIdentityProvider;
use Shared\Domain\Result;
use Shared\Domain\Failure;

final class FakeGoogleIdentityProvider implements GoogleIdentityProvider
{
    public function verifyToken(string $idToken): Result
    {
        if (str_starts_with($idToken, 'valid-')) {
            $email = substr($idToken, 6);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = 'google-user@example.com';
            }
            $externalId = 'google-' . md5($email);

            return Result::success(new ExternalUser(
                $externalId,
                $email,
                'Google User'
            ));
        }

        return Result::failure(Failure::unauthorized('Invalid Google ID token.'));
    }
}
