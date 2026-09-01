<?php

declare(strict_types=1);

use App\Helpers\ViteHelper;

/** @var string $activeView */
/** @var string $csrfToken */
/** @var string $renderDate */
/** @var string $renderTimezone */
/** @var bool $timezoneIsPersisted */
/** @var string $effectiveLanguage */
/** @var string $calendarSystem */
/** @var int|null $sentNotificationCount */

$activeView = isset($activeView) && is_string($activeView) ? $activeView : 'home';
$csrfToken = isset($csrfToken) && is_string($csrfToken) ? $csrfToken : '';
$renderDate = isset($renderDate) && is_string($renderDate) ? $renderDate : date('Y-m-d');
$renderTimezone = isset($renderTimezone) && is_string($renderTimezone)
    ? $renderTimezone
    : date_default_timezone_get();
$timezoneIsPersisted = isset($timezoneIsPersisted) && $timezoneIsPersisted === true;
$effectiveLanguage = isset($effectiveLanguage) && in_array($effectiveLanguage, ['english', 'persian'], true)
    ? $effectiveLanguage
    : 'english';
$calendarSystem = isset($calendarSystem) && $calendarSystem === 'jalali'
    ? 'jalali'
    : 'gregorian';
$calendarCssClass = $calendarSystem === 'jalali'
    ? 'jalaliCalendar'
    : 'gregorianCalendar';
$htmlLanguage = $effectiveLanguage === 'persian' ? 'fa' : 'en';

$pageStylesheets = [
    'home' => '/assets/css/pages/home.css',
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
<html lang="<?= $htmlLanguage ?>">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <title>MyTodo</title>
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
    data-timezone-persisted="<?= $timezoneIsPersisted ? '1' : '0' ?>"
    data-effective-language="<?= $effectiveLanguage ?>"
    data-calendar-system="<?= $calendarSystem ?>"
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
    <?php if (ViteHelper::isDevelopment()): ?>
        <script type="module" src="<?= ViteHelper::developmentAssetUrl('@vite/client') ?>"></script>
        <script type="module" src="<?= ViteHelper::developmentAssetUrl('assets/js/app.js') ?>"></script>
    <?php else: ?>
        <script type="module" src="/assets/js/app.js"></script>
    <?php endif; ?>
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
        'timezoneIsPersisted' => $timezoneIsPersisted,
        'effectiveLanguage' => $effectiveLanguage,
        'calendarSystem' => $calendarSystem,
        'renderDate' => $renderDate,
        'sentNotificationCount' => $sentNotificationCount ?? 0,
    ], JSON_THROW_ON_ERROR);
?>
<?php endif; ?>
