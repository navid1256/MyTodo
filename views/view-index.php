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

$renderTaskItems = static function (
  $taskItems,
  $viewName,
  $emptyMessage,
  $showDueTime = false,
  $showDueDate = false
) {
  if (!sizeof($taskItems)) {
    $emptyClass = $showDueDate ? 'emptyTask allTasksEmpty' : 'emptyTask';
    $emptyId = $showDueDate ? ' id="allTasksEmpty"' : '';
    echo '<li class="' . $emptyClass . '"' . $emptyId . '>'
      . htmlspecialchars($emptyMessage, ENT_QUOTES, 'UTF-8') . '</li>';
    return;
  }

  foreach ($taskItems as $task): ?>
    <?php $taskDate = !empty($task->due_at) ? substr((string) $task->due_at, 0, 10) : ''; ?>
    <li
      class="taskItem<?= $task->is_done ? ' checked' : ''; ?>"
      data-task-date="<?= htmlspecialchars($taskDate, ENT_QUOTES, 'UTF-8') ?>">
      <i class="<?= $task->is_done ? 'fa-regular fa-square-check' : 'fa-regular fa-square'; ?>"></i>
      <span><?= htmlspecialchars($task->title, ENT_QUOTES, 'UTF-8') ?></span>
      <div class="info">
        <?php if ($showDueDate): ?>
          <?php if (!empty($task->due_at)): ?>
            <span class="created-at">
              Due <?= date('M j, Y', strtotime($task->due_at)) ?>
              <?= !empty($task->has_time) ? 'at ' . date('h:i A', strtotime($task->due_at)) : '· No time' ?>
            </span>
          <?php else: ?>
            <span class="created-at">No due date</span>
          <?php endif; ?>
        <?php elseif ($showDueTime && !empty($task->due_at)): ?>
          <?php if (!empty($task->has_time)): ?>
            <span class="created-at">Due at <?= date('h:i A', strtotime($task->due_at)) ?></span>
          <?php else: ?>
            <span class="created-at">No time set</span>
          <?php endif; ?>
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
            <li data-nav-id="messages">
              <button type="button">
                <i class="fa-solid fa-envelope"></i>
                <span>Messages</span>
              </button>
            </li>
          </ul>
        </div>
      </div>
      <div class="view<?= in_array($activeView, ['profile', 'change-password'], true) ? ' profileView' : '' ?><?= $activeView === 'manage-tasks' ? ' manageTasksView' : '' ?>" id="tasks">
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
              <input id="avatarAction" type="hidden" name="avatar_action" value="unchanged">
              <input id="avatarChoice" type="hidden" name="avatar_choice" value="">
              <input id="avatarData" type="hidden" name="avatar_data" value="">
              <div class="profilePicture">
                <button
                  class="profilePictureButton"
                  id="openAvatarPicker"
                  type="button"
                  data-avatar-seed-base="user-<?= getCurrentUserId() ?>"
                  aria-haspopup="dialog">
                  <img data-user-avatar src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($currentDisplayName, ENT_QUOTES, 'UTF-8') ?> profile picture">
                  <span class="profilePictureOverlay">Change</span>
                </button>
                <span>Profile picture</span>
                <small id="avatarSelectionStatus">Click the picture to change it</small>
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

            <div class="avatarPickerBackdrop" id="avatarPickerModal" hidden>
              <section
                class="avatarPickerModal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="avatarPickerTitle">
                <button class="avatarPickerClose" id="closeAvatarPicker" type="button" aria-label="Close avatar picker">
                  <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>

                <h2 id="avatarPickerTitle">Choose a profile picture</h2>
                <p class="avatarPickerHint">Select a Boring Avatar or choose a picture from your device.</p>

                <div class="avatarGalleryPanel" id="avatarGalleryPanel">
                  <div class="boringAvatarGrid" id="boringAvatarGrid" role="radiogroup" aria-label="Boring Avatar options"></div>
                  <button class="chooseDeviceButton" id="chooseAvatarFromDevice" type="button">Choose from your device</button>
                  <input id="avatarFileInput" type="file" accept="image/jpeg,image/png,image/webp" hidden>
                </div>

                <div class="avatarCropPanel" id="avatarCropPanel" hidden>
                  <button class="backToAvatarGallery" id="backToAvatarGallery" type="button">Back to avatars</button>
                  <div class="avatarCropViewport" id="avatarCropViewport">
                    <img id="avatarCropImage" alt="Profile picture crop preview" draggable="false">
                    <span class="avatarCropRing" aria-hidden="true"></span>
                  </div>
                  <p class="avatarCropHint">Drag the picture to reposition it.</p>
                  <div class="avatarZoomControl">
                    <button id="avatarZoomOut" type="button" aria-label="Zoom out">−</button>
                    <label class="srOnly" for="avatarZoom">Zoom profile picture</label>
                    <input id="avatarZoom" type="range" min="-100" max="100" step="1" value="0" aria-valuetext="100%">
                    <button id="avatarZoomIn" type="button" aria-label="Zoom in">+</button>
                  </div>
                </div>

                <p class="avatarPickerMessage" id="avatarPickerMessage" role="alert" aria-live="polite"></p>

                <div class="avatarPickerActions">
                  <button class="cancelAvatarButton" id="cancelAvatarPicker" type="button">Cancel</button>
                  <button class="applyAvatarButton" id="applyAvatarSelection" type="button" disabled>Use Picture</button>
                </div>
              </section>
            </div>

            <div class="profileSecurity">
              <h2>Security</h2>
              <form class="changePasswordNavigation" method="GET">
                <input type="hidden" name="view" value="change-password">
                <button class="changePasswordButton" type="submit">Change Password</button>
              </form>
            </div>
          </section>
        <?php elseif ($activeView === 'change-password'): ?>
          <section class="changePasswordPage" aria-labelledby="changePasswordTitle">
            <svg class="passwordIconDefinitions" aria-hidden="true" focusable="false">
              <symbol id="change-password-eye-closed" viewBox="0 0 24 24">
                <path d="M3.5 9.25c1.75 3.35 4.58 5.05 8.5 5.05s6.75-1.7 8.5-5.05" />
                <path d="M5.5 12.05 4.1 13.5M8.55 13.75l-.65 1.9M12 14.3v2M15.45 13.75l.65 1.9M18.5 12.05l1.4 1.45" />
              </symbol>
              <symbol id="change-password-eye-open" viewBox="0 0 24 24">
                <path d="M2.75 12s3.35-5 9.25-5 9.25 5 9.25 5-3.35 5-9.25 5-9.25-5-9.25-5Z" />
                <circle cx="12" cy="12" r="2.35" />
              </symbol>
            </svg>

            <a class="backToProfileLink" href="?view=profile">
              <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
              <span>Back to My Profile</span>
            </a>

            <header class="changePasswordHeader">
              <h1 id="changePasswordTitle">Change Password</h1>
              <p>Enter your current password and choose a secure new password.</p>
            </header>

            <form class="changePasswordForm" id="changePasswordForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="changePasswordField">
                  <label for="currentPassword">Current Password :</label>
                  <div class="changePasswordInput">
                    <input
                      id="currentPassword"
                      name="current_password"
                      type="password"
                      required
                      autocomplete="current-password"
                      placeholder="Enter your current password">
                    <button class="changePasswordToggle" type="button" data-password-target="currentPassword" aria-label="Show current password" aria-pressed="false">
                      <svg class="changePasswordIcon changePasswordIconClosed" aria-hidden="true"><use href="#change-password-eye-closed"></use></svg>
                      <svg class="changePasswordIcon changePasswordIconOpen" aria-hidden="true"><use href="#change-password-eye-open"></use></svg>
                    </button>
                  </div>
                </div>

                <div class="changePasswordField">
                  <label for="newPassword">New Password :</label>
                  <div class="changePasswordInput">
                    <input
                      id="newPassword"
                      name="new_password"
                      type="password"
                      required
                      minlength="8"
                      maxlength="72"
                      autocomplete="new-password"
                      aria-describedby="passwordRequirements"
                      placeholder="Enter your new password">
                    <button class="changePasswordToggle" type="button" data-password-target="newPassword" aria-label="Show new password" aria-pressed="false">
                      <svg class="changePasswordIcon changePasswordIconClosed" aria-hidden="true"><use href="#change-password-eye-closed"></use></svg>
                      <svg class="changePasswordIcon changePasswordIconOpen" aria-hidden="true"><use href="#change-password-eye-open"></use></svg>
                    </button>
                  </div>
                </div>

                <div class="changePasswordField">
                  <label for="confirmNewPassword">Confirm New Password :</label>
                  <div class="changePasswordInput">
                    <input
                      id="confirmNewPassword"
                      name="new_password_confirmation"
                      type="password"
                      required
                      minlength="8"
                      maxlength="72"
                      autocomplete="new-password"
                      aria-describedby="passwordConfirmationMessage"
                      placeholder="Confirm your new password">
                    <button class="changePasswordToggle" type="button" data-password-target="confirmNewPassword" aria-label="Show password confirmation" aria-pressed="false">
                      <svg class="changePasswordIcon changePasswordIconClosed" aria-hidden="true"><use href="#change-password-eye-closed"></use></svg>
                      <svg class="changePasswordIcon changePasswordIconOpen" aria-hidden="true"><use href="#change-password-eye-open"></use></svg>
                    </button>
                  </div>
                </div>

                <div class="passwordRequirements" id="passwordRequirements" aria-label="Password requirements">
                  <label class="passwordRequirement" data-password-requirement="length">
                    <input type="checkbox" disabled>
                    <span>At least 8 characters</span>
                  </label>
                  <label class="passwordRequirement" data-password-requirement="number">
                    <input type="checkbox" disabled>
                    <span>Include numbers</span>
                  </label>
                  <label class="passwordRequirement" data-password-requirement="special">
                    <input type="checkbox" disabled>
                    <span>Include a special character</span>
                  </label>
                </div>

                <p class="passwordConfirmationMessage" id="passwordConfirmationMessage" aria-live="polite"></p>
                <p class="changePasswordMessage" id="changePasswordMessage" role="status" aria-live="polite"></p>

                <div class="changePasswordActions">
                  <button class="confirmPasswordButton" id="confirmPasswordButton" type="submit">Confirm</button>
                </div>
            </form>
          </section>
        <?php else: ?>
          <div class="viewHeader">
            <div class="functions">
              <button class="button active" id="openTaskModal" type="button">Add New Task</button>
              <div class="button completedButton" aria-label="<?= $completedTasksToday ?> tasks completed today">
                <span class="completedCount"><?= $completedTasksToday ?></span>
                <span>Completed</span>
              </div>
              <div class="button inverz"><i class="fa-regular fa-trash-can"></i></div>
            </div>
          </div>
          <div class="content<?= $activeView === 'manage-tasks' ? ' manageTasksContent' : '' ?>">
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
              <div class="manageTasksLayout">
                <section class="list manageTaskList" aria-labelledby="manageTaskListTitle">
                  <div class="title manageTaskListTitle" id="manageTaskListTitle">
                    <button class="allTasksFilter is-active" id="showAllTasksButton" type="button" aria-pressed="true">
                      All Tasks
                    </button>
                    <span class="selectedTaskDateLabel" id="selectedTaskDateLabel" hidden></span>
                  </div>
                  <ul id="manageTaskItems">
                    <?php $renderTaskItems($tasks, 'manage-tasks', 'No tasks found.', false, true); ?>
                    <li class="emptyTask filteredTasksEmpty" id="filteredTasksEmpty" hidden>
                      No tasks due on this date.
                    </li>
                  </ul>
                </section>

                <aside class="taskCalendar" id="taskCalendar" aria-labelledby="taskCalendarMonth">
                  <div class="taskCalendarHeader">
                    <button class="taskCalendarNav" id="previousTaskCalendarMonth" type="button" aria-label="Previous month">
                      <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                    </button>
                    <h2 id="taskCalendarMonth"></h2>
                    <button class="taskCalendarNav" id="nextTaskCalendarMonth" type="button" aria-label="Next month">
                      <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    </button>
                  </div>

                  <div class="taskCalendarWeekdays" aria-hidden="true">
                    <span>Mon</span>
                    <span>Tue</span>
                    <span>Wed</span>
                    <span>Thu</span>
                    <span>Fri</span>
                    <span>Sat</span>
                    <span>Sun</span>
                  </div>
                  <div class="taskCalendarDays" id="taskCalendarDays" role="grid" aria-labelledby="taskCalendarMonth"></div>
                  <p class="srOnly" id="taskCalendarStatus" aria-live="polite"></p>
                </aside>
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
                    aria-controls="dateTimeModal">
                    Set Date &amp; Time
                  </button>
                  <button
                    class="taskOptionButton"
                    id="setTaskReminderButton"
                    type="button"
                    aria-expanded="false"
                    aria-controls="reminderModal">
                    Set Reminder
                  </button>
                  <button
                    class="taskOptionButton"
                    id="setTaskRepeatButton"
                    type="button"
                    aria-expanded="false"
                    aria-controls="repeatModal">
                    Repeat
                  </button>
                </div>

                <input id="taskDueAt" name="due_at" type="hidden" value="">
                <input id="taskHasTime" name="has_time" type="hidden" value="0">
                <input id="taskReminders" name="reminders" type="hidden" value="[]">
                <input id="taskRepeat" name="repeat_config" type="hidden" value="">
                <p class="taskDateSummary" id="taskDateSummary">No date selected</p>
                <p class="taskReminderSummary" id="taskReminderSummary">No reminders set</p>
                <p class="taskRepeatSummary" id="taskRepeatSummary">Does not repeat</p>

                <p class="taskModalMessage" id="taskModalMessage" role="alert" aria-live="polite"></p>

                <div class="taskModalActions">
                  <button class="saveTaskButton" id="saveTaskButton" type="submit">Save</button>
                </div>
              </form>
            </section>
          </div>

          <div class="dateTimeModalBackdrop" id="dateTimeModal" hidden>
            <section
              class="dateTimeModal"
              role="dialog"
              aria-modal="true"
              aria-labelledby="dateTimeModalTitle">
              <button class="dateTimeModalClose" id="closeDateTimeModal" type="button" aria-label="Close date and time modal">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
              </button>

              <div class="calendarHeader">
                <button class="calendarNavButton" id="previousCalendarMonth" type="button" aria-label="Previous month">
                  <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                </button>
                <h2 id="dateTimeModalTitle">July 2026</h2>
                <button class="calendarNavButton" id="nextCalendarMonth" type="button" aria-label="Next month">
                  <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                </button>
              </div>

              <div class="calendarWeekdays" aria-hidden="true">
                <span>Sun</span>
                <span>Mon</span>
                <span>Tue</span>
                <span>Wed</span>
                <span>Thu</span>
                <span>Fri</span>
                <span>Sat</span>
              </div>
              <div class="calendarDays" id="calendarDays" role="grid" aria-labelledby="dateTimeModalTitle"></div>

              <fieldset class="quickDateOptions">
                <legend class="srOnly">Quick date selection</legend>
                <label>
                  <input type="radio" name="quick_task_date" value="today" checked>
                  <span>Today</span>
                </label>
                <label>
                  <input type="radio" name="quick_task_date" value="tomorrow">
                  <span>Tomorrow</span>
                </label>
                <label>
                  <input type="radio" name="quick_task_date" value="no-date">
                  <span>No Date</span>
                </label>
              </fieldset>

              <section class="setTimeSection" id="setTimeSection" aria-labelledby="setTimeTitle">
                <div class="setTimeHeader">
                  <h3 id="setTimeTitle">Set Time</h3>
                  <div class="setTimeToggle" role="radiogroup" aria-label="Enable task time">
                    <label>
                      <input id="setTimeYes" type="radio" name="set_task_time" value="yes" checked>
                      <span>Yes</span>
                    </label>
                    <label>
                      <input id="setTimeNo" type="radio" name="set_task_time" value="no">
                      <span>No</span>
                    </label>
                  </div>
                </div>

                <div class="timePicker" id="timePicker">
                  <label>
                    <span>Hour</span>
                    <select id="taskTimeHour" aria-label="Hour">
                      <?php for ($hour = 1; $hour <= 12; $hour++): ?>
                        <option value="<?= $hour ?>"><?= str_pad((string) $hour, 2, '0', STR_PAD_LEFT) ?></option>
                      <?php endfor; ?>
                    </select>
                  </label>
                  <span class="timeSeparator" aria-hidden="true">:</span>
                  <label>
                    <span>Minute</span>
                    <select id="taskTimeMinute" aria-label="Minute">
                      <?php for ($minute = 0; $minute < 60; $minute++): ?>
                        <option value="<?= $minute ?>"><?= str_pad((string) $minute, 2, '0', STR_PAD_LEFT) ?></option>
                      <?php endfor; ?>
                    </select>
                  </label>
                  <label>
                    <span>Period</span>
                    <select id="taskTimePeriod" aria-label="AM or PM">
                      <option value="AM">AM</option>
                      <option value="PM">PM</option>
                    </select>
                  </label>
                </div>
              </section>

              <p class="dateTimeModalMessage" id="dateTimeModalMessage" role="alert" aria-live="polite"></p>

              <div class="dateTimeModalActions">
                <button class="cancelDateTimeButton" id="cancelDateTimeButton" type="button">Cancel</button>
                <button class="applyDateTimeButton" id="applyDateTimeButton" type="button">Apply</button>
              </div>
            </section>
          </div>

          <div class="reminderModalBackdrop" id="reminderModal" hidden>
            <section
              class="reminderModal"
              role="dialog"
              aria-modal="true"
              aria-labelledby="reminderModalTitle">
              <button class="reminderModalClose" id="closeReminderModal" type="button" aria-label="Close reminder modal">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
              </button>

              <div class="reminderModalHeader">
                <h2 id="reminderModalTitle">Set</h2>
                <label class="reminderCountField" for="reminderCount">
                  <span class="srOnly">Number of reminders</span>
                  <select id="reminderCount">
                    <?php for ($reminderCount = 1; $reminderCount <= 5; $reminderCount++): ?>
                      <option value="<?= $reminderCount ?>"><?= $reminderCount ?></option>
                    <?php endfor; ?>
                  </select>
                </label>
                <span id="reminderCountLabel">Reminder</span>
              </div>

              <p class="reminderModalHint">Choose when you want to be notified before the task due time.</p>

              <div class="reminderList" id="reminderList"></div>

              <p class="reminderModalMessage" id="reminderModalMessage" role="alert" aria-live="polite"></p>

              <div class="reminderModalActions">
                <button class="cancelReminderButton" id="cancelReminderButton" type="button">Cancel</button>
                <button class="applyReminderButton" id="applyReminderButton" type="button">Apply</button>
              </div>
            </section>
          </div>

          <div class="repeatModalBackdrop" id="repeatModal" hidden>
            <section
              class="repeatModal"
              role="dialog"
              aria-modal="true"
              aria-labelledby="repeatModalTitle">
              <button class="repeatModalClose" id="closeRepeatModal" type="button" aria-label="Close repeat modal">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
              </button>

              <div class="repeatModalScroll">
                <h2 id="repeatModalTitle">Repeat</h2>
                <p class="repeatModalHint">Choose how often this task should repeat.</p>

                <fieldset class="repeatFrequencyOptions">
                  <legend class="srOnly">Repeat frequency</legend>
                  <label>
                    <input type="radio" name="task_repeat_frequency" value="daily" checked>
                    <span>Daily</span>
                  </label>
                  <label>
                    <input type="radio" name="task_repeat_frequency" value="weekly">
                    <span>Weekly</span>
                  </label>
                  <label>
                    <input type="radio" name="task_repeat_frequency" value="monthly">
                    <span>Monthly</span>
                  </label>
                  <label>
                    <input type="radio" name="task_repeat_frequency" value="custom">
                    <span>Custom</span>
                  </label>
                </fieldset>

                <section class="customRepeatSection" id="customRepeatSection" aria-labelledby="customRepeatTitle" hidden>
                  <h3 id="customRepeatTitle">Repeat Every</h3>
                  <div class="repeatEveryFields">
                    <label class="srOnly" for="repeatInterval">Repeat interval</label>
                    <input id="repeatInterval" type="number" min="1" max="999" step="1" inputmode="numeric" value="1">
                    <label class="srOnly" for="repeatUnit">Repeat interval unit</label>
                    <select id="repeatUnit">
                      <option value="day">Day</option>
                      <option value="week">Weeks</option>
                      <option value="month">Month</option>
                    </select>
                  </div>

                  <fieldset class="repeatOnWeek" id="repeatOnWeek" hidden>
                    <legend>Repeat on</legend>
                    <div class="repeatWeekDays">
                      <label><input type="checkbox" value="0"><span>Sun</span></label>
                      <label><input type="checkbox" value="1"><span>Mon</span></label>
                      <label><input type="checkbox" value="2"><span>Tue</span></label>
                      <label><input type="checkbox" value="3"><span>Wed</span></label>
                      <label><input type="checkbox" value="4"><span>Thu</span></label>
                      <label><input type="checkbox" value="5"><span>Fri</span></label>
                      <label><input type="checkbox" value="6"><span>Sat</span></label>
                    </div>
                  </fieldset>

                  <fieldset class="repeatOnMonth" id="repeatOnMonth" hidden>
                    <legend>Repeat on day</legend>
                    <div class="repeatMonthDays">
                      <?php for ($monthDay = 1; $monthDay <= 31; $monthDay++): ?>
                        <label>
                          <input type="radio" name="repeat_month_day" value="<?= $monthDay ?>">
                          <span><?= $monthDay ?></span>
                        </label>
                      <?php endfor; ?>
                    </div>
                  </fieldset>
                </section>

                <section class="repeatEndsSection" aria-labelledby="repeatEndsTitle">
                  <h3 id="repeatEndsTitle">Repeat ends at</h3>
                  <fieldset class="repeatEndOptions">
                    <legend class="srOnly">Repeat ending</legend>
                    <label>
                      <input type="radio" name="task_repeat_end" value="endlessly" checked>
                      <span>Endlessly</span>
                    </label>
                    <label>
                      <input type="radio" name="task_repeat_end" value="date">
                      <span>A date</span>
                    </label>
                    <label>
                      <input type="radio" name="task_repeat_end" value="count">
                      <span>Repeat Counts</span>
                    </label>
                  </fieldset>

                  <div class="repeatEndDateSection" id="repeatEndDateSection" hidden>
                    <div class="repeatCalendarHeader">
                      <button id="previousRepeatMonth" type="button" aria-label="Previous month">
                        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                      </button>
                      <h4 id="repeatCalendarMonth">Month Year</h4>
                      <button id="nextRepeatMonth" type="button" aria-label="Next month">
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                      </button>
                    </div>
                    <div class="repeatCalendarWeekdays" aria-hidden="true">
                      <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span>
                      <span>Thu</span><span>Fri</span><span>Sat</span>
                    </div>
                    <div class="repeatCalendarDays" id="repeatCalendarDays" role="grid" aria-labelledby="repeatCalendarMonth"></div>
                    <p class="repeatEndDateSummary" id="repeatEndDateSummary">Select an end date</p>
                  </div>

                  <label class="repeatCountField" id="repeatCountField" for="repeatCount" hidden>
                    <span>Number of repeats</span>
                    <input id="repeatCount" type="number" min="1" max="9999" step="1" inputmode="numeric" value="10">
                  </label>
                </section>

                <p class="repeatModalMessage" id="repeatModalMessage" role="alert" aria-live="polite"></p>

                <div class="repeatModalActions">
                  <button class="cancelRepeatButton" id="cancelRepeatButton" type="button">Cancel</button>
                  <button class="applyRepeatButton" id="applyRepeatButton" type="button">Apply</button>
                </div>
              </div>
            </section>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <script type="module" src="assets/js/app.js"></script>
</body>

</html>
