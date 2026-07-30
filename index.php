<?php
include "bootstrap/init.php";
// use Hekmatinasser\Verta\Verta;
// echo(verta::now());
if (isset($_GET['delete_folder'])&& is_numeric($_GET['delete_folder'])) {
    $deletedCount = deleteFolder($_GET['delete_folder']);
    echo "$deletedCount Folders Succesfully Deleted";
}

if (isset($_GET['delete_task'])&& is_numeric($_GET['delete_task'])) {
    $deletedCount = deleteTask($_GET['delete_task']);
    echo "$deletedCount Tasks Succesfully Deleted";
}

$folders = getFolders();

$allowedViews = ['home', 'manage-tasks'];
$activeView = $_GET['view'] ?? 'manage-tasks';

if (!in_array($activeView, $allowedViews, true)) {
    $activeView = 'manage-tasks';
}

$tasks = $activeView === 'manage-tasks' ? getTasks() : [];
$todayTasks = [];
$tomorrowTasks = [];

if ($activeView === 'home') {
    $userTimezone = new DateTimeZone('Asia/Tehran');
    $today = new DateTimeImmutable('today', $userTimezone);
    $tomorrow = $today->modify('+1 day');
    $todayTasks = getTasksForDate($today);
    $tomorrowTasks = getTasksForDate($tomorrow);
}
// dd($tasks);

include "views/view-index.php";
