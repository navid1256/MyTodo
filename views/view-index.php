<?php

/** @var array $folders */
/** @var array $tasks */
/** @var array $todayTasks */
/** @var array $tomorrowTasks */
/** @var string $activeView */
$unreadNotifications = isset($unreadNotifications) ? max(0, (int) $unreadNotifications) : 3;

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
            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40'%3E%3Crect fill='%23667eea' width='40' height='40'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='white' font-size='20' font-family='Arial'%3EJD%3C/text%3E%3C/svg%3E" width="40" height="40" alt="John Doe">
            <span class="username">John Doe</span>
            <i class="profileChevron fa-solid fa-chevron-down" aria-hidden="true"></i>
          </button>

          <div class="profileDropdown" id="profileDropdown" hidden>
            <button type="button">
              <i class="fa-regular fa-user" aria-hidden="true"></i>
              <span>My Profile</span>
            </button>
            <button type="button">
              <i class="fa-solid fa-user-gear" aria-hidden="true"></i>
              <span>Account Settings</span>
            </button>
            <button type="button">
              <i class="fa-regular fa-rectangle-list" aria-hidden="true"></i>
              <span>Billing &amp; Plans</span>
            </button>
            <button class="signOutButton" type="button">
              <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
              <span>Sign out</span>
            </button>
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
      <div class="view" id="tasks">
        <div class="viewHeader">
          <div class="title" style="width:50% ;">
            <input type="text" id="taskNameInput" style="width: 76%;margin-left: 5%;line-height: 17px;" placeholder="Add New Task">
            <button id="newTaskBtn" class="Btn clickable">+</button>
          </div>
          <div class="functions">
            <div class="button active">Add New Task</div>
            <div class="button">Completed</div>
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
      </div>
    </div>
  </div>
  <script src="assets/js/jquery-minimal.js"></script>
  <script src="assets/js/script.js"></script>
  <script>
    $(document).ready(function() {
      $('#newFolderBtn').click(function() {
        var input = $('input#newFolderInput');
        if (input.val().length < 3) {
          alert("Folder Name Must Be At Least 3 Characters Long !");
          return;
        }
        $.ajax({
          url: "bootstrap/ajaxHandler.php",
          method: "post",
          data: {
            action: "newFolder",
            foldername: input.val()
          },
          success: function(response) {
            if (response == '1') {
              location.reload();
            } else {
              alert(response);
            }
          },
          error: function(xhr, status, error) {
            alert("Error Creating Folder: " + error);
          }

        });
      });
    });
  </script>
</body>

</html>
