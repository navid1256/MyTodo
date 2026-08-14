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

$allowedViews = ['home', 'activity', 'manage-tasks', 'messages', 'notifications', 'profile', 'change-password'];
$activeView = $_GET['view'] ?? 'manage-tasks';

if (!in_array($activeView, $allowedViews, true)) {
    $activeView = 'manage-tasks';
}

$userTimezone = getApplicationTimezone();
$activityTimezone = getClientTimezone();
$userProfile = getCurrentUserProfile();
$profileErrors = [];
$profileSuccess = isset($_SESSION['profile_success']) && is_string($_SESSION['profile_success'])
    ? $_SESSION['profile_success']
    : null;
unset($_SESSION['profile_success']);

if ($activeView === 'profile' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $profileAction = isset($_POST['profile_action']) && is_string($_POST['profile_action'])
        ? $_POST['profile_action']
        : '';
    $readProfileValue = static function (string $field): string {
        return isset($_POST[$field]) && is_string($_POST[$field])
            ? trim($_POST[$field])
            : '';
    };
    $profileInput = [
        'firstname' => $readProfileValue('firstname'),
        'lastname' => $readProfileValue('lastname'),
        'job_title' => $readProfileValue('job_title'),
        'date_of_birth' => $readProfileValue('date_of_birth'),
        'gender' => $readProfileValue('gender'),
        'country' => $readProfileValue('country'),
        'avatar_url' => trim((string) ($userProfile->avatar_url ?? '')),
    ];
    $avatarAction = $readProfileValue('avatar_action');
    $avatarChoiceValue = $readProfileValue('avatar_choice');
    $avatarData = isset($_POST['avatar_data']) && is_string($_POST['avatar_data'])
        ? $_POST['avatar_data']
        : '';

    if ($profileAction !== 'save') {
        $profileErrors[] = 'Invalid profile request.';
    } elseif (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $profileErrors[] = 'Your session has expired. Please submit the form again.';
    }

    foreach ([
        'firstname' => 'First name',
        'lastname' => 'Last name',
        'job_title' => 'Job title',
        'country' => 'Country',
    ] as $field => $label) {
        if (mb_strlen($profileInput[$field]) > 100) {
            $profileErrors[] = "$label must not exceed 100 characters.";
        }
    }

    if (!in_array($profileInput['gender'], ['', 'male', 'female', 'other'], true)) {
        $profileErrors[] = 'Please select a valid gender.';
    }

    if (!in_array($avatarAction, ['unchanged', 'boring', 'upload'], true)) {
        $profileErrors[] = 'Please choose a valid profile picture option.';
    }

    if ($avatarAction === 'boring' && (!ctype_digit($avatarChoiceValue) || (int) $avatarChoiceValue < 1 || (int) $avatarChoiceValue > 12)) {
        $profileErrors[] = 'Please choose a valid avatar.';
    }

    if ($profileInput['date_of_birth'] !== '') {
        $birthDate = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $profileInput['date_of_birth'],
            $userTimezone
        );
        $birthDateErrors = DateTimeImmutable::getLastErrors();
        $birthDateIsInvalid = !$birthDate
            || ($birthDateErrors !== false
                && ($birthDateErrors['warning_count'] > 0 || $birthDateErrors['error_count'] > 0))
            || $birthDate->format('Y-m-d') !== $profileInput['date_of_birth'];

        if ($birthDateIsInvalid) {
            $profileErrors[] = 'Please enter a valid date of birth.';
        } elseif ($birthDate > new DateTimeImmutable('today', $userTimezone)) {
            $profileErrors[] = 'Date of birth cannot be in the future.';
        }
    }

    if (!$profileErrors) {
        $newAvatarPath = '';

        try {
            if ($avatarAction !== 'unchanged') {
                $newAvatarPath = storeCurrentUserProfileAvatar(
                    $avatarAction,
                    (int) $avatarChoiceValue,
                    $avatarData
                );
                $profileInput['avatar_url'] = $newAvatarPath;
            }

            updateCurrentUserProfile($profileInput);
            $_SESSION['profile_success'] = 'Your profile has been saved successfully.';

            header('Location: ' . BASE_URL . 'index.php?view=profile');
            exit();
        } catch (PDOException $exception) {
            if ($newAvatarPath !== '') {
                deleteStoredProfileAvatar($newAvatarPath);
            }

            $profileErrors[] = 'Your profile could not be saved. Please try again.';
        } catch (InvalidArgumentException | RuntimeException $exception) {
            if ($newAvatarPath !== '') {
                deleteStoredProfileAvatar($newAvatarPath);
            }

            $profileErrors[] = $exception->getMessage();
        }
    }

    $userProfile = (object) array_merge((array) $userProfile, $profileInput);
}

$tasks = $activeView === 'manage-tasks' ? getTasks() : [];
$notifications = $activeView === 'messages' ? getCurrentUserNotifications() : [];
$sentNotifications = $activeView === 'notifications' ? getCurrentUserSentNotifications() : [];
$sentNotificationCount = $activeView === 'notifications'
    ? count($sentNotifications)
    : countCurrentUserSentNotifications();
$completedTasks = $activeView === 'activity' ? getCurrentUserCompletedTasks() : [];
$noDateTasks = [];
$todayTasks = [];
$tomorrowTasks = [];
$activityToday = new DateTimeImmutable('today', $activityTimezone);
$today = $activityToday;
$completedTasksToday = countCompletedTasksForDate($activityToday);

if ($activeView === 'home') {
    $tomorrow = $today->modify('+1 day');
    $noDateTasks = getTasksWithoutDueDate($today);
    $todayTasks = getTasksForDate($today, $today);
    $tomorrowTasks = getTasksForDate($tomorrow, $today);
}

// dd($tasks);

include "views/view-index.php";
