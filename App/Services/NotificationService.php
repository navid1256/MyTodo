<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotificationNotFoundException;
use App\Exceptions\NotificationValidationException;
use App\Helpers\TimezoneHelper;
use App\Repositories\NotificationRepository;
use DateTimeImmutable;
use PDOException;
use Throwable;

final class NotificationService
{
    private const ALLOWED_STATUSES = ['pending', 'failed', 'sent', 'cancelled'];

    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly ReminderService $reminderService
    ) {}

    /**
     * @return array<int, object>
     */
    public function getNotificationsForUser(int $userId, ?string $status = null): array
    {
        if ($status !== null && !in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new NotificationValidationException('Invalid notification status.');
        }

        return $this->notificationRepository->getNotificationsForUser($userId, $status);
    }

    public function countSentNotifications(int $userId): int
    {
        return $this->notificationRepository->countSentNotifications($userId);
    }

    public function updateNotification(
        int $notificationId,
        int $userId,
        int $offsetValue,
        string $offsetUnit
    ): object {
        if ($notificationId < 1) {
            throw new NotificationValidationException('Invalid notification.');
        }

        $normalizedUnit = strtolower(trim($offsetUnit));
        $this->notificationRepository->beginTransaction();

        try {
            $notification = $this->notificationRepository->findTaskReminderForUser($notificationId, $userId);
            if ($notification === null) {
                throw new NotificationNotFoundException('Notification not found.');
            }

            if (!in_array($notification->status, ['pending', 'failed'], true)) {
                throw new NotificationValidationException('Only pending or failed notifications can be edited.');
            }

            if (empty($notification->due_at) || !(bool) $notification->has_time) {
                throw new NotificationValidationException('This task no longer has a due date and time.');
            }

            $dueAt = new DateTimeImmutable((string) $notification->due_at, TimezoneHelper::getApplicationTimezone());
            $preparedReminders = $this->reminderService->prepareTaskReminders(
                [['value' => $offsetValue, 'unit' => $normalizedUnit]],
                $dueAt,
                true
            );
            $preparedReminder = $preparedReminders[0];
            $formattedRemindAt = $preparedReminder['remind_at']->format('Y-m-d H:i:s');

            $this->notificationRepository->update(
                notificationId: $notificationId,
                offsetValue: $preparedReminder['offset_value'],
                offsetUnit: $preparedReminder['offset_unit'],
                remindAt: $formattedRemindAt
            );

            $this->notificationRepository->commit();

            return (object) [
                'id' => $notificationId,
                'offset_value' => $preparedReminder['offset_value'],
                'offset_unit' => $preparedReminder['offset_unit'],
                'remind_at' => $formattedRemindAt,
                'formatted_remind_at' => TimezoneHelper::formatNotificationDate($formattedRemindAt),
                'status' => 'pending',
            ];
        } catch (PDOException $exception) {
            if ($this->notificationRepository->inTransaction()) {
                $this->notificationRepository->rollBack();
            }

            if ($exception->getCode() === '23000') {
                throw new NotificationValidationException('This task already has a notification at that time.');
            }

            throw $exception;
        } catch (Throwable $exception) {
            if ($this->notificationRepository->inTransaction()) {
                $this->notificationRepository->rollBack();
            }

            throw $exception;
        }
    }

    public function cancelNotification(int $notificationId, int $userId): bool
    {
        if ($notificationId < 1) {
            throw new NotificationValidationException('Invalid notification.');
        }

        return $this->notificationRepository->cancel($notificationId, $userId);
    }
}
