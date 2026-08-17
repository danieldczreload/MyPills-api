<?php

declare(strict_types=1);

namespace Notification\UI\Http;

use Notification\Application\Command\RegisterDeviceCommand;
use Notification\Application\Command\DeregisterDeviceCommand;
use Notification\Application\Command\SendPushNotificationCommand;
use Notification\Application\Command\UpdatePreferencesCommand;
use Shared\Domain\Failure;
use Shared\UI\Http\ApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Notification\Application\Query\GetPreferencesQuery;

#[Route('/api/v1', name: 'api_notification_')]
final class NotificationController extends ApiController
{
    #[Route('/notifications/preferences', name: 'get_preferences', methods: ['GET'])]
    public function getPreferences(): JsonResponse
    {
        $query = new GetPreferencesQuery($this->getAuthenticatedUserId()->value());

        return $this->respond($this->queryBus->ask($query));
    }

    #[Route('/devices', name: 'register_device', methods: ['POST'])]
    public function registerDevice(Request $request): JsonResponse
    {
        $data = $this->decodeObject($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

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

    #[Route('/devices/{deviceId}', name: 'deregister_device', methods: ['DELETE'])]
    public function deregisterDevice(string $deviceId): JsonResponse
    {
        $command = new DeregisterDeviceCommand($deviceId, $this->getAuthenticatedUserId()->value());
        $result = $this->commandBus->dispatch($command);

        return $this->respond($result, Response::HTTP_NO_CONTENT);
    }

    #[Route('/notifications/test-push', name: 'test_push', methods: ['POST'])]
    public function testPush(Request $request): JsonResponse
    {
        $data = $this->decodeObject($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $title = is_string($data['title'] ?? null) ? $data['title'] : '';
        $body = is_string($data['body'] ?? null) ? $data['body'] : '';
        /** @var array<string, mixed> $payload */
        $payload = is_array($data['data'] ?? null) ? $data['data'] : [];

        $command = new SendPushNotificationCommand(
            $this->getAuthenticatedUserId()->value(),
            $title,
            $body,
            $payload
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result);
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    private function decodeObject(Request $request): array|JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->respondWithFailure(Failure::validation('Request body must contain valid JSON.'));
        }

        if (!is_object($data)) {
            return $this->respondWithFailure(Failure::validation('Request body must contain a JSON object.'));
        }

        return get_object_vars($data);
    }

    #[Route('/notifications/preferences', name: 'update_preferences', methods: ['PATCH'])]
    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $this->decodeObject($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $command = new UpdatePreferencesCommand(
            $this->getAuthenticatedUserId()->value(),
            array_key_exists('doseRemindersEnabled', $data) ? (bool) $data['doseRemindersEnabled'] : null,
            array_key_exists('missedDoseNudgesEnabled', $data) ? (bool) $data['missedDoseNudgesEnabled'] : null,
            array_key_exists('refillAlertsEnabled', $data) ? (bool) $data['refillAlertsEnabled'] : null,
            array_key_exists('weeklyStreakSummariesEnabled', $data) ? (bool) $data['weeklyStreakSummariesEnabled'] : null,
            array_key_exists('inAppBannersEnabled', $data) ? (bool) $data['inAppBannersEnabled'] : null,
            array_key_exists('reminderMinutesBefore', $data) && is_numeric($data['reminderMinutesBefore']) ? (int) $data['reminderMinutesBefore'] : null
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result);
    }
}
