<?php

declare(strict_types=1);

use App\Helpers\TimezoneHelper;

/** @var array<int, object> $completedTasks */
/** @var DateTimeZone|null $activityTimezone */

$completedTasks = isset($completedTasks) && is_array($completedTasks) ? $completedTasks : [];
$appTimezone = TimezoneHelper::getApplicationTimezone();
$tz = $activityTimezone ?? TimezoneHelper::getClientTimezone();
$todayKey = (new DateTimeImmutable('today', $tz))->format('Y-m-d');
$yesterdayKey = (new DateTimeImmutable('yesterday', $tz))->format('Y-m-d');
$completedTaskGroups = [];

foreach ($completedTasks as $task) {
    $completedAt = (new DateTimeImmutable(
        (string) ($task->completed_at ?? 'now'),
        $appTimezone
    ))->setTimezone($tz);
    $dateKey = $completedAt->format('Y-m-d');

    if (!isset($completedTaskGroups[$dateKey])) {
        $dateLabel = $dateKey === $todayKey
            ? 'Today'
            : ($dateKey === $yesterdayKey ? 'Yesterday' : $completedAt->format('F j, Y'));
        $completedTaskGroups[$dateKey] = [
            'label' => $dateLabel,
            'tasks' => [],
        ];
    }

    $completedTaskGroups[$dateKey]['tasks'][] = [
        'task' => $task,
        'completed_at' => $completedAt,
    ];
}
?>
<div class="content activityContent">
    <section class="activityPage" aria-labelledby="activityPageTitle">
        <div class="activityPageHeader">
            <h1 class="activityPageTitle" id="activityPageTitle">Completed Tasks</h1>
            <label class="srOnly" for="activityFilter">Filter completed tasks</label>
            <div class="activityFilterControl">
                <select class="activityFilter" id="activityFilter" aria-label="Filter completed tasks">
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="all" selected>All Completed</option>
                </select>
                <i class="activityFilterArrow fa-solid fa-chevron-down" aria-hidden="true"></i>
            </div>
        </div>

        <?php if (empty($completedTaskGroups)): ?>
            <p class="activityEmpty">No completed tasks found.</p>
        <?php else: ?>
            <?php foreach ($completedTaskGroups as $dateKey => $group): ?>
                <section class="activityDateGroup" data-completed-date="<?= htmlspecialchars($dateKey, ENT_QUOTES, 'UTF-8') ?>">
                    <h2><?= htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <ul>
                        <?php foreach ($group['tasks'] as $item): ?>
                            <?php
                            $task = $item['task'];
                            $taskId = (int) ($task->id ?? 0);
                            $title = (string) ($task->title ?? '');
                            $dueAt = (string) ($task->due_at ?? '');
                            $hasTime = !empty($task->has_time);
                            $completedAtText = $item['completed_at']->format('h:i A');
                            $dueInfo = $dueAt !== ''
                                ? 'Due ' . date('M j, Y', strtotime($dueAt)) . ($hasTime ? ' at ' . date('h:i A', strtotime($dueAt)) : ' · No time')
                                : 'No due date';
                            ?>
                            <li class="activityTaskItem">
                                <div class="activityTaskMain">
                                    <span class="activityTaskTitle"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="activityTaskDue"><?= htmlspecialchars($dueInfo, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <span class="activityTaskCompletedAt">Completed at <?= htmlspecialchars($completedAtText, ENT_QUOTES, 'UTF-8') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
