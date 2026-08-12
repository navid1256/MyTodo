<?php

/** @var string $csrfToken */
?>
<div class="taskModalBackdrop" id="taskModal" hidden>
    <section
        class="taskModal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="taskModalTitle">
        <h2 class="srOnly" id="taskModalTitle">Add New Task</h2>
        <button class="taskModalClose" id="closeTaskModal" type="button" aria-label="Close task modal">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>

        <form id="newTaskForm" novalidate>
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <label class="srOnly" for="taskModalText">Task text</label>
            <textarea
                id="taskModalText"
                name="task_title"
                maxlength="512"
                placeholder="Input Text"
                required></textarea>

            <div class="taskModalOptions">
                <button
                    class="taskOptionButton"
                    id="setTaskDateButton"
                    type="button"
                    aria-expanded="false"
                    aria-controls="dateTimeModal">
                    Set Date &amp; Time
                </button>
                <button
                    class="taskOptionButton"
                    id="setTaskReminderButton"
                    type="button"
                    aria-expanded="false"
                    aria-controls="reminderModal">
                    Set Reminder
                </button>
                <button
                    class="taskOptionButton"
                    id="setTaskRepeatButton"
                    type="button"
                    aria-expanded="false"
                    aria-controls="repeatModal">
                    Repeat
                </button>
            </div>

            <input id="taskDueAt" name="due_at" type="hidden" value="">
            <input id="taskHasTime" name="has_time" type="hidden" value="0">
            <input id="taskReminders" name="reminders" type="hidden" value="[]">
            <input id="taskRepeat" name="repeat_config" type="hidden" value="">
            <p class="taskDateSummary" id="taskDateSummary">No date selected</p>
            <p class="taskReminderSummary" id="taskReminderSummary">No reminders set</p>
            <p class="taskRepeatSummary" id="taskRepeatSummary">Does not repeat</p>

            <p class="taskModalMessage" id="taskModalMessage" role="alert" aria-live="polite"></p>

            <div class="taskModalActions">
                <button class="saveTaskButton" id="saveTaskButton" type="submit">Save</button>
            </div>
        </form>
    </section>
</div>