<?php

declare(strict_types=1);

namespace Identity\Infrastructure\Security;

use Identity\Domain\ExternalUser;
use Identity\Domain\MicrosoftIdentityProvider;
use Shared\Domain\Failure;
use Shared\Domain\Result;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpMicrosoftIdentityProvider implements MicrosoftIdentityProvider
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
    }

    public function verifyToken(string $idToken): Result
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                'https://graph.microsoft.com/v1.0/me',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $idToken,
                    ],
                ]
            );

            if ($response->getStatusCode() !== 200) {
                return Result::failure(Failure::unauthorized('Invalid Microsoft access token response from Graph API.'));
            }

            $data = $response->toArray();

            $externalId = $data['id'] ?? null;
            $email = $data['mail'] ?? $data['userPrincipalName'] ?? null;
            $name = $data['displayName'] ?? $email ?? 'Microsoft User';

            if ($externalId === null || $email === null) {
                return Result::failure(Failure::unauthorized('Missing user payload in Microsoft Graph API response.'));
            }

            return Result::success(new ExternalUser(
                (string) $externalId,
                (string) $email,
                (string) $name
            ));
        } catch (\Throwable $e) {
            return Result::failure(Failure::unauthorized('Failed to verify Microsoft token: ' . $e->getMessage()));
        }
    }
}
