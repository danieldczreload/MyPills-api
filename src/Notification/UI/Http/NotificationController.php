<?php

declare(strict_types=1);

namespace Notification\UI\Http;

use Notification\Application\Command\RegisterDeviceCommand;
use Notification\Application\Command\DeregisterDeviceCommand;
use Notification\Application\Command\UpdatePreferencesCommand;
use Shared\UI\Http\ApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1', name: 'api_notification_')]
final class NotificationController extends ApiController
{
    #[Route('/devices', name: 'register_device', methods: ['POST'])]
    public function registerDevice(Request $request): JsonResponse
    {
        /** @var array{fcmToken?: mixed, platform?: mixed, locale?: mixed} $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $fcmToken = is_string($data['fcmToken'] ?? null) ? $data['fcmToken'] : '';
        $platform = is_string($data['platform'] ?? null) ? $data['platform'] : '';
        $locale = is_string($data['locale'] ?? null) ? $data['locale'] : '';

        $command = new RegisterDeviceCommand(
            $this->getAuthenticatedUserId()->value(),
            $fcmToken,
            $platform,
            $locale
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result, Response::HTTP_CREATED);
    }

    #[Route('/devices/{token}', name: 'deregister_device', methods: ['DELETE'])]
    public function deregisterDevice(string $token): JsonResponse
    {
        $command = new DeregisterDeviceCommand($token, $this->getAuthenticatedUserId()->value());
        $result = $this->commandBus->dispatch($command);

        return $this->respond($result, Response::HTTP_NO_CONTENT);
    }

    #[Route('/notifications/preferences', name: 'update_preferences', methods: ['PATCH'])]
    public function updatePreferences(Request $request): JsonResponse
    {
        /** @var array{
         *     doseRemindersEnabled?: mixed,
         *     missedDoseNudgesEnabled?: mixed,
         *     refillAlertsEnabled?: mixed,
         *     weeklyStreakSummariesEnabled?: mixed
         * } $data */
        $data = json_decode($request->getContent(), true) ?? [];

        $command = new UpdatePreferencesCommand(
            $this->getAuthenticatedUserId()->value(),
            (bool) ($data['doseRemindersEnabled'] ?? true),
            (bool) ($data['missedDoseNudgesEnabled'] ?? true),
            (bool) ($data['refillAlertsEnabled'] ?? true),
            (bool) ($data['weeklyStreakSummariesEnabled'] ?? true)
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result);
    }
}
