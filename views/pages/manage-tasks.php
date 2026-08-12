<?php

/** @var array $tasks */
/** @var \Closure $renderTaskItems */

?>

<div class="manageTasksLayout">
    <section class="list manageTaskList" aria-labelledby="manageTaskListTitle">
        <div class="title manageTaskListTitle" id="manageTaskListTitle">
            <button class="allTasksFilter is-active" id="showAllTasksButton" type="button" aria-pressed="true">
                All Tasks
            </button>
            <span class="selectedTaskDateLabel" id="selectedTaskDateLabel" hidden></span>
        </div>
        <ul id="manageTaskItems">
            <?php $renderTaskItems($tasks, 'manage-tasks', 'No tasks found.', false, true); ?>
            <li class="emptyTask filteredTasksEmpty" id="filteredTasksEmpty" hidden>
                No tasks due on this date.
            </li>
        </ul>
    </section>

    <aside class="taskCalendar" id="taskCalendar" aria-labelledby="taskCalendarMonth">
        <div class="taskCalendarHeader">
            <button class="taskCalendarNav" id="previousTaskCalendarMonth" type="button" aria-label="Previous month">
                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
            </button>
            <h2 id="taskCalendarMonth"></h2>
            <button class="taskCalendarNav" id="nextTaskCalendarMonth" type="button" aria-label="Next month">
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </button>
        </div>

        <div class="taskCalendarWeekdays" aria-hidden="true">
            <span>Mon</span>
            <span>Tue</span>
            <span>Wed</span>
            <span>Thu</span>
            <span>Fri</span>
            <span>Sat</span>
            <span>Sun</span>
        </div>
        <div class="taskCalendarDays" id="taskCalendarDays" role="grid" aria-labelledby="taskCalendarMonth"></div>
        <p class="srOnly" id="taskCalendarStatus" aria-live="polite"></p>
    </aside>
</div>