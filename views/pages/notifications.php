<?php

/** @var array $sentNotifications */
/** @var \Closure $formatNotificationDate */

?>
<div class="content notificationsContent">
    <section class="list notificationsPanel" aria-labelledby="sentNotificationsPageTitle">
        <div class="title notificationsTitle" id="sentNotificationsPageTitle">
            <span>Sent Notifications</span>
        </div>

        <ul class="notificationsList">
            <?php if (!$sentNotifications): ?>
                <li class="emptyTask notificationsEmpty">No sent notifications found.</li>
            <?php else: ?>
                <?php foreach ($sentNotifications as $notification): ?>
                    <li class="notificationItem">
                        <div class="notificationItemIcon" aria-hidden="true">
                            <i class="fa-regular fa-bell"></i>
                        </div>

                        <div class="notificationItemContent">
                            <div class="notificationItemHeading">
                                <h2><?= htmlspecialchars((string) $notification->task_title, ENT_QUOTES, 'UTF-8') ?></h2>
                                <span class="notificationStatus notificationStatus--sent">Sent</span>
                            </div>

                            <div class="notificationDetails">
                                <p>
                                    <span>Sent at</span>
                                    <strong><?= htmlspecialchars($formatNotificationDate((string) $notification->sent_at), ENT_QUOTES, 'UTF-8') ?></strong>
                                </p>
                                <p>
                                    <span>Notification time</span>
                                    <strong><?= htmlspecialchars($formatNotificationDate((string) $notification->remind_at), ENT_QUOTES, 'UTF-8') ?></strong>
                                </p>
                                <p>
                                    <span>Task due time</span>
                                    <strong><?= htmlspecialchars($formatNotificationDate((string) $notification->task_due_at), ENT_QUOTES, 'UTF-8') ?></strong>
                                </p>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </section>
</div>
