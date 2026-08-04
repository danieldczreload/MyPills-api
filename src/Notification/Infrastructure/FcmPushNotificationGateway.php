<?php

declare(strict_types=1);

namespace Notification\Infrastructure;

use Notification\Domain\PushNotificationGateway;
use Notification\Domain\InvalidDeviceToken;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FcmPushNotificationGateway implements PushNotificationGateway
{
    private ?string $accessToken = null;

    private int $accessTokenExpiresAt = 0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $projectId = '',
        private readonly string $clientEmail = '',
        private readonly string $privateKey = ''
    ) {
    }

    public function send(string $token, string $title, string $body, array $data = []): void
    {
        if ($this->projectId === '' || $this->clientEmail === '' || $this->privateKey === '') {
            throw new \LogicException('Firebase service-account credentials are not configured.');
        }

        if (trim($token) === '' || trim($title) === '' || trim($body) === '') {
            throw new \InvalidArgumentException('FCM token, title and body are required.');
        }

        /** @var array<string, string> $normalizedData */
        $normalizedData = [];
        foreach ($data as $key => $value) {
            if (!is_scalar($value)) {
                throw new \InvalidArgumentException('FCM data keys and values must be scalar.');
            }

            $normalizedData[$key] = (string) $value;
        }

        $accessToken = $this->getAccessToken();
        $response = $this->httpClient->request(
            'POST',
            sprintf(
                'https://fcm.googleapis.com/v1/projects/%s/messages:send',
                rawurlencode($this->projectId)
            ),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => $normalizedData,
                        'android' => [
                            'priority' => 'HIGH',
                            'notification' => [
                                'sound' => 'default',
                            ],
                        ],
                        'apns' => [
                            'payload' => [
                                'aps' => [
                                    'sound' => 'default',
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );

        if ($response->getStatusCode() !== 200) {
            $error = $response->toArray(false)['error'] ?? [];
            $errorStatus = is_array($error) && is_string($error['status'] ?? null) ? $error['status'] : null;

            if ($errorStatus === 'NOT_FOUND' || $this->hasUnregisteredToken($error)) {
                throw new InvalidDeviceToken('Firebase rejected the device token.');
            }

            throw new \RuntimeException(sprintf('FCM Push notification failed with status %d.', $response->getStatusCode()));
        }
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null && $this->accessTokenExpiresAt > time() + 60) {
            return $this->accessToken;
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $this->clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));
        $unsignedToken = $header . '.' . $claims;
        $signature = '';
        $privateKey = str_replace('\\n', "\n", $this->privateKey);

        if (!openssl_sign($unsignedToken, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Unable to sign Firebase service-account assertion.');
        }

        $response = $this->httpClient->request(
            'POST',
            'https://oauth2.googleapis.com/token',
            [
                'body' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $unsignedToken . '.' . $this->base64UrlEncode($signature),
                ],
            ]
        );

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Firebase OAuth token request failed with status %d.', $response->getStatusCode()));
        }

        $responseData = $response->toArray();
        $accessToken = $responseData['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new \RuntimeException('Firebase OAuth response did not contain an access token.');
        }

        $expiresIn = is_int($responseData['expires_in'] ?? null) ? $responseData['expires_in'] : 3600;
        $this->accessToken = $accessToken;
        $this->accessTokenExpiresAt = $now + $expiresIn;

        return $this->accessToken;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @param mixed $error
     */
    private function hasUnregisteredToken(mixed $error): bool
    {
        if (!is_array($error) || !is_array($error['details'] ?? null)) {
            return false;
        }

        foreach ($error['details'] as $detail) {
            if (is_array($detail) && ($detail['errorCode'] ?? null) === 'UNREGISTERED') {
                return true;
            }
        }

        return false;
    }
}
