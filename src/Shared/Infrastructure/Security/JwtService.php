<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Security;

final class JwtService
{
    public function __construct(
        private readonly string $secret
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createToken(array $payload, int $ttl = 3600): string
    {
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR);

        $payload['exp'] = time() + $ttl;
        $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);

        $base64Header = $this->base64UrlEncode($header);
        $base64Payload = $this->base64UrlEncode($payloadJson);

        $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, $this->secret, true);
        $base64Signature = $this->base64UrlEncode($signature);

        return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodeToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$base64Header, $base64Payload, $base64Signature] = $parts;

        $signature = $this->base64UrlDecode($base64Signature);
        $expectedSignature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, $this->secret, true);

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payloadJson = $this->base64UrlDecode($base64Payload);
        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }
}
