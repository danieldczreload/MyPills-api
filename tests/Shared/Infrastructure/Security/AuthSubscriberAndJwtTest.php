<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Security;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Security\AuthenticationSubscriber;
use Shared\Infrastructure\Security\CurrentUserContext;
use Shared\Infrastructure\Security\JwtService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class AuthSubscriberAndJwtTest extends TestCase
{
    public function testJwtServiceEncodeDecodeAndExpired(): void
    {
        $jwt = new JwtService('super-secret-key-12345');
        $token = $jwt->createToken(['sub' => 'user-123', 'role' => 'admin'], 3600);

        self::assertNotEmpty($token);
        $decoded = $jwt->decodeToken($token);
        self::assertNotNull($decoded);
        self::assertSame('user-123', $decoded['sub']);
        self::assertSame('admin', $decoded['role']);

        // Invalid format
        self::assertNull($jwt->decodeToken('invalid.token'));

        // Tampered token
        $tampered = $token . 'corrupted';
        self::assertNull($jwt->decodeToken($tampered));

        // Expired token (ttl = -10)
        $expiredToken = $jwt->createToken(['sub' => 'user-expired'], -10);
        self::assertNull($jwt->decodeToken($expiredToken));
    }

    public function testAuthenticationSubscriberWithValidBearerToken(): void
    {
        $jwt = new JwtService('secret');
        $token = $jwt->createToken(['sub' => '123e4567-e89b-12d3-a456-426614174000']);
        $context = new CurrentUserContext();
        $subscriber = new AuthenticationSubscriber($jwt, $context);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/v1/profiles');
        $request->headers->set('Authorization', 'Bearer ' . $token);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
        self::assertSame('123e4567-e89b-12d3-a456-426614174000', $context->getUserId()?->value());
    }

    public function testAuthenticationSubscriberMissingHeaderReturns401(): void
    {
        $jwt = new JwtService('secret');
        $context = new CurrentUserContext();
        $subscriber = new AuthenticationSubscriber($jwt, $context);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/v1/profiles');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertNotNull($event->getResponse());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $event->getResponse()->getStatusCode());
    }

    public function testAuthenticationSubscriberBypassesAuthRoutes(): void
    {
        $jwt = new JwtService('secret');
        $context = new CurrentUserContext();
        $subscriber = new AuthenticationSubscriber($jwt, $context);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/v1/auth/google');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }
}
