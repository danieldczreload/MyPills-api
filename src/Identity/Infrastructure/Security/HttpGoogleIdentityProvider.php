<?php

declare(strict_types=1);

namespace Identity\Infrastructure\Security;

use Identity\Domain\ExternalUser;
use Identity\Domain\GoogleIdentityProvider;
use Shared\Domain\Failure;
use Shared\Domain\Result;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpGoogleIdentityProvider implements GoogleIdentityProvider
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $clientId = ''
    ) {
    }

    public function verifyToken(string $idToken): Result
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                'https://oauth2.googleapis.com/tokeninfo',
                [
                    'query' => [
                        'id_token' => $idToken,
                    ],
                ]
            );

            if ($response->getStatusCode() !== 200) {
                return Result::failure(Failure::unauthorized('Invalid Google ID token response from Google API.'));
            }

            $data = $response->toArray();

            if ($this->clientId !== '' && isset($data['aud']) && $data['aud'] !== $this->clientId) {
                return Result::failure(Failure::unauthorized('Google ID token audience mismatch.'));
            }

            $externalId = $data['sub'] ?? null;
            $email = $data['email'] ?? null;
            $name = $data['name'] ?? $email ?? 'Google User';

            if ($externalId === null || $email === null) {
                return Result::failure(Failure::unauthorized('Missing user payload in Google ID token.'));
            }

            return Result::success(new ExternalUser(
                (string) $externalId,
                (string) $email,
                (string) $name
            ));
        } catch (\Throwable $e) {
            return Result::failure(Failure::unauthorized('Failed to verify Google ID token: ' . $e->getMessage()));
        }
    }
}
