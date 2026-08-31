<?php

declare(strict_types=1);

use App\Helpers\TimezoneHelper;

/** @var array<int, object> $notifications */
/** @var array<string, string>|null $notificationStatusLabels */
/** @var \Closure|null $formatNotificationDate */
/** @var \Closure|null $formatNotificationOffset */
/** @var string $csrfToken */
/** @var DateTimeZone|null $notificationTimezone */

$notifications = isset($notifications) && is_array($notifications) ? $notifications : [];
$statusLabels = $notificationStatusLabels ?? [
    'pending' => 'Pending',
    'sent' => 'Sent',
    'failed' => 'Failed',
    'cancelled' => 'Cancelled',
];
$csrfToken = isset($csrfToken) && is_string($csrfToken) ? $csrfToken : '';

$storageTimezone = TimezoneHelper::getApplicationTimezone();
$userTimezone = $notificationTimezone ?? TimezoneHelper::getClientTimezone();
$dateFormatter = $formatNotificationDate ?? static function (?string $dateTime) use ($storageTimezone, $userTimezone): string {
    if ($dateTime === null || trim($dateTime) === '') {
        return 'Not available';
    }

    return (new DateTimeImmutable($dateTime, $storageTimezone))
        ->setTimezone($userTimezone)
        ->format('M j, Y \a\t h:i A');
};

$offsetFormatter = $formatNotificationOffset ?? static function (int $value, string $unit): string {
    if ($value === 0) {
        return 'On due time';
    }

    return $value . ' ' . $unit . ($value === 1 ? '' : 's') . ' before due time';
};
?>
<div class="content notificationsContent">
    <section class="list notificationsPanel" aria-labelledby="notificationsPageTitle">
        <div class="title notificationsTitle" id="notificationsPageTitle">
            <span>Notifications</span>
        </div>
        <output class="notificationsPageMessage" id="notificationsPageMessage" aria-live="polite" hidden></output>
        <ul class="notificationsList" id="notificationsList">
            <?php if (empty($notifications)): ?>
                <li class="emptyTask notificationsEmpty">No notifications found.</li>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <?php
                    $rawStatus = (string) ($notification->status ?? 'failed');
                    $status = array_key_exists($rawStatus, $statusLabels) ? $rawStatus : 'failed';
                    $canManageNotification = in_array($status, ['pending', 'failed'], true);
                    $offsetValue = (int) ($notification->offset_value ?? 0);
                    $offsetUnit = (string) ($notification->offset_unit ?? 'minute');
                    $taskTitle = (string) ($notification->task_title ?? 'Task');
                    ?>
                    <li class="notificationItem" data-notification-id="<?= (int) ($notification->id ?? 0) ?>">
                        <div class="notificationItemIcon" aria-hidden="true">
                            <i class="fa-regular fa-bell"></i>
                        </div>
                        <div class="notificationItemContent">
                            <div class="notificationItemHeading">
                                <h2><?= htmlspecialchars($taskTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                                <span class="notificationStatus notificationStatus--<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" data-notification-status>
                                    <?= htmlspecialchars($statusLabels[$status], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <div class="notificationDetails">
                                <p>
                                    <span>Notification time</span>
                                    <strong data-notification-schedule><?= htmlspecialchars($dateFormatter((string) ($notification->remind_at ?? '')), ENT_QUOTES, 'UTF-8') ?></strong>
                                </p>
                                <p>
                                    <span>Task due time</span>
                                    <strong><?= htmlspecialchars($dateFormatter((string) ($notification->task_due_at ?? '')), ENT_QUOTES, 'UTF-8') ?></strong>
                                </p>
                                <p>
                                    <span>Reminder</span>
                                    <strong data-notification-offset><?= htmlspecialchars($offsetFormatter($offsetValue, $offsetUnit), ENT_QUOTES, 'UTF-8') ?></strong>
                                </p>
                            </div>
                        </div>
                        <div class="notificationActions">
                            <button
                                class="notificationAction notificationEditButton"
                                type="button"
                                aria-label="Edit notification for <?= htmlspecialchars($taskTitle, ENT_QUOTES, 'UTF-8') ?>"
                                data-notification-edit
                                data-offset-value="<?= $offsetValue ?>"
                                data-offset-unit="<?= htmlspecialchars($offsetUnit, ENT_QUOTES, 'UTF-8') ?>"
                                data-task-title="<?= htmlspecialchars($taskTitle, ENT_QUOTES, 'UTF-8') ?>"
                                data-task-due="<?= htmlspecialchars($dateFormatter((string) ($notification->task_due_at ?? '')), ENT_QUOTES, 'UTF-8') ?>"
                                <?= $canManageNotification ? '' : 'disabled' ?>>
                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                            </button>
                            <button
                                class="notificationAction notificationCancelButton"
                                type="button"
                                aria-label="Cancel notification for <?= htmlspecialchars($taskTitle, ENT_QUOTES, 'UTF-8') ?>"
                                data-notification-cancel
                                <?= $canManageNotification ? '' : 'disabled' ?>>
                                <i class="fa-solid fa-ban" aria-hidden="true"></i>
                            </button>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </section>
</div>
<?php require_once dirname(__DIR__) . '/modals/notification-edit.php'; ?>
