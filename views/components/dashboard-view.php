<?php

/** @var string $activeView */
/** @var bool $usesTaskModals */
/** @var Closure $renderTaskToolbar */
?>
<div class="view<?= in_array($activeView, ['profile', 'change-password'], true) ? ' profileView' : '' ?><?= $activeView === 'manage-tasks' ? ' manageTasksView' : '' ?><?= $activeView === 'activity' ? ' activityView' : '' ?><?= in_array($activeView, ['messages', 'notifications'], true) ? ' messagesView' : '' ?>" id="tasks">
  <?php if ($activeView === 'profile'): ?>

    <?php require __DIR__ . '/../pages/profile.php'; ?>

  <?php elseif ($activeView === 'change-password'): ?>

    <?php require __DIR__ . '/../pages/change-password.php'; ?>

  <?php elseif ($activeView === 'messages'): ?>

    <?php $renderTaskToolbar(); ?>
    <?php require __DIR__ . '/../pages/messages.php'; ?>

  <?php elseif ($activeView === 'notifications'): ?>

    <?php require __DIR__ . '/../pages/notifications.php'; ?>

  <?php elseif ($activeView === 'activity'): ?>

    <?php $renderTaskToolbar(); ?>
    <?php require __DIR__ . '/../pages/activity.php'; ?>

  <?php else: ?>

    <?php $renderTaskToolbar(); ?>

    <div class="content<?= $activeView === 'manage-tasks' ? ' manageTasksContent' : '' ?>">
      <?php if ($activeView === 'home'): ?>

        <?php require __DIR__ . '/../pages/home.php'; ?>

      <?php else: ?>

        <?php require __DIR__ . '/../pages/manage-tasks.php'; ?>

      <?php endif; ?>
    </div>

  <?php endif; ?>

  <?php if ($usesTaskModals): ?>

    <?php require __DIR__ . '/../modals/task.php'; ?>
    <?php require __DIR__ . '/../modals/date-time.php'; ?>
    <?php require __DIR__ . '/../modals/reminder.php'; ?>
    <?php require __DIR__ . '/../modals/repeat.php'; ?>

  <?php endif; ?>
</div>
