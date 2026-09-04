<?php

declare(strict_types=1);

/** @var array<int, object> $tasks */
/** @var DateTimeZone $taskTimezone */

$tasks = isset($tasks) && is_array($tasks) ? $tasks : [];

require_once dirname(__DIR__) . '/components/task-items.php';
?>

<div class="manageTasksLayout <?= $calendarCssClass ?>">
    <section class="list manageTaskList" aria-labelledby="manageTaskListTitle">
        <div class="title manageTaskListTitle" id="manageTaskListTitle">
            <button class="allTasksFilter is-active" id="showAllTasksButton" type="button" aria-pressed="true" data-i18n="manage.all_tasks">
                <?= htmlspecialchars($translator->translate('manage.all_tasks'), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <span class="selectedTaskDateLabel" id="selectedTaskDateLabel" hidden></span>
        </div>
        <ul id="manageTaskItems">
            <?php renderTaskItems($tasks, 'manage-tasks', 'manage.empty_all', $translator, false, true, $taskTimezone); ?>
            <?php if (!empty($tasks)): ?>
                <li class="emptyTask allTasksEmpty" id="allTasksEmpty" data-i18n="manage.empty_all" hidden><?= htmlspecialchars($translator->translate('manage.empty_all'), ENT_QUOTES, 'UTF-8') ?></li>
            <?php endif; ?>
            <li class="emptyTask filteredTasksEmpty" id="filteredTasksEmpty" data-i18n="manage.empty_date" hidden>
                <?= htmlspecialchars($translator->translate('manage.empty_date'), ENT_QUOTES, 'UTF-8') ?>
            </li>
        </ul>
    </section>

    <aside class="taskCalendar <?= $calendarCssClass ?>" id="taskCalendar" aria-labelledby="taskCalendarMonth">
        <div class="taskCalendarHeader">
            <button class="taskCalendarNav" id="previousTaskCalendarMonth" type="button" data-i18n-aria-label="common.previous_month" aria-label="<?= htmlspecialchars($translator->translate('common.previous_month'), ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
            </button>
            <h2 id="taskCalendarMonth" data-i18n="manage.calendar"><?= htmlspecialchars($translator->translate('manage.calendar'), ENT_QUOTES, 'UTF-8') ?></h2>
            <button class="taskCalendarNav" id="nextTaskCalendarMonth" type="button" data-i18n-aria-label="common.next_month" aria-label="<?= htmlspecialchars($translator->translate('common.next_month'), ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </button>
        </div>

        <div class="taskCalendarWeekdays" aria-hidden="true">
            <span data-i18n="calendar.weekday.mon.short"><?= htmlspecialchars($translator->translate('calendar.weekday.mon.short'), ENT_QUOTES, 'UTF-8') ?></span>
            <span data-i18n="calendar.weekday.tue.short"><?= htmlspecialchars($translator->translate('calendar.weekday.tue.short'), ENT_QUOTES, 'UTF-8') ?></span>
            <span data-i18n="calendar.weekday.wed.short"><?= htmlspecialchars($translator->translate('calendar.weekday.wed.short'), ENT_QUOTES, 'UTF-8') ?></span>
            <span data-i18n="calendar.weekday.thu.short"><?= htmlspecialchars($translator->translate('calendar.weekday.thu.short'), ENT_QUOTES, 'UTF-8') ?></span>
            <span data-i18n="calendar.weekday.fri.short"><?= htmlspecialchars($translator->translate('calendar.weekday.fri.short'), ENT_QUOTES, 'UTF-8') ?></span>
            <span data-i18n="calendar.weekday.sat.short"><?= htmlspecialchars($translator->translate('calendar.weekday.sat.short'), ENT_QUOTES, 'UTF-8') ?></span>
            <span data-i18n="calendar.weekday.sun.short"><?= htmlspecialchars($translator->translate('calendar.weekday.sun.short'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="taskCalendarDays" id="taskCalendarDays" role="grid" aria-labelledby="taskCalendarMonth"></div>
        <p class="srOnly" id="taskCalendarStatus" aria-live="polite"></p>
    </aside>
</div>
