<?php

declare(strict_types=1);

use App\Helpers\CalendarDateFormatter;
use App\Helpers\TimezoneHelper;

require_once dirname(__DIR__) . '/components/sent-notification-item.php';

/** @var array<int, object> $sentNotifications */
/** @var array<int, object> $notifications */
/** @var \Closure|null $formatNotificationDate */
/** @var DateTimeZone|null $notificationTimezone */

$notificationList = isset($sentNotifications) && is_array($sentNotifications)
    ? $sentNotifications
    : (isset($notifications) && is_array($notifications) ? $notifications : []);

$storageTimezone = TimezoneHelper::getApplicationTimezone();
$userTimezone = $notificationTimezone ?? TimezoneHelper::getClientTimezone();
$calendarDateFormatter = new CalendarDateFormatter($calendarSystem ?? 'gregorian', $userTimezone);
/** Formats sent, scheduled, and due timestamps consistently with the notification management page. */
$dateFormatter = $formatNotificationDate ?? static function (?string $dateTime) use ($storageTimezone, $calendarDateFormatter, $translator): string {
    if ($dateTime === null || trim($dateTime) === '') {
        return $translator->translate('common.not_available');
    }

    return $calendarDateFormatter->dateTime(new DateTimeImmutable($dateTime, $storageTimezone));
};
?>
<section class="sentNotificationsPage" aria-labelledby="sentNotificationsPageTitle" data-notification-archive data-next-cursor="<?= htmlspecialchars($nextNotificationCursor ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <header class="sentNotificationsPageHeader dashboardSectionHeader">
        <a class="dashboardBackButton" href="/" data-dashboard-back data-i18n-aria-label="common.back_to_previous" aria-label="<?= htmlspecialchars($translator->translate('common.back_to_previous'), ENT_QUOTES, 'UTF-8') ?>">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <span data-i18n="common.back"><?= htmlspecialchars($translator->translate('common.back'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <div class="sentNotificationsTitleGroup">
            <i class="sentNotificationsPageHeaderIcon fa-regular fa-bell" aria-hidden="true"></i>
            <h1 id="sentNotificationsPageTitle" tabindex="-1" data-i18n="notifications.sent_title"><?= htmlspecialchars($translator->translate('notifications.sent_title'), ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
        <button class="notificationsClearButton" type="button" data-clear-notifications <?= empty($notificationList) ? 'disabled' : '' ?>>
            <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
            <span data-i18n="notifications.clear"><?= htmlspecialchars($translator->translate('notifications.clear'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
    </header>

    <div class="content notificationsContent">
        <section class="list notificationsPanel">
            <p class="notificationsPageMessage" data-archive-message role="status" aria-live="polite" hidden></p>
            <ul class="notificationsList" data-sent-notifications>
                <?php if (empty($notificationList)): ?>
                    <li class="emptyTask notificationsEmpty" data-i18n="notifications.sent_empty"><?= htmlspecialchars($translator->translate('notifications.sent_empty'), ENT_QUOTES, 'UTF-8') ?></li>
                <?php else: ?>
                    <?php foreach ($notificationList as $notification): ?>
                        <?php renderSentNotificationItem($notification, $dateFormatter, $translator); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
            <template data-sent-notification-template><?php renderSentNotificationItem((object) [], $dateFormatter, $translator); ?></template>
            <div class="notificationsLoadMore">
                <button type="button" data-load-notifications <?= empty($nextNotificationCursor) ? 'hidden' : '' ?> data-i18n="notifications.load_more"><?= htmlspecialchars($translator->translate('notifications.load_more'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </section>
    </div>
</section>
