<?php

/** @var array $tasks */
/** @var array $todayTasks */
/** @var array $tomorrowTasks */
/** @var string $activeView */
/** @var array $currentUser */
/** @var int $completedTasksToday */
/** @var object|null $userProfile */
/** @var array $notifications */
$unreadNotifications = isset($unreadNotifications) ? max(0, (int) $unreadNotifications) : 3;
$notifications = isset($notifications) && is_array($notifications) ? $notifications : [];
$profileData = $userProfile ?? (object) [];
$currentUsername = trim((string) ($currentUser['username'] ?? 'User'));
$profileFirstName = trim((string) ($profileData->firstname ?? ''));
$profileLastName = trim((string) ($profileData->lastname ?? ''));
$hasFullName = $profileFirstName !== '' && $profileLastName !== '';
$currentDisplayName = $hasFullName
  ? $profileFirstName . ' ' . $profileLastName
  : $currentUsername;
$defaultAvatarUrl = BASE_URL . 'assets/img/user-default-avatar.webp';
$savedAvatarUrl = trim((string) ($profileData->avatar_url ?? ''));
$avatarUrl = $savedAvatarUrl !== ''
  ? (preg_match('#^(?:https?://|data:)#i', $savedAvatarUrl) ? $savedAvatarUrl : BASE_URL . ltrim($savedAvatarUrl, '/'))
  : $defaultAvatarUrl;
$csrfToken = getCsrfToken();
$profileErrors = isset($profileErrors) && is_array($profileErrors) ? $profileErrors : [];
$profileSuccess = isset($profileSuccess) && is_string($profileSuccess) ? $profileSuccess : null;
$profileFields = [
  'firstname' => (string) ($profileData->firstname ?? ''),
  'lastname' => (string) ($profileData->lastname ?? ''),
  'email' => (string) ($profileData->email ?? ($currentUser['email'] ?? '')),
  'username' => (string) ($profileData->username ?? $currentUsername),
  'job_title' => (string) ($profileData->job_title ?? ''),
  'date_of_birth' => (string) ($profileData->date_of_birth ?? ''),
  'gender' => (string) ($profileData->gender ?? ''),
  'country' => (string) ($profileData->country ?? ''),
];
$notificationStatusLabels = [
  'pending' => 'Pending',
  'sent' => 'Sent',
  'failed' => 'Failed',
  'cancelled' => 'Cancelled',
];
$formatNotificationDate = static function (?string $dateTime) use ($userTimezone): string {
  if ($dateTime === null || trim($dateTime) === '') {
    return 'Not available';
  }

  return (new DateTimeImmutable($dateTime, $userTimezone))->format('M j, Y \a\t h:i A');
};
$formatNotificationOffset = static function (int $value, string $unit): string {
  if ($value === 0) {
    return 'On due time';
  }

  return $value . ' ' . $unit . ($value === 1 ? '' : 's') . ' before due time';
};

require_once __DIR__ . '/components/task-items.php';

$pageStylesheets = [
  'manage-tasks' => 'assets/css/pages/manage-tasks.css',
  'messages' => 'assets/css/pages/messages.css',
  'profile' => 'assets/css/pages/profile.css',
  'change-password' => 'assets/css/pages/change-password.css',
];
$activePageStylesheet = $pageStylesheets[$activeView] ?? null;
$usesTaskModals = in_array($activeView, ['home', 'manage-tasks', 'messages'], true);

