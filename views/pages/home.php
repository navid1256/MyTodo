<?php

declare(strict_types=1);

/** @var array<int, object> $todayTasks */
/** @var array<int, object> $tomorrowTasks */
/** @var array<int, object> $noDateTasks */
/** @var DateTimeZone $taskTimezone */

$todayTasks = isset($todayTasks) && is_array($todayTasks) ? $todayTasks : [];
$tomorrowTasks = isset($tomorrowTasks) && is_array($tomorrowTasks) ? $tomorrowTasks : [];
$noDateTasks = isset($noDateTasks) && is_array($noDateTasks) ? $noDateTasks : [];

require_once dirname(__DIR__) . '/components/task-items.php';
?>

<div class="list">
    <div class="title" data-i18n="home.no_date"><?= htmlspecialchars($translator->translate('home.no_date'), ENT_QUOTES, 'UTF-8') ?></div>
    <ul>
        <?php renderTaskItems(
            $noDateTasks,
            'home',
            'home.empty_no_date',
            $translator,
            false,
            false,
            $taskTimezone
        ); ?>
    </ul>
</div>

<div class="list scheduledTaskList">
    <div class="title" data-i18n="home.today"><?= htmlspecialchars($translator->translate('home.today'), ENT_QUOTES, 'UTF-8') ?></div>
    <ul>
        <?php renderTaskItems(
            $todayTasks,
            'home',
            'home.empty_today',
            $translator,
            true,
            false,
            $taskTimezone
        ); ?>
    </ul>
</div>

<div class="list scheduledTaskList">
    <div class="title" data-i18n="home.tomorrow"><?= htmlspecialchars($translator->translate('home.tomorrow'), ENT_QUOTES, 'UTF-8') ?></div>
    <ul>
        <?php renderTaskItems(
            $tomorrowTasks,
            'home',
            'home.empty_tomorrow',
            $translator,
            true,
            false,
            $taskTimezone
        ); ?>
    </ul>
</div>
