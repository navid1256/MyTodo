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
            $dateLabel = $translator->translate('activity.filter.today');
        } elseif ($dateKey === $yesterdayKey) {
            $dateLabel = $translator->translate('activity.filter.yesterday');
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
            <h1 class="activityPageTitle" id="activityPageTitle" data-i18n="activity.title"><?= htmlspecialchars($translator->translate('activity.title'), ENT_QUOTES, 'UTF-8') ?></h1>
            <label class="srOnly" for="activityFilter" data-i18n="activity.filter_label"><?= htmlspecialchars($translator->translate('activity.filter_label'), ENT_QUOTES, 'UTF-8') ?></label>
            <div class="activityFilterControl">
                <select class="activityFilter" id="activityFilter" data-i18n-aria-label="activity.filter_label" aria-label="<?= htmlspecialchars($translator->translate('activity.filter_label'), ENT_QUOTES, 'UTF-8') ?>">
                    <option value="today" data-i18n="activity.filter.today" <?= $selectedFilter === 'today' ? 'selected' : '' ?>><?= htmlspecialchars($translator->translate('activity.filter.today'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="yesterday" data-i18n="activity.filter.yesterday" <?= $selectedFilter === 'yesterday' ? 'selected' : '' ?>><?= htmlspecialchars($translator->translate('activity.filter.yesterday'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="week" data-i18n="activity.filter.week" <?= $selectedFilter === 'week' ? 'selected' : '' ?>><?= htmlspecialchars($translator->translate('activity.filter.week'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="month" data-i18n="activity.filter.month" <?= $selectedFilter === 'month' ? 'selected' : '' ?>><?= htmlspecialchars($translator->translate('activity.filter.month'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="all" data-i18n="activity.filter.all" <?= $selectedFilter === 'all' ? 'selected' : '' ?>><?= htmlspecialchars($translator->translate('activity.filter.all'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
                <i class="activityFilterArrow fa-solid fa-chevron-down" aria-hidden="true"></i>
            </div>
        </div>

        <p class="activityEmpty" id="activityFilterEmpty" data-i18n="activity.empty" <?= !empty($completedTaskGroups) ? 'hidden' : '' ?>><?= htmlspecialchars($translator->translate('activity.empty'), ENT_QUOTES, 'UTF-8') ?></p>

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
                                <time datetime="<?= htmlspecialchars($isoCompletedAt, ENT_QUOTES, 'UTF-8') ?>" data-i18n="activity.completed_at" data-time="<?= htmlspecialchars($completedAtText, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($translator->translate('activity.completed_at', ['time' => $completedAtText]), ENT_QUOTES, 'UTF-8') ?></time>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
