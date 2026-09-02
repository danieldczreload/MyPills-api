<?php

declare(strict_types=1);

namespace CalendarIntegration\Infrastructure;

use CalendarIntegration\Domain\CalendarAuthorizationRevoked;
use CalendarIntegration\Domain\CalendarOAuthTokens;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Shared OAuth 2.0 token-endpoint flow for calendar providers. Providers
 * supply their token URL, extra body fields, and display name; the request,
 * validation, and revoked-grant detection live here once.
 */
final class OAuthTokenEndpoint
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * @param array<string, string> $extraBody
     */
    public function exchangeAuthorizationCode(
        string $tokenUrl,
        string $providerName,
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        string $code,
        string $codeVerifier,
        array $extraBody = []
    ): CalendarOAuthTokens {
        $body = array_merge([
            'code' => $code,
            'code_verifier' => $codeVerifier,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ], $extraBody);

        $response = $this->request($tokenUrl, $clientId, $clientSecret, $body);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('%s OAuth code exchange failed with status %d.', $providerName, $response->getStatusCode()));
        }

        return $this->tokensFromResponse($response, $providerName);
    }

    public function refreshAccessToken(
        string $tokenUrl,
        string $providerName,
        string $clientId,
        string $clientSecret,
        string $refreshToken
    ): CalendarOAuthTokens {
        $response = $this->request($tokenUrl, $clientId, $clientSecret, [
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->getStatusCode() !== 200) {
            if ($this->isInvalidGrant($response)) {
                throw new CalendarAuthorizationRevoked(sprintf('%s Calendar authorization was revoked.', $providerName));
            }

            throw new \RuntimeException(sprintf('Failed to refresh %s OAuth token. Status: %d.', $providerName, $response->getStatusCode()));
        }

        return $this->tokensFromResponse($response, $providerName);
    }

    /**
     * @param array<string, string> $body
     */
    private function request(string $tokenUrl, string $clientId, string $clientSecret, array $body): ResponseInterface
    {
        $body['client_id'] = $clientId;

        if ($clientSecret !== '') {
            $body['client_secret'] = $clientSecret;
        }

        $response = $this->httpClient->request('POST', $tokenUrl, ['body' => $body]);
        $this->logTokenResponse($tokenUrl, $body['grant_type'] ?? 'unknown', $response);

        return $response;
    }

    private function logTokenResponse(string $tokenUrl, string $grantType, ResponseInterface $response): void
    {
        if ($this->logger === null) {
            return;
        }

        $decoded = json_decode($response->getContent(false), true);
        $error = is_array($decoded) && is_string($decoded['error'] ?? null) ? $decoded['error'] : null;
        $errorDescription = is_array($decoded) && is_string($decoded['error_description'] ?? null)
            ? $decoded['error_description']
            : null;

        $status = $response->getStatusCode();
        $hasAccessToken = is_array($decoded) && isset($decoded['access_token']);
        $hasRefreshToken = is_array($decoded) && isset($decoded['refresh_token']);
        error_log(sprintf(
            'GOOGLE_OAUTH grant=%s status=%d error=%s errorDescription=%s hasAccessToken=%s hasRefreshToken=%s',
            $grantType,
            $status,
            $error ?? '-',
            $errorDescription ?? '-',
            $hasAccessToken ? 'yes' : 'no',
            $hasRefreshToken ? 'yes' : 'no'
        ));
        $this->logger->info('Calendar OAuth token endpoint response.', [
            'url' => $tokenUrl,
            'grantType' => $grantType,
            'status' => $status,
            'error' => $error,
            'errorDescription' => $errorDescription,
            'hasAccessToken' => $hasAccessToken,
            'hasRefreshToken' => $hasRefreshToken,
        ]);
    }

    private function tokensFromResponse(ResponseInterface $response, string $providerName): CalendarOAuthTokens
    {
        $data = $response->toArray();
        if (!is_string($data['access_token'] ?? null) || $data['access_token'] === '') {
            throw new \RuntimeException(sprintf('%s OAuth response did not contain an access token.', $providerName));
        }

        $refreshToken = $data['refresh_token'] ?? null;
        if ($refreshToken !== null && !is_string($refreshToken)) {
            throw new \RuntimeException(sprintf('%s OAuth response contained an invalid refresh token.', $providerName));
        }

        return new CalendarOAuthTokens($data['access_token'], $refreshToken);
    }

    private function isInvalidGrant(ResponseInterface $response): bool
    {
        try {
            return ($response->toArray(false)['error'] ?? null) === 'invalid_grant';
        } catch (\Throwable) {
            return false;
        }
    }
}
