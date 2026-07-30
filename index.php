<?php
include "bootstrap/init.php";

if (getCurrentUserId() === 0) {
    header('Location: ' . BASE_URL . 'auth.php');
    exit();
}

$currentUser = getCurrentUser();

if (isset($_GET['delete_task'])&& is_numeric($_GET['delete_task'])) {
    $deletedCount = deleteTask($_GET['delete_task']);
    echo "$deletedCount Tasks Succesfully Deleted";
}

$allowedViews = ['home', 'manage-tasks', 'profile'];
$activeView = $_GET['view'] ?? 'manage-tasks';

if (!in_array($activeView, $allowedViews, true)) {
    $activeView = 'manage-tasks';
}

$tasks = $activeView === 'manage-tasks' ? getTasks() : [];
$todayTasks = [];
$tomorrowTasks = [];
$userProfile = null;
$userTimezone = new DateTimeZone('Asia/Tehran');
$today = new DateTimeImmutable('today', $userTimezone);
$completedTasksToday = countCompletedTasksForDate($today);

if ($activeView === 'home') {
    $tomorrow = $today->modify('+1 day');
    $todayTasks = getTasksForDate($today);
    $tomorrowTasks = getTasksForDate($tomorrow);
}

if ($activeView === 'profile') {
    $userProfile = getCurrentUserProfile();
}
// dd($tasks);

include "views/view-index.php";
