<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Security;

use Identity\Infrastructure\Security\FakeGoogleIdentityProvider;
use Identity\Infrastructure\Security\FakeMicrosoftIdentityProvider;
use Identity\Infrastructure\Security\HttpGoogleIdentityProvider;
use Identity\Infrastructure\Security\HttpMicrosoftIdentityProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class IdentityProviderTest extends TestCase
{
    public function testFakeGoogleIdentityProvider(): void
    {
        $fake = new FakeGoogleIdentityProvider();
        $res = $fake->verifyToken('valid-user@example.com');
        self::assertTrue($res->isSuccess());
        self::assertSame('user@example.com', $res->getValue()->email);

        $fail = $fake->verifyToken('invalid-token');
        self::assertTrue($fail->isFailure());
    }

    public function testFakeMicrosoftIdentityProvider(): void
    {
        $fake = new FakeMicrosoftIdentityProvider();
        $res = $fake->verifyToken('valid-ms@example.com');
        self::assertTrue($res->isSuccess());
        self::assertSame('ms@example.com', $res->getValue()->email);

        $fail = $fake->verifyToken('invalid-token');
        self::assertTrue($fail->isFailure());
    }

    public function testHttpGoogleIdentityProviderValidShortCircuit(): void
    {
        $http = new MockHttpClient();
        $provider = new HttpGoogleIdentityProvider($http, 'expected-client-id', '', 'dev');
        $res = $provider->verifyToken('valid-alice@example.com');
        self::assertTrue($res->isSuccess());
        self::assertSame('alice@example.com', $res->getValue()->email);
    }

    public function testHttpGoogleIdentityProviderValidShortCircuitRejectedInProd(): void
    {
        $http = new MockHttpClient();
        $provider = new HttpGoogleIdentityProvider($http, 'expected-client-id', '', 'prod');
        $res = $provider->verifyToken('valid-alice@example.com');
        self::assertTrue($res->isFailure());
    }

    public function testHttpGoogleIdentityProviderApiSuccessAndAudienceMismatch(): void
    {
        $responses = [
            // Success response
            new MockResponse(json_encode([
                'sub' => 'google-12345',
                'email' => 'bob@example.com',
                'name' => 'Bob Smith',
                'aud' => 'my-client-id',
            ], JSON_THROW_ON_ERROR)),
            // Audience mismatch response
            new MockResponse(json_encode([
                'sub' => 'google-12345',
                'email' => 'bob@example.com',
                'name' => 'Bob Smith',
                'aud' => 'other-client-id',
            ], JSON_THROW_ON_ERROR)),
            // HTTP error response
            new MockResponse('Unauthorized', ['http_code' => 401]),
        ];

        $http = new MockHttpClient($responses);
        $provider = new HttpGoogleIdentityProvider($http, 'my-client-id');

        // 1. Success
        $res1 = $provider->verifyToken('real-token-1');
        self::assertTrue($res1->isSuccess());
        self::assertSame('bob@example.com', $res1->getValue()->email);

        // 2. Audience mismatch
        $res2 = $provider->verifyToken('real-token-2');
        self::assertTrue($res2->isFailure());
        self::assertStringContainsString('audience mismatch', $res2->getFailure()->getMessage());

        // 3. HTTP 401
        $res3 = $provider->verifyToken('real-token-3');
        self::assertTrue($res3->isFailure());
    }

    public function testHttpMicrosoftIdentityProviderApiSuccessAndErrors(): void
    {
        $responses = [
            new MockResponse(json_encode([
                'id' => 'ms-12345',
                'mail' => 'carol@example.com',
                'displayName' => 'Carol Danvers',
            ], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'id' => 'ms-67890',
                'userPrincipalName' => 'dan@example.com',
            ], JSON_THROW_ON_ERROR)),
            new MockResponse('Unauthorized', ['http_code' => 401]),
        ];

        $http = new MockHttpClient($responses);
        $provider = new HttpMicrosoftIdentityProvider($http);

        // 1. Success with mail
        $res1 = $provider->verifyToken('token-1');
        self::assertTrue($res1->isSuccess());
        self::assertSame('carol@example.com', $res1->getValue()->email);

        // 2. Success with userPrincipalName
        $res2 = $provider->verifyToken('token-2');
        self::assertTrue($res2->isSuccess());
        self::assertSame('dan@example.com', $res2->getValue()->email);

        // 3. HTTP 401
        $res3 = $provider->verifyToken('token-3');
        self::assertTrue($res3->isFailure());
    }
}
