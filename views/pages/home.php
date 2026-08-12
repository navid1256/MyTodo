<?php

/** @var array $todayTasks */
/** @var array $tomorrowTasks */
/** @var array $noDateTasks */
/** @var \Closure $renderTaskItems */

?>

<div class="list">
    <div class="title">No Date</div>
    <ul>
        <?php $renderTaskItems(
            $noDateTasks,
            'home',
            'No tasks without a date.'
        ); ?>
    </ul>
</div>

<div class="list scheduledTaskList">
    <div class="title">Today</div>
    <ul>
        <?php $renderTaskItems(
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
        <?php $renderTaskItems(
            $tomorrowTasks,
            'home',
            'No tasks due tomorrow.',
            true
        ); ?>
    </ul>
</div>
