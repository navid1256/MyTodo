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
    'pending' => $translator->translate('notifications.status.pending'),
    'sent' => $translator->translate('notifications.status.sent'),
    'failed' => $translator->translate('notifications.status.failed'),
    'cancelled' => $translator->translate('notifications.status.cancelled'),
];
$csrfToken = isset($csrfToken) && is_string($csrfToken) ? $csrfToken : '';

$storageTimezone = TimezoneHelper::getApplicationTimezone();
$userTimezone = $notificationTimezone ?? TimezoneHelper::getClientTimezone();
$dateFormatter = $formatNotificationDate ?? static function (?string $dateTime) use ($storageTimezone, $userTimezone, $translator): string {
    if ($dateTime === null || trim($dateTime) === '') {
        return $translator->translate('common.not_available');
    }

    return (new DateTimeImmutable($dateTime, $storageTimezone))
        ->setTimezone($userTimezone)
        ->format('M j, Y \a\t h:i A');
};

$offsetFormatter = $formatNotificationOffset ?? static function (int $value, string $unit) use ($translator): string {
    if ($value === 0) {
        return $translator->translate('notifications.on_due_time');
    }

    $normalizedUnit = in_array($unit, ['minute', 'hour', 'day'], true) ? $unit : 'minute';
    $unitKey = 'notifications.unit.' . $normalizedUnit . ($value === 1 ? '.one' : '.other');

    return $translator->translate('notifications.before_due_time', [
        'value' => $value,
        'unit' => $translator->translate($unitKey),
    ]);
};
?>
<div class="content notificationsContent">
    <section class="list notificationsPanel" aria-labelledby="notificationsPageTitle">
        <div class="title notificationsTitle" id="notificationsPageTitle">
            <span data-i18n="notifications.title"><?= htmlspecialchars($translator->translate('notifications.title'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <output class="notificationsPageMessage" id="notificationsPageMessage" aria-live="polite" hidden></output>
        <ul class="notificationsList" id="notificationsList">
            <?php if (empty($notifications)): ?>
                <li class="emptyTask notificationsEmpty" data-i18n="notifications.empty"><?= htmlspecialchars($translator->translate('notifications.empty'), ENT_QUOTES, 'UTF-8') ?></li>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <?php
                    $rawStatus = (string) ($notification->status ?? 'failed');
                    $status = array_key_exists($rawStatus, $statusLabels) ? $rawStatus : 'failed';
                    $canManageNotification = in_array($status, ['pending', 'failed'], true);
                    $offsetValue = (int) ($notification->offset_value ?? 0);
                    $offsetUnit = (string) ($notification->offset_unit ?? 'minute');
                    $taskTitle = (string) ($notification->task_title ?? $translator->translate('notifications.default_task'));
                    ?>
                    <li class="notificationItem" data-notification-id="<?= (int) ($notification->id ?? 0) ?>">
                        <div class="notificationItemIcon" aria-hidden="true">
                            <i class="fa-regular fa-bell"></i>
                        </div>
                        <div class="notificationItemContent">
                            <div class="notificationItemHeading">
                                <h2><?= htmlspecialchars($taskTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                                <span class="notificationStatus notificationStatus--<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" data-notification-status data-i18n="notifications.status.<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($statusLabels[$status], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <div class="notificationDetails">
                                <p>
                                    <span data-i18n="notifications.notification_time"><?= htmlspecialchars($translator->translate('notifications.notification_time'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <strong data-notification-schedule><?= htmlspecialchars($dateFormatter((string) ($notification->remind_at ?? '')), ENT_QUOTES, 'UTF-8') ?></strong>
                                </p>
                                <p>
                                    <span data-i18n="notifications.task_due_time"><?= htmlspecialchars($translator->translate('notifications.task_due_time'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <strong><?= htmlspecialchars($dateFormatter((string) ($notification->task_due_at ?? '')), ENT_QUOTES, 'UTF-8') ?></strong>
                                </p>
                                <p>
                                    <span data-i18n="notifications.reminder"><?= htmlspecialchars($translator->translate('notifications.reminder'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <strong data-notification-offset><?= htmlspecialchars($offsetFormatter($offsetValue, $offsetUnit), ENT_QUOTES, 'UTF-8') ?></strong>
                                </p>
                            </div>
                        </div>
                        <div class="notificationActions">
                            <button
                                class="notificationAction notificationEditButton"
                                type="button"
                                data-i18n-aria-label="notifications.edit"
                                data-title="<?= htmlspecialchars($taskTitle, ENT_QUOTES, 'UTF-8') ?>"
                                aria-label="<?= htmlspecialchars($translator->translate('notifications.edit', ['title' => $taskTitle]), ENT_QUOTES, 'UTF-8') ?>"
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
                                data-i18n-aria-label="notifications.cancel"
                                data-title="<?= htmlspecialchars($taskTitle, ENT_QUOTES, 'UTF-8') ?>"
                                aria-label="<?= htmlspecialchars($translator->translate('notifications.cancel', ['title' => $taskTitle]), ENT_QUOTES, 'UTF-8') ?>"
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
