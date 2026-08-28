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
        if ($dateKey === $todayKey) {
            $dateLabel = 'Today';
        } elseif ($dateKey === $yesterdayKey) {
            $dateLabel = 'Yesterday';
        } else {
            $dateLabel = $completedAt->format('F j, Y');
        }

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

$selectedFilter = isset($_GET['filter']) && is_string($_GET['filter']) ? $_GET['filter'] : 'all';
if (!in_array($selectedFilter, ['today', 'yesterday', 'week', 'month', 'all'], true)) {
    $selectedFilter = 'all';
}
?>
<div class="content activityContent">
    <section class="activityPage" aria-labelledby="activityPageTitle">
        <div class="activityPageHeader">
            <h1 class="activityPageTitle" id="activityPageTitle">Completed Tasks</h1>
            <label class="srOnly" for="activityFilter">Filter completed tasks</label>
            <div class="activityFilterControl">
                <select class="activityFilter" id="activityFilter" aria-label="Filter completed tasks">
                    <option value="today" <?= $selectedFilter === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="yesterday" <?= $selectedFilter === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                    <option value="week" <?= $selectedFilter === 'week' ? 'selected' : '' ?>>This Week</option>
                    <option value="month" <?= $selectedFilter === 'month' ? 'selected' : '' ?>>This Month</option>
                    <option value="all" <?= $selectedFilter === 'all' ? 'selected' : '' ?>>All Completed</option>
                </select>
                <i class="activityFilterArrow fa-solid fa-chevron-down" aria-hidden="true"></i>
            </div>
        </div>

        <p class="activityEmpty" id="activityFilterEmpty" <?= !empty($completedTaskGroups) ? 'hidden' : '' ?>>No completed tasks found.</p>

        <?php if (!empty($completedTaskGroups)): ?>
            <?php foreach ($completedTaskGroups as $dateKey => $group): ?>
                <?php
                $isToday = ($dateKey === $todayKey);
                $isGroupVisible = ($selectedFilter === 'all') || ($selectedFilter === 'today' && $isToday);
                ?>
                <section class="activityDateGroup" data-completed-date="<?= htmlspecialchars($dateKey, ENT_QUOTES, 'UTF-8') ?>" <?= $isGroupVisible ? '' : 'hidden' ?>>
                    <h2><?= htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <ul>
                        <?php foreach ($group['tasks'] as $item): ?>
                            <?php
                            $task = $item['task'];
                            $title = (string) ($task->title ?? '');
                            $completedAtText = $item['completed_at']->format('h:i A');
                            $isoCompletedAt = $item['completed_at']->format('c');
                            ?>
                            <li class="activityTaskItem">
                                <i class="fa-regular fa-square-check" aria-hidden="true"></i>
                                <span class="activityTaskTitle"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></span>
                                <time datetime="<?= htmlspecialchars($isoCompletedAt, ENT_QUOTES, 'UTF-8') ?>">Completed at <?= htmlspecialchars($completedAtText, ENT_QUOTES, 'UTF-8') ?></time>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