$renderTaskToolbar = static function () use ($completedTasksToday): void {
  $completedCount = max(0, (int) $completedTasksToday);

  echo '<div class="viewHeader">';
  echo '  <div class="functions">';
  echo '    <button class="button active" id="openTaskModal" type="button">';
  echo '      Add New Task';
  echo '    </button>';
  echo '    <div class="button completedButton"';
  echo '         aria-label="' . $completedCount . ' tasks completed today">';
  echo '      <span class="completedCount">' . $completedCount . '</span>';
  echo '      <span>Completed</span>';
  echo '    </div>';
  echo '  </div>';
  echo '</div>';
};
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= Site_Title ?></title>
  <script>
    try {
      if (localStorage.getItem('mytodo-theme') === 'dark') {
        document.documentElement.classList.add('dark-mode');
      }
    } catch (error) {
      // The dashboard still works when browser storage is unavailable.
    }
  </script>
  <link rel="stylesheet" href="assets/css/core.css">
  <?php if ($usesTaskModals): ?>
    <link rel="stylesheet" href="assets/css/task-modal.css">
  <?php endif; ?>
  <?php if ($activePageStylesheet !== null): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($activePageStylesheet, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="assets/css/theme.css">
</head>

<body>
  <div class="page">
    <div class="pageHeader">
      <div class="title">Dashboard</div>
      <div class="userArea">
        <button class="notificationButton" type="button" aria-label="<?= $unreadNotifications ?> unread notifications">
          <i class="fa-regular fa-bell" aria-hidden="true"></i>
          <?php if ($unreadNotifications > 0): ?>
            <span class="notificationBadge"><?= $unreadNotifications > 99 ? '99+' : $unreadNotifications ?></span>
          <?php endif; ?>
        </button>

        <div class="profileMenu">
          <button
            class="userPanel"
            id="userMenuToggle"
            type="button"
            aria-expanded="false"
            aria-controls="profileDropdown">
            <img data-user-avatar src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" width="40" height="40" alt="<?= htmlspecialchars($currentDisplayName, ENT_QUOTES, 'UTF-8') ?>">
            <span class="username"><?= htmlspecialchars($currentDisplayName, ENT_QUOTES, 'UTF-8') ?></span>
            <i class="profileChevron fa-solid fa-chevron-down" aria-hidden="true"></i>
          </button>

          <div class="profileDropdown" id="profileDropdown" hidden>
            <a class="profileDropdownLink" href="?view=profile">
              <i class="fa-regular fa-user" aria-hidden="true"></i>
              <span>My Profile</span>
            </a>
            <button type="button">
              <i class="fa-solid fa-user-gear" aria-hidden="true"></i>
              <span>Account Settings</span>
            </button>
            <button id="themeToggle" type="button" aria-pressed="false">
              <i id="themeIcon" class="fa-solid fa-moon" aria-hidden="true"></i>
              <span id="themeLabel">Dark Mode</span>
            </button>
            <form action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>logout.php" method="POST">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
              <button class="signOutButton" type="submit">
                <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                <span>Sign out</span>
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <div class="main">
      <div class="nav">
        <div class="searchbox">
          <div><i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" placeholder="Search" />
          </div>
        </div>
        <div class="menu">
          <div class="title">Navigation</div>
          <ul class="navigation-list">
            <li class="<?= $activeView === 'home' ? 'active' : '' ?>" data-nav-id="home">
              <a href="?view=home" <?= $activeView === 'home' ? 'aria-current="page"' : '' ?>>
                <i class="fa-solid fa-house"></i>
                <span>Home</span>
              </a>
            </li>
            <li data-nav-id="activity">
              <button type="button">
                <i class="fa-solid fa-chart-simple"></i>
                <span>Activity</span>
              </button>
            </li>
            <li class="<?= $activeView === 'manage-tasks' ? 'active' : '' ?>" data-nav-id="manage-tasks">
              <a href="?view=manage-tasks#tasks" <?= $activeView === 'manage-tasks' ? 'aria-current="page"' : '' ?>>
                <i class="fa-solid fa-server"></i>
                <span>Manage Tasks</span>
              </a>
            </li>
            <li class="<?= $activeView === 'messages' ? 'active' : '' ?>" data-nav-id="messages">
              <a href="?view=messages" <?= $activeView === 'messages' ? 'aria-current="page"' : '' ?>>
                <i class="fa-solid fa-envelope"></i>
                <span>Messages</span>
              </a>
            </li>
          </ul>
        </div>
      </div>
      <div class="view<?= in_array($activeView, ['profile', 'change-password'], true) ? ' profileView' : '' ?><?= $activeView === 'manage-tasks' ? ' manageTasksView' : '' ?><?= $activeView === 'messages' ? ' messagesView' : '' ?>" id="tasks">
        <?php if ($activeView === 'profile'): ?>

          <?php require __DIR__ . '/pages/profile.php'; ?>

        <?php elseif ($activeView === 'change-password'): ?>

          <?php require __DIR__ . '/pages/change-password.php'; ?>

        <?php elseif ($activeView === 'messages'): ?>

          <?php require __DIR__ . '/pages/messages.php'; ?>

        <?php else: ?>

          <?php $renderTaskToolbar(); ?>

          <div class="content<?= $activeView === 'manage-tasks' ? ' manageTasksContent' : '' ?>">
            <?php if ($activeView === 'home'): ?>

              <?php require __DIR__ . '/pages/home.php'; ?>

            <?php else: ?>

              <?php require __DIR__ . '/pages/manage-tasks.php'; ?>

            <?php endif; ?>
          </div>

        <?php endif; ?>

        <?php if (in_array($activeView, ['home', 'manage-tasks', 'messages'], true)): ?>

          <?php require __DIR__ . '/modals/task.php'; ?>
          <?php require __DIR__ . '/modals/date-time.php'; ?>
          <?php require __DIR__ . '/modals/reminder.php'; ?>
          <?php require __DIR__ . '/modals/repeat.php'; ?>

        <?php endif; ?>
      </div>
    </div>
  </div>
  <script type="module" src="assets/js/app.js"></script>
</body>

</html>
