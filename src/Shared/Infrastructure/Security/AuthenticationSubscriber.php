<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Security;

use Shared\Domain\ValueObject\UserId;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class AuthenticationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly CurrentUserContext $currentUserContext
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 31], // Run before routing
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Only authenticate under /api/v1/ except /api/v1/auth/
        if (!str_starts_with($path, '/api/v1/') || str_starts_with($path, '/api/v1/auth/')) {
            return;
        }

        $authHeader = $request->headers->get('Authorization');
        if ($authHeader === null || !str_starts_with($authHeader, 'Bearer ')) {
            $this->respondUnauthorized($event, 'Missing or malformed Authorization header.');
            return;
        }

        $token = substr($authHeader, 7);
        $payload = $this->jwtService->decodeToken($token);

        $sub = $payload['sub'] ?? null;
        if (!is_string($sub) && !is_int($sub)) {
            $this->respondUnauthorized($event, 'Invalid token payload.');
            return;
        }
        $userId = new UserId((string) $sub);
        $this->currentUserContext->setUserId($userId);
    }

    private function respondUnauthorized(RequestEvent $event, string $message): void
    {
        $response = new JsonResponse([
            'error' => [
                'type' => 'UNAUTHORIZED',
                'message' => $message,
                'details' => [],
            ],
        ], Response::HTTP_UNAUTHORIZED);

        $event->setResponse($response);
    }
}
