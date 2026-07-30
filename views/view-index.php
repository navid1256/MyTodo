<?php

/** @var array $tasks */
/** @var array $todayTasks */
/** @var array $tomorrowTasks */
/** @var string $activeView */
/** @var array $currentUser */
/** @var int $completedTasksToday */
/** @var object|null $userProfile */
$unreadNotifications = isset($unreadNotifications) ? max(0, (int) $unreadNotifications) : 3;
$profileData = $userProfile ?? (object) [];
$currentUsername = trim((string) ($currentUser['username'] ?? 'User'));
$profileFirstName = trim((string) ($profileData->firstname ?? ''));
$profileLastName = trim((string) ($profileData->lastname ?? ''));
$hasFullName = $profileFirstName !== '' && $profileLastName !== '';
$currentDisplayName = $hasFullName
  ? $profileFirstName . ' ' . $profileLastName
  : $currentUsername;
$avatarInitials = $hasFullName
  ? mb_strtoupper(mb_substr($profileFirstName, 0, 1) . mb_substr($profileLastName, 0, 1))
  : mb_strtoupper(mb_substr($currentUsername, 0, 2));
$avatarSvg = sprintf(
  "<svg xmlns='http://www.w3.org/2000/svg' width='40' height='40'><rect fill='#667eea' width='40' height='40'/><text x='50%%' y='50%%' dominant-baseline='middle' text-anchor='middle' fill='white' font-size='16' font-family='Arial'>%s</text></svg>",
  htmlspecialchars($avatarInitials, ENT_QUOTES | ENT_XML1, 'UTF-8')
);
$avatarUrl = 'data:image/svg+xml,' . rawurlencode($avatarSvg);
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

