<?php

/** @var array $notifications */
/** @var array $notificationStatusLabels */
/** @var \Closure $formatNotificationDate */
/** @var \Closure $formatNotificationOffset */
?>
<div class="content notificationsContent">
    <section class="list notificationsPanel" aria-labelledby="notificationsPageTitle">
        <div class="title notificationsTitle" id="notificationsPageTitle">
            <span>Notifications</span>
        </div>
        <p class="notificationsPageMessage" id="notificationsPageMessage" role="status" aria-live="polite"></p>
        <ul class="notificationsList" id="notificationsList">
            <?php if (!$notifications): ?>
                <li class="emptyTask notificationsEmpty">No notifications found.</li>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <?php
                    $status = array_key_exists((string) $notification->status, $notificationStatusLabels)
                        ? (string) $notification->status
                        : 'failed';
                    $canManageNotification = in_array($status, ['pending', 'failed'], true);
                    $offsetValue = (int) $notification->offset_value;
                    $offsetUnit = (string) $notification->offset_unit;
                    ?>
                    <li class="notificationItem" data-notification-id="<?= (int) $notification->id ?>">
                        <div class="notificationItemIcon" aria-hidden="true">
                            <i class="fa-regular fa-bell"></i>
                        </div>
                        <div class="notificationItemContent">
                            <div class="notificationItemHeading">
                                <h2><?= htmlspecialchars((string) $notification->task_title, ENT_QUOTES, 'UTF-8') ?></h2>
                                <span class="notificationStatus notificationStatus--<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" data-notification-status>
                                    <?= htmlspecialchars($notificationStatusLabels[$status], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <div class="notificationDetails">
                                <p>
                                    <span>Notification time</span>
                                    <strong data-notification-schedule><?= htmlspecialchars($formatNotificationDate((string) $notification->remind_at), ENT_QUOTES, 'UTF-8') ?></strong>
                                </p>
                                <p>
                                    <span>Task due time</span>
                                    <strong><?= htmlspecialchars($formatNotificationDate((string) $notification->task_due_at), ENT_QUOTES, 'UTF-8') ?></strong>
                                </p>
                                <p>
                                    <span>Reminder</span>
                                    <strong data-notification-offset><?= htmlspecialchars($formatNotificationOffset($offsetValue, $offsetUnit), ENT_QUOTES, 'UTF-8') ?></strong>
                                </p>
                            </div>
                        </div>
                        <div class="notificationActions">
                            <button
                                class="notificationAction notificationEditButton"
                                type="button"
                                aria-label="Edit notification for <?= htmlspecialchars((string) $notification->task_title, ENT_QUOTES, 'UTF-8') ?>"
                                data-notification-edit
                                data-offset-value="<?= $offsetValue ?>"
                                data-offset-unit="<?= htmlspecialchars($offsetUnit, ENT_QUOTES, 'UTF-8') ?>"
                                data-task-title="<?= htmlspecialchars((string) $notification->task_title, ENT_QUOTES, 'UTF-8') ?>"
                                data-task-due="<?= htmlspecialchars($formatNotificationDate((string) $notification->task_due_at), ENT_QUOTES, 'UTF-8') ?>"
                                <?= $canManageNotification ? '' : 'disabled' ?>>
                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                            </button>
                            <button
                                class="notificationAction notificationCancelButton"
                                type="button"
                                aria-label="Cancel notification for <?= htmlspecialchars((string) $notification->task_title, ENT_QUOTES, 'UTF-8') ?>"
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
<?php require __DIR__ . '/../modals/notification-edit.php'; ?>