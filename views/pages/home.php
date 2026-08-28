<?php

declare(strict_types=1);

/** @var array<int, object> $todayTasks */
/** @var array<int, object> $tomorrowTasks */
/** @var array<int, object> $noDateTasks */

$todayTasks = isset($todayTasks) && is_array($todayTasks) ? $todayTasks : [];
$tomorrowTasks = isset($tomorrowTasks) && is_array($tomorrowTasks) ? $tomorrowTasks : [];
$noDateTasks = isset($noDateTasks) && is_array($noDateTasks) ? $noDateTasks : [];

require_once dirname(__DIR__) . '/components/task-items.php';
?>

<div class="list">
    <div class="title">No Date</div>
    <ul>
        <?php renderTaskItems(
            $noDateTasks,
            'home',
            'No tasks without a date.'
        ); ?>
    </ul>
</div>

<div class="list scheduledTaskList">
    <div class="title">Today</div>
    <ul>
        <?php renderTaskItems(
            $todayTasks,
            'home',
            'No tasks due today.',
            true
        ); ?>
    </ul>
</div>

<div class="list scheduledTaskList">
    <div class="title">Tomorrow</div>
    <ul>
        <?php renderTaskItems(
            $tomorrowTasks,
            'home',
            'No tasks due tomorrow.',
            true
        ); ?>
    </ul>
</div>