$renderTaskItems = static function ($taskItems, $viewName, $emptyMessage, $showDueTime = false) {
  if (!sizeof($taskItems)) {
    echo '<li class="emptyTask">' . htmlspecialchars($emptyMessage, ENT_QUOTES, 'UTF-8') . '</li>';
    return;
  }

  foreach ($taskItems as $task): ?>
    <li class="<?= $task->is_done ? 'checked' : ''; ?>">
      <i class="<?= $task->is_done ? 'fa-regular fa-square-check' : 'fa-regular fa-square'; ?>"></i>
      <span><?= htmlspecialchars($task->title, ENT_QUOTES, 'UTF-8') ?></span>
      <div class="info">
        <?php if ($showDueTime && !empty($task->due_at)): ?>
          <span class="created-at">Due at <?= date('H:i', strtotime($task->due_at)) ?></span>
        <?php else: ?>
          <span class="created-at">Created At <?= htmlspecialchars($task->created_at, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
        <a href="?view=<?= urlencode($viewName) ?>&amp;delete_task=<?= $task->id ?>">
          <i class="fa-regular fa-trash-can" onclick="return confirm('Are You Sure To Delete This Task ?\n<?= htmlspecialchars($task->title, ENT_QUOTES, 'UTF-8') ?>')"></i>
        </a>
      </div>
    </li>
  <?php endforeach;
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
  <link rel="stylesheet" href="assets/css/style.css">
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
            <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" width="40" height="40" alt="<?= htmlspecialchars($currentDisplayName, ENT_QUOTES, 'UTF-8') ?>">
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
            <li data-nav-id="messages">
              <button type="button">
                <i class="fa-solid fa-envelope"></i>
                <span>Messages</span>
              </button>
            </li>
          </ul>
        </div>
      </div>
      <div class="view<?= $activeView === 'profile' ? ' profileView' : '' ?>" id="tasks">
        <?php if ($activeView === 'profile'): ?>
          <section class="profilePage" aria-labelledby="profilePageTitle">
            <h1 class="srOnly" id="profilePageTitle">My Profile</h1>
            <?php if ($profileSuccess): ?>
              <div class="profileMessage profileMessageSuccess" role="status">
                <?= htmlspecialchars($profileSuccess, ENT_QUOTES, 'UTF-8') ?>
              </div>
            <?php endif; ?>
            <?php if ($profileErrors): ?>
              <div class="profileMessage profileMessageError" role="alert">
                <?php foreach ($profileErrors as $profileError): ?>
                  <p><?= htmlspecialchars((string) $profileError, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <form class="profileOverview" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>index.php?view=profile" method="POST">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="profile_action" value="save">
              <div class="profilePicture">
                <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($currentDisplayName, ENT_QUOTES, 'UTF-8') ?> profile picture">
                <span>Profile picture</span>
              </div>

              <div class="profileFields">
                <label class="profileField">
                  <span>First Name :</span>
                  <input type="text" name="firstname" placeholder="Enter your first name" value="<?= htmlspecialchars($profileFields['firstname'], ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label class="profileField">
                  <span>Last Name :</span>
                  <input type="text" name="lastname" placeholder="Enter your last name" value="<?= htmlspecialchars($profileFields['lastname'], ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label class="profileField">
                  <span>Email :</span>
                  <input type="email" placeholder="Email address" value="<?= htmlspecialchars($profileFields['email'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                </label>
                <label class="profileField">
                  <span>Username :</span>
                  <input type="text" placeholder="Username" value="<?= htmlspecialchars($profileFields['username'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                </label>
                <label class="profileField">
                  <span>Job Title :</span>
                  <input type="text" name="job_title" placeholder="Enter your job title" value="<?= htmlspecialchars($profileFields['job_title'], ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label class="profileField">
                  <span>Date of Birth :</span>
                  <input type="date" name="date_of_birth" placeholder="Select your date of birth" value="<?= htmlspecialchars($profileFields['date_of_birth'], ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label class="profileField">
                  <span>Gender :</span>
                  <select name="gender">
                    <option value="" <?= $profileFields['gender'] === '' ? 'selected' : '' ?>>Select gender</option>
                    <option value="male" <?= $profileFields['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                    <option value="female" <?= $profileFields['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                    <option value="other" <?= $profileFields['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                  </select>
                </label>
                <label class="profileField">
                  <span>Country :</span>
                  <input type="text" name="country" placeholder="Enter your country" value="<?= htmlspecialchars($profileFields['country'], ENT_QUOTES, 'UTF-8') ?>">
                </label>
              </div>

              <div class="profileActions">
                <button class="saveProfileButton" type="submit">Save</button>
              </div>
            </form>

            <div class="profileSecurity">
              <h2>Security</h2>
              <button class="changePasswordButton" type="button">Change Password</button>
            </div>
          </section>
        <?php else: ?>
        <div class="viewHeader">
          <div class="title" style="width:50% ;">
            <input type="text" id="taskNameInput" style="width: 76%;margin-left: 5%;line-height: 17px;" placeholder="Add New Task">
            <button id="newTaskBtn" class="Btn clickable">+</button>
          </div>
          <div class="functions">
            <button class="button active" id="openTaskModal" type="button">Add New Task</button>
            <div class="button completedButton" aria-label="<?= $completedTasksToday ?> tasks completed today">
              <span class="completedCount"><?= $completedTasksToday ?></span>
              <span>Completed</span>
            </div>
            <div class="button inverz"><i class="fa-regular fa-trash-can"></i></div>
          </div>
        </div>
        <div class="content">
          <?php if ($activeView === 'home'): ?>
            <div class="list">
              <div class="title">Today</div>
              <ul>
                <?php $renderTaskItems($todayTasks, 'home', 'No tasks due today.', true); ?>
              </ul>
            </div>
            <div class="list scheduledTaskList">
              <div class="title">Tomorrow</div>
              <ul>
                <?php $renderTaskItems($tomorrowTasks, 'home', 'No tasks due tomorrow.', true); ?>
              </ul>
            </div>
          <?php else: ?>
            <div class="list">
              <div class="title">All Tasks</div>
              <ul>
                <?php $renderTaskItems($tasks, 'manage-tasks', 'No tasks found.'); ?>
              </ul>
            </div>
          <?php endif; ?>
        </div>

        <div class="taskModalBackdrop" id="taskModal" hidden>
          <section
            class="taskModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="taskModalTitle">
            <h2 class="srOnly" id="taskModalTitle">Add New Task</h2>
            <button class="taskModalClose" id="closeTaskModal" type="button" aria-label="Close task modal">
              <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>

            <form id="newTaskForm" novalidate>
              <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

              <label class="srOnly" for="taskModalText">Task text</label>
              <textarea
                id="taskModalText"
                name="task_title"
                maxlength="512"
                placeholder="Input Text"
                required></textarea>

              <div class="taskModalOptions">
                <button
                  class="taskOptionButton"
                  id="setTaskDateButton"
                  type="button"
                  aria-expanded="false"
                  aria-controls="taskDateTimePanel">
                  Set Date &amp; Time
                </button>
                <button
                  class="taskOptionButton"
                  id="setTaskReminderButton"
                  type="button"
                  aria-pressed="false">
                  Set Reminder
                </button>
                <button
                  class="taskOptionButton"
                  id="setTaskRepeatButton"
                  type="button"
                  aria-pressed="false">
                  Repeat
                </button>
              </div>

              <div class="taskDateTimePanel" id="taskDateTimePanel" hidden>
                <label for="taskDueAt">Task date and time</label>
                <input id="taskDueAt" name="due_at" type="datetime-local">
              </div>

              <p class="taskModalMessage" id="taskModalMessage" role="alert" aria-live="polite"></p>

              <div class="taskModalActions">
                <button class="saveTaskButton" id="saveTaskButton" type="submit">Save</button>
              </div>
            </form>
          </section>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <script src="assets/js/jquery-minimal.js"></script>
  <script src="assets/js/script.js"></script>
</body>

</html>
