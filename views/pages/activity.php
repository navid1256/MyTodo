<?php

/** @var array $completedTasks */
/** @var DateTimeZone $activityTimezone */

$todayKey = (new DateTimeImmutable('today', $activityTimezone))->format('Y-m-d');
$yesterdayKey = (new DateTimeImmutable('yesterday', $activityTimezone))->format('Y-m-d');
$completedTaskGroups = [];

foreach ($completedTasks as $task) {
    $completedAt = (new DateTimeImmutable(
        (string) $task->completed_at,
        getApplicationTimezone()
    ))->setTimezone($activityTimezone);
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
        <h1 class="activityPageTitle" id="activityPageTitle">Completed Tasks</h1>

        <?php if (!$completedTaskGroups): ?>
            <p class="activityEmpty">No completed tasks found.</p>
        <?php else: ?>
            <?php foreach ($completedTaskGroups as $group): ?>
                <section class="activityDateGroup">
                    <h2><?= htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <ul>
                        <?php foreach ($group['tasks'] as $item): ?>
                            <?php $task = $item['task']; ?>
                            <li class="activityTaskItem">
                                <i class="fa-regular fa-square-check" aria-hidden="true"></i>
                                <span class="activityTaskTitle"><?= htmlspecialchars((string) $task->title, ENT_QUOTES, 'UTF-8') ?></span>
                                <time datetime="<?= htmlspecialchars($item['completed_at']->format(DateTimeInterface::ATOM), ENT_QUOTES, 'UTF-8') ?>">
                                    Completed at <?= htmlspecialchars($item['completed_at']->format('h:i A'), ENT_QUOTES, 'UTF-8') ?>
                                </time>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
