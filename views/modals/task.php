<?php

/** @var string $csrfToken */
/** @var \App\Localization\Translator $translator */
?>
<div class="taskModalBackdrop" id="taskModal" hidden>
    <section
        class="taskModal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="taskModalTitle">
        <h2 class="srOnly" id="taskModalTitle" data-i18n="task.add_new"><?= htmlspecialchars($translator->translate('task.add_new'), ENT_QUOTES, 'UTF-8') ?></h2>
        <button class="taskModalClose" id="closeTaskModal" type="button" data-i18n-aria-label="task.modal.close" aria-label="<?= htmlspecialchars($translator->translate('task.modal.close'), ENT_QUOTES, 'UTF-8') ?>">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>

        <form id="newTaskForm" novalidate>
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <label class="srOnly" for="taskModalText" data-i18n="task.modal.text_label"><?= htmlspecialchars($translator->translate('task.modal.text_label'), ENT_QUOTES, 'UTF-8') ?></label>
            <textarea
                id="taskModalText"
                name="task_title"
                maxlength="512"
                data-i18n-placeholder="task.modal.text_placeholder"
                placeholder="<?= htmlspecialchars($translator->translate('task.modal.text_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                required></textarea>

            <div class="taskModalOptions">
                <button
                    class="taskOptionButton"
                    id="setTaskDateButton"
                    type="button"
                    aria-expanded="false"
                    aria-controls="dateTimeModal">
                    <span data-i18n="task.modal.set_date_time"><?= htmlspecialchars($translator->translate('task.modal.set_date_time'), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
                <button
                    class="taskOptionButton"
                    id="setTaskReminderButton"
                    type="button"
                    aria-expanded="false"
                    aria-controls="reminderModal">
                    <span data-i18n="task.modal.set_reminder"><?= htmlspecialchars($translator->translate('task.modal.set_reminder'), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
                <button
                    class="taskOptionButton"
                    id="setTaskRepeatButton"
                    type="button"
                    aria-expanded="false"
                    aria-controls="repeatModal">
                    <span data-i18n="task.modal.repeat"><?= htmlspecialchars($translator->translate('task.modal.repeat'), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            </div>

            <input id="taskDueAt" name="due_at" type="hidden" value="">
            <input id="taskHasTime" name="has_time" type="hidden" value="0">
            <input id="taskReminders" name="reminders" type="hidden" value="[]">
            <input id="taskRepeat" name="repeat_config" type="hidden" value="">
            <p class="taskDateSummary" id="taskDateSummary" data-i18n="task.modal.no_date"><?= htmlspecialchars($translator->translate('task.modal.no_date'), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="taskReminderSummary" id="taskReminderSummary" data-i18n="task.modal.no_reminders"><?= htmlspecialchars($translator->translate('task.modal.no_reminders'), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="taskRepeatSummary" id="taskRepeatSummary" data-i18n="task.modal.no_repeat"><?= htmlspecialchars($translator->translate('task.modal.no_repeat'), ENT_QUOTES, 'UTF-8') ?></p>

            <p class="taskModalMessage" id="taskModalMessage" role="alert" aria-live="polite"></p>

            <div class="taskModalActions">
                <button class="saveTaskButton" id="saveTaskButton" type="submit" data-i18n="common.save"><?= htmlspecialchars($translator->translate('common.save'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </form>
    </section>
</div>
