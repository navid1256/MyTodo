<?php

declare(strict_types=1);

/** @var string $activeView */
/** @var string $csrfToken */
/** @var string $renderDate */
/** @var string $renderTimezone */
/** @var int|null $sentNotificationCount */

$activeView = isset($activeView) && is_string($activeView) ? $activeView : 'home';
$csrfToken = isset($csrfToken) && is_string($csrfToken) ? $csrfToken : '';
$renderDate = isset($renderDate) && is_string($renderDate) ? $renderDate : date('Y-m-d');
$renderTimezone = isset($renderTimezone) && is_string($renderTimezone) ? $renderTimezone : 'Asia/Tehran';

$pageStylesheets = [
    'home' => null,
    'manage-tasks' => '/assets/css/pages/manage-tasks.css',
    'activity' => '/assets/css/pages/activity.css',
    'messages' => '/assets/css/pages/messages.css',
    'notifications' => '/assets/css/pages/messages.css',
    'profile' => '/assets/css/pages/profile.css',
    'change-password' => '/assets/css/pages/change-password.css',
    'account-settings' => '/assets/css/pages/account-settings.css',
];
$activePageStylesheet = $pageStylesheets[$activeView] ?? null;
$usesTaskModals = in_array($activeView, ['home', 'activity', 'manage-tasks', 'messages'], true);
$viewClassMap = [
    'profile' => ' profileView',
    'change-password' => ' profileView',
    'account-settings' => ' accountSettingsView',
    'manage-tasks' => ' manageTasksView',
    'activity' => ' activityView',
    'messages' => ' messagesView',
    'notifications' => ' messagesView',
];
$extraViewClass = $viewClassMap[$activeView] ?? '';

$isPartial = (isset($_GET['partial']) && $_GET['partial'] === '1') || (isset($isPartial) && $isPartial);
if ($isPartial) {
    ob_start();
}
?>
<?php if (!$isPartial): ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <title>MyTodo Dashboard</title>
    <script>
        try {
            if (localStorage.getItem('mytodo-theme') === 'dark') {
                document.documentElement.classList.add('dark-mode');
            }
        } catch (error) {
            // Storage access fallback
        }
    </script>
    <link rel="stylesheet" href="/assets/css/core.css">
    <link rel="stylesheet" href="/assets/css/fontawesome.css">
    <?php if ($usesTaskModals): ?>
        <link id="taskModalStylesheet" rel="stylesheet" href="/assets/css/task-modal.css">
    <?php endif; ?>
    <?php if ($activePageStylesheet !== null): ?>
        <link id="activePageStylesheet" rel="stylesheet" href="<?= htmlspecialchars($activePageStylesheet, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/theme.css">
</head>

<body
    data-active-view="<?= htmlspecialchars($activeView, ENT_QUOTES, 'UTF-8') ?>"
    data-render-timezone="<?= htmlspecialchars($renderTimezone, ENT_QUOTES, 'UTF-8') ?>"
    data-render-date="<?= htmlspecialchars($renderDate, ENT_QUOTES, 'UTF-8') ?>">
    <div class="page">
        <?php require_once dirname(__DIR__) . '/components/dashboard-header.php'; ?>

        <div class="main">
            <?php require_once dirname(__DIR__) . '/components/sidebar.php'; ?>
<?php endif; ?>

            <div class="view<?= $extraViewClass ?>" id="tasks">
                <?php
                if (in_array($activeView, ['messages', 'activity', 'manage-tasks', 'home'], true)) {
                    require_once dirname(__DIR__) . '/components/task-toolbar.php';
                }

                $pageFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . $activeView . '.php';
                if ($activeView === 'home' || $activeView === 'manage-tasks') {
                    echo '<div class="content' . ($activeView === 'manage-tasks' ? ' manageTasksContent' : '') . '">';
                    require_once $pageFile;
                    echo '</div>';
                } else {
                    require_once $pageFile;
                }

                if ($usesTaskModals) {
                    require_once dirname(__DIR__) . '/components/task-modals.php';
                }
                ?>
            </div>

<?php if (!$isPartial): ?>
        </div>
    </div>
    <script type="module" src="/assets/js/app.js"></script>
</body>

</html>
<?php else: ?>
<?php
    $partialHtml = (string) ob_get_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'html' => $partialHtml,
        'activeView' => $activeView,
        'pageStylesheet' => $activePageStylesheet,
        'taskModalStylesheet' => $usesTaskModals ? '/assets/css/task-modal.css' : null,
        'renderTimezone' => $renderTimezone,
        'renderDate' => $renderDate,
        'sentNotificationCount' => $sentNotificationCount ?? 0,
    ], JSON_THROW_ON_ERROR);
?>
<?php endif; ?>
