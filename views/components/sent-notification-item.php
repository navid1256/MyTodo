<?php

declare(strict_types=1);

use App\Localization\Translator;

/** Renders the shared archive row used by the initial list and safe client-side cloning. */
function renderSentNotificationItem(object $notification, Closure $dateFormatter, Translator $translator): void
{
    ?>
    <li class="notificationItem" data-sent-notification="<?= (int) ($notification->id ?? 0) ?>" tabindex="-1">
        <div class="notificationItemIcon" aria-hidden="true"><i class="fa-regular fa-bell"></i></div>
        <div class="notificationItemContent">
            <div class="notificationItemHeading">
                <h2 data-notification-title><?= htmlspecialchars((string) ($notification->task_title ?? $translator->translate('notifications.default_sent_task')), ENT_QUOTES, 'UTF-8') ?></h2>
                <span class="notificationStatus notificationStatus--sent" data-i18n="notifications.status.sent"><?= htmlspecialchars($translator->translate('notifications.status.sent'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="notificationDetails">
                <?php foreach (['sent_at' => 'sent_at', 'remind_at' => 'notification_time', 'task_due_at' => 'task_due_time'] as $field => $label): ?>
                    <p>
                        <span data-i18n="notifications.<?= $label ?>"><?= htmlspecialchars($translator->translate('notifications.' . $label), ENT_QUOTES, 'UTF-8') ?></span>
                        <strong data-notification-date="<?= $field ?>"><?= htmlspecialchars($dateFormatter((string) ($notification->$field ?? '')), ENT_QUOTES, 'UTF-8') ?></strong>
                    </p>
                <?php endforeach; ?>
            </div>
        </div>
    </li>
    <?php
}
