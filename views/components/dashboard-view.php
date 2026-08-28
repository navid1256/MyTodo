<?php

declare(strict_types=1);

/** @var string $activeView */
/** @var bool $usesTaskModals */
/** @var Closure|null $renderTaskToolbar */

$viewClassMap = [
    'profile' => ' profileView',
    'change-password' => ' profileView',
    'account-settings' => ' accountSettingsView',
    'manage-tasks' => ' manageTasksView',
    'activity' => ' activityView',
    'messages' => ' messagesView',
    'notifications' => ' messagesView',
];
$extraClass = $viewClassMap[$activeView] ?? '';
?>
<div class="view<?= $extraClass ?>" id="tasks">
  <?php if ($activeView === 'profile'): ?>

    <?php require_once __DIR__ . '/../pages/profile.php'; ?>

  <?php elseif ($activeView === 'change-password'): ?>

    <?php require_once __DIR__ . '/../pages/change-password.php'; ?>

  <?php elseif ($activeView === 'account-settings'): ?>

    <?php require_once __DIR__ . '/../pages/account-settings.php'; ?>

  <?php elseif ($activeView === 'messages'): ?>

    <?php if (isset($renderTaskToolbar) && is_callable($renderTaskToolbar)) { $renderTaskToolbar(); } ?>
    <?php require_once __DIR__ . '/../pages/messages.php'; ?>

  <?php elseif ($activeView === 'notifications'): ?>

    <?php require_once __DIR__ . '/../pages/notifications.php'; ?>

  <?php elseif ($activeView === 'activity'): ?>

    <?php if (isset($renderTaskToolbar) && is_callable($renderTaskToolbar)) { $renderTaskToolbar(); } ?>
    <?php require_once __DIR__ . '/../pages/activity.php'; ?>

  <?php else: ?>

    <?php if (isset($renderTaskToolbar) && is_callable($renderTaskToolbar)) { $renderTaskToolbar(); } ?>

    <div class="content<?= $activeView === 'manage-tasks' ? ' manageTasksContent' : '' ?>">
      <?php if ($activeView === 'home'): ?>

        <?php require_once __DIR__ . '/../pages/home.php'; ?>

      <?php else: ?>

        <?php require_once __DIR__ . '/../pages/manage-tasks.php'; ?>

      <?php endif; ?>
    </div>

  <?php endif; ?>

  <?php if (!empty($usesTaskModals)): ?>

    <?php require_once __DIR__ . '/../modals/task.php'; ?>
    <?php require_once __DIR__ . '/../modals/date-time.php'; ?>
    <?php require_once __DIR__ . '/../modals/reminder.php'; ?>
    <?php require_once __DIR__ . '/../modals/repeat.php'; ?>

  <?php endif; ?>
</div>
