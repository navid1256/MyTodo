<?php

declare(strict_types=1);

use App\Helpers\TimezoneHelper;

/** @var array<int, object> $sentNotifications */
/** @var array<int, object> $notifications */
/** @var \Closure|null $formatNotificationDate */
/** @var DateTimeZone|null $notificationTimezone */

$notificationList = isset($sentNotifications) && is_array($sentNotifications)
    ? $sentNotifications
    : (isset($notifications) && is_array($notifications) ? $notifications : []);

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
?>
<section class="sentNotificationsPage" aria-labelledby="sentNotificationsPageTitle">
    <header class="sentNotificationsPageHeader dashboardSectionHeader">
        <a class="dashboardBackButton" href="/" data-dashboard-back data-i18n-aria-label="common.back_to_previous" aria-label="<?= htmlspecialchars($translator->translate('common.back_to_previous'), ENT_QUOTES, 'UTF-8') ?>">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <span data-i18n="common.back"><?= htmlspecialchars($translator->translate('common.back'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <i class="sentNotificationsPageHeaderIcon fa-regular fa-bell" aria-hidden="true"></i>
        <h1 id="sentNotificationsPageTitle" data-i18n="notifications.sent_title"><?= htmlspecialchars($translator->translate('notifications.sent_title'), ENT_QUOTES, 'UTF-8') ?></h1>
    </header>

    <div class="content notificationsContent">
        <section class="list notificationsPanel">
            <ul class="notificationsList">
                <?php if (empty($notificationList)): ?>
                    <li class="emptyTask notificationsEmpty" data-i18n="notifications.sent_empty"><?= htmlspecialchars($translator->translate('notifications.sent_empty'), ENT_QUOTES, 'UTF-8') ?></li>
                <?php else: ?>
                    <?php foreach ($notificationList as $notification): ?>
                        <li class="notificationItem">
                            <div class="notificationItemIcon" aria-hidden="true">
                                <i class="fa-regular fa-bell"></i>
                            </div>

                            <div class="notificationItemContent">
                                <div class="notificationItemHeading">
                                    <h2><?= htmlspecialchars((string) ($notification->task_title ?? $translator->translate('notifications.default_sent_task')), ENT_QUOTES, 'UTF-8') ?></h2>
                                    <span class="notificationStatus notificationStatus--sent" data-i18n="notifications.status.sent"><?= htmlspecialchars($translator->translate('notifications.status.sent'), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>

                                <div class="notificationDetails">
                                    <p>
                                        <span data-i18n="notifications.sent_at"><?= htmlspecialchars($translator->translate('notifications.sent_at'), ENT_QUOTES, 'UTF-8') ?></span>
                                        <strong><?= htmlspecialchars($dateFormatter((string) ($notification->sent_at ?? '')), ENT_QUOTES, 'UTF-8') ?></strong>
                                    </p>
                                    <p>
                                        <span data-i18n="notifications.notification_time"><?= htmlspecialchars($translator->translate('notifications.notification_time'), ENT_QUOTES, 'UTF-8') ?></span>
                                        <strong><?= htmlspecialchars($dateFormatter((string) ($notification->remind_at ?? '')), ENT_QUOTES, 'UTF-8') ?></strong>
                                    </p>
                                    <p>
                                        <span data-i18n="notifications.task_due_time"><?= htmlspecialchars($translator->translate('notifications.task_due_time'), ENT_QUOTES, 'UTF-8') ?></span>
                                        <strong><?= htmlspecialchars($dateFormatter((string) ($notification->task_due_at ?? '')), ENT_QUOTES, 'UTF-8') ?></strong>
                                    </p>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </section>
    </div>
</section>
