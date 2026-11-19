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

        $doseRemindersEnabled = $this->parseNullableBool($data, 'doseRemindersEnabled');
        if ($doseRemindersEnabled instanceof JsonResponse) {
            return $doseRemindersEnabled;
        }
        $missedDoseNudgesEnabled = $this->parseNullableBool($data, 'missedDoseNudgesEnabled');
        if ($missedDoseNudgesEnabled instanceof JsonResponse) {
            return $missedDoseNudgesEnabled;
        }
        $refillAlertsEnabled = $this->parseNullableBool($data, 'refillAlertsEnabled');
        if ($refillAlertsEnabled instanceof JsonResponse) {
            return $refillAlertsEnabled;
        }
        $weeklyStreakSummariesEnabled = $this->parseNullableBool($data, 'weeklyStreakSummariesEnabled');
        if ($weeklyStreakSummariesEnabled instanceof JsonResponse) {
            return $weeklyStreakSummariesEnabled;
        }
        $inAppBannersEnabled = $this->parseNullableBool($data, 'inAppBannersEnabled');
        if ($inAppBannersEnabled instanceof JsonResponse) {
            return $inAppBannersEnabled;
        }
        $alarmRemindersEnabled = $this->parseNullableBool($data, 'alarmRemindersEnabled');
        if ($alarmRemindersEnabled instanceof JsonResponse) {
            return $alarmRemindersEnabled;
        }

        $reminderMinutesBefore = null;
        if (array_key_exists('reminderMinutesBefore', $data) && $data['reminderMinutesBefore'] !== null) {
            if (!is_int($data['reminderMinutesBefore']) || $data['reminderMinutesBefore'] < 0) {
                return $this->respondWithFailure(Failure::validation('reminderMinutesBefore must be a non-negative integer.'));
            }
            $reminderMinutesBefore = $data['reminderMinutesBefore'];
        }

        $command = new UpdatePreferencesCommand(
            $this->getAuthenticatedUserId()->value(),
            $doseRemindersEnabled,
            $missedDoseNudgesEnabled,
            $refillAlertsEnabled,
            $weeklyStreakSummariesEnabled,
            $inAppBannersEnabled,
            $reminderMinutesBefore,
            $alarmRemindersEnabled
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result);
    }

    #[Route('/profiles/{id}/notifications/{doseEventId}/cancel', name: 'cancel_notification', methods: ['POST', 'DELETE'])]
    public function cancelNotification(string $id, string $doseEventId, Request $request): JsonResponse
    {
        $data = [];
        if ($request->getContent() !== '') {
            $decoded = $this->decodeObject($request);
            if ($decoded instanceof JsonResponse) {
                return $decoded;
            }
            $data = $decoded;
        }

        $cancelPush = $this->parseOptionalBool($data, 'cancelPush', true);
        if ($cancelPush instanceof JsonResponse) {
            return $cancelPush;
        }
        $cancelCalendar = $this->parseOptionalBool($data, 'cancelCalendar', true);
        if ($cancelCalendar instanceof JsonResponse) {
            return $cancelCalendar;
        }
        $cancelAlarm = $this->parseOptionalBool($data, 'cancelAlarm', $cancelPush);
        if ($cancelAlarm instanceof JsonResponse) {
            return $cancelAlarm;
        }

        $command = new \Notification\Application\Command\CancelNotificationCommand(
            $id,
            $this->getAuthenticatedUserId()->value(),
            $doseEventId,
            $cancelPush,
            $cancelCalendar,
            $cancelAlarm
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result);
    }

    #[Route('/profiles/{id}/notifications/cancel-recurring', name: 'cancel_recurring_notifications', methods: ['POST'])]
    public function cancelRecurringNotifications(string $id, Request $request): JsonResponse
    {
        $data = [];
        if ($request->getContent() !== '') {
            $decoded = $this->decodeObject($request);
            if ($decoded instanceof JsonResponse) {
                return $decoded;
            }
            $data = $decoded;
        }

        $scheduleId = is_string($data['scheduleId'] ?? null) && $data['scheduleId'] !== '' ? $data['scheduleId'] : null;
        $medicationId = is_string($data['medicationId'] ?? null) && $data['medicationId'] !== '' ? $data['medicationId'] : null;
        $cancelPush = $this->parseOptionalBool($data, 'cancelPush', true);
        if ($cancelPush instanceof JsonResponse) {
            return $cancelPush;
        }
        $cancelCalendar = $this->parseOptionalBool($data, 'cancelCalendar', true);
        if ($cancelCalendar instanceof JsonResponse) {
            return $cancelCalendar;
        }
        $deleteSchedule = $this->parseOptionalBool($data, 'deleteSchedule', false);
        if ($deleteSchedule instanceof JsonResponse) {
            return $deleteSchedule;
        }
        $cancelAlarm = $this->parseOptionalBool($data, 'cancelAlarm', $cancelPush);
        if ($cancelAlarm instanceof JsonResponse) {
            return $cancelAlarm;
        }

        $command = new \Notification\Application\Command\CancelRecurringNotificationsCommand(
            $id,
            $this->getAuthenticatedUserId()->value(),
            $scheduleId,
            $medicationId,
            $cancelPush,
            $cancelCalendar,
            $deleteSchedule,
            $cancelAlarm
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result);
    }

    #[Route('/profiles/{id}/schedules/{sid}/cancel-recurring', name: 'cancel_schedule_recurring_notifications', methods: ['POST', 'DELETE'])]
    public function cancelScheduleRecurringNotifications(string $id, string $sid, Request $request): JsonResponse
    {
        $data = [];
        if ($request->getContent() !== '') {
            $decoded = $this->decodeObject($request);
            if ($decoded instanceof JsonResponse) {
                return $decoded;
            }
            $data = $decoded;
        }

        $cancelPush = $this->parseOptionalBool($data, 'cancelPush', true);
        if ($cancelPush instanceof JsonResponse) {
            return $cancelPush;
        }
        $cancelCalendar = $this->parseOptionalBool($data, 'cancelCalendar', true);
        if ($cancelCalendar instanceof JsonResponse) {
            return $cancelCalendar;
        }
        $deleteSchedule = $this->parseOptionalBool($data, 'deleteSchedule', false);
        if ($deleteSchedule instanceof JsonResponse) {
            return $deleteSchedule;
        }
        $cancelAlarm = $this->parseOptionalBool($data, 'cancelAlarm', $cancelPush);
        if ($cancelAlarm instanceof JsonResponse) {
            return $cancelAlarm;
        }

        $command = new \Notification\Application\Command\CancelRecurringNotificationsCommand(
            $id,
            $this->getAuthenticatedUserId()->value(),
            $sid,
            null,
            $cancelPush,
            $cancelCalendar,
            $deleteSchedule,
            $cancelAlarm
        );

        $result = $this->commandBus->dispatch($command);

        return $this->respond($result);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseNullableBool(array $data, string $field): bool|null|JsonResponse
    {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            return null;
        }

        if (!is_bool($data[$field])) {
            return $this->respondWithFailure(Failure::validation(sprintf('%s must be a boolean.', $field)));
        }

        return $data[$field];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseOptionalBool(array $data, string $field, bool $default): bool|JsonResponse
    {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            return $default;
        }

        if (!is_bool($data[$field])) {
            return $this->respondWithFailure(Failure::validation(sprintf('%s must be a boolean.', $field)));
        }

        return $data[$field];
    }
}
