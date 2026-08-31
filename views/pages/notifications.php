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
$dateFormatter = $formatNotificationDate ?? static function (?string $dateTime) use ($storageTimezone, $userTimezone): string {
    if ($dateTime === null || trim($dateTime) === '') {
        return 'Not available';
    }

    return (new DateTimeImmutable($dateTime, $storageTimezone))
        ->setTimezone($userTimezone)
        ->format('M j, Y \a\t h:i A');
};
?>
<section class="sentNotificationsPage" aria-labelledby="sentNotificationsPageTitle">
    <header class="sentNotificationsPageHeader dashboardSectionHeader">
        <a class="dashboardBackButton" href="/" data-dashboard-back aria-label="Back to previous page">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <span>Back</span>
        </a>
        <i class="sentNotificationsPageHeaderIcon fa-regular fa-bell" aria-hidden="true"></i>
        <h1 id="sentNotificationsPageTitle">Sent Notifications</h1>
    </header>

    <div class="content notificationsContent">
        <section class="list notificationsPanel">
            <ul class="notificationsList">
                <?php if (empty($notificationList)): ?>
                    <li class="emptyTask notificationsEmpty">No sent notifications found.</li>
                <?php else: ?>
                    <?php foreach ($notificationList as $notification): ?>
                        <li class="notificationItem">
                            <div class="notificationItemIcon" aria-hidden="true">
                                <i class="fa-regular fa-bell"></i>
                            </div>

                            <div class="notificationItemContent">
                                <div class="notificationItemHeading">
                                    <h2><?= htmlspecialchars((string) ($notification->task_title ?? 'Task Notification'), ENT_QUOTES, 'UTF-8') ?></h2>
                                    <span class="notificationStatus notificationStatus--sent">Sent</span>
                                </div>

                                <div class="notificationDetails">
                                    <p>
                                        <span>Sent at</span>
                                        <strong><?= htmlspecialchars($dateFormatter((string) ($notification->sent_at ?? '')), ENT_QUOTES, 'UTF-8') ?></strong>
                                    </p>
                                    <p>
                                        <span>Notification time</span>
                                        <strong><?= htmlspecialchars($dateFormatter((string) ($notification->remind_at ?? '')), ENT_QUOTES, 'UTF-8') ?></strong>
                                    </p>
                                    <p>
                                        <span>Task due time</span>
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
