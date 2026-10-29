<?php

declare(strict_types=1);

use App\Helpers\CalendarDateFormatter;
use App\Helpers\TimezoneHelper;
use App\Localization\Translator;

if (!function_exists('formatTaskTimeInfo')) {
    /** Formats task metadata for the selected calendar without changing its canonical edit values. */
    function formatTaskTimeInfo(
        object $task,
        bool $showDueDate,
        bool $showDueTime,
        DateTimeZone $timezone,
        Translator $translator,
        ?CalendarDateFormatter $dateFormatter = null
    ): string
    {
        $dateFormatter ??= new CalendarDateFormatter('gregorian', $timezone);
        $dueAt = (string) ($task->due_at ?? '');
        $hasTime = !empty($task->has_time);
        $createdAt = (string) ($task->created_at ?? '');
        $localizedDueAt = $dueAt !== ''
            ? (new DateTimeImmutable($dueAt, TimezoneHelper::getApplicationTimezone()))->setTimezone($timezone)
            : null;
        $localizedCreatedAt = $createdAt !== ''
            ? (new DateTimeImmutable($createdAt, TimezoneHelper::getApplicationTimezone()))->setTimezone($timezone)
            : null;

        [$translationKey, $replacements] = match (true) {
            $showDueDate && $localizedDueAt !== null && $hasTime => [
                'task.due_date_time',
                ['date' => $dateFormatter->date($localizedDueAt), 'time' => $dateFormatter->time($localizedDueAt)],
            ],
            $showDueDate && $localizedDueAt !== null => [
                'task.due_date_no_time',
                ['date' => $dateFormatter->date($localizedDueAt)],
            ],
            $showDueDate => ['task.no_due_date', []],
            $showDueTime && $localizedDueAt !== null && $hasTime => [
                'task.due_at',
                ['time' => $dateFormatter->time($localizedDueAt)],
            ],
            $showDueTime && $localizedDueAt !== null => ['task.no_time_set', []],
            default => [
                'task.created_at',
                ['date' => $localizedCreatedAt !== null
                    ? $dateFormatter->dateTime($localizedCreatedAt)
                    : $translator->translate('common.not_available')],
            ],
        };

        $dataAttributes = '';
        foreach ($replacements as $name => $value) {
            $dataAttributes .= ' data-' . $name . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
        }

        return '<span class="created-at" data-i18n="' . $translationKey . '"' . $dataAttributes . '>'
            . htmlspecialchars($translator->translate($translationKey, $replacements), ENT_QUOTES, 'UTF-8')
            . '</span>';
    }
}

if (!function_exists('renderSingleTaskItem')) {
    /** Renders a task row and converts its UTC edit payload to the same timezone used by its displayed date. */
    function renderSingleTaskItem(
        object $task,
        string $viewName,
        bool $showDueDate,
        bool $showDueTime,
        DateTimeZone $timezone,
        Translator $translator,
        ?CalendarDateFormatter $dateFormatter = null
    ): void
    {
        $taskId = (int) ($task->id ?? 0);
        $title = (string) ($task->title ?? '');
        $isDone = !empty($task->is_done);
        $dueAt = (string) ($task->due_at ?? '');
        $taskDate = $dueAt !== ''
            ? (new DateTimeImmutable($dueAt, TimezoneHelper::getApplicationTimezone()))
                ->setTimezone($timezone)
                ->format('Y-m-d')
            : '';

        $itemClass = $isDone ? 'taskItem checked' : 'taskItem';
        $iconClass = $isDone ? 'fa-regular fa-square-check' : 'fa-regular fa-square';
        $ariaPressed = $isDone ? 'true' : 'false';
        $toggleTranslationKey = $isDone ? 'task.mark_incomplete' : 'task.mark_completed';
        $ariaLabel = htmlspecialchars($translator->translate($toggleTranslationKey), ENT_QUOTES, 'UTF-8');
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $timeInfo = formatTaskTimeInfo($task, $showDueDate, $showDueTime, $timezone, $translator, $dateFormatter);
        $deleteLabel = htmlspecialchars(
            $translator->translate('task.delete', ['title' => $title]),
            ENT_QUOTES,
            'UTF-8'
        );
        $editPayload = isset($task->edit_payload) && is_array($task->edit_payload)
            && !$isDone && !empty($task->repeat_rule_id)
            ? $task->edit_payload
            : null;
        if ($editPayload !== null) {
            $editPayload['due_at'] = TimezoneHelper::formatUtcDateTimeForInput($editPayload['due_at'] ?? null, $timezone);
        }
        $editLabel = htmlspecialchars(
            $translator->translate('task.edit', ['title' => $title]),
            ENT_QUOTES,
            'UTF-8'
        );

        echo "<li class=\"{$itemClass}\" data-task-id=\"{$taskId}\" data-task-date=\"{$taskDate}\">\n"
            . "    <button class=\"taskToggleButton\" type=\"button\" data-task-toggle data-task-id=\"{$taskId}\" aria-pressed=\"{$ariaPressed}\" data-i18n-aria-label=\"{$toggleTranslationKey}\" aria-label=\"{$ariaLabel}\">\n"
            . "        <i class=\"{$iconClass}\" aria-hidden=\"true\"></i>\n"
            . "    </button>\n"
            . "    <span class=\"taskTitle\">{$escapedTitle}</span>\n"
            . "    <div class=\"info\">\n"
            . "        {$timeInfo}\n"
            . ($editPayload !== null
                ? "        <button class=\"editTaskButton\" type=\"button\" data-task-edit aria-label=\"{$editLabel}\" title=\"{$editLabel}\"><i class=\"fa-light fa-pen\" aria-hidden=\"true\"></i></button>\n"
                    . "        <script type=\"application/json\" data-task-edit-payload>"
                    . json_encode($editPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
                    . "</script>\n"
                : '')
            . "        <button class=\"deleteTaskButton\" type=\"button\" data-task-delete data-task-id=\"{$taskId}\" data-i18n-aria-label=\"task.delete\" data-title=\"{$escapedTitle}\" aria-label=\"{$deleteLabel}\" title=\"{$deleteLabel}\">\n"
            . "            <i class=\"fa-light fa-trash-can\" aria-hidden=\"true\"></i>\n"
            . "        </button>\n"
            . "    </div>\n"
            . "</li>\n";
    }
}

if (!function_exists('renderTaskItems')) {
    /** Renders task rows using calendar preferences, retaining support for existing timezone-only callers. */
    function renderTaskItems(
        ?array $taskItems,
        string $viewName,
        string $emptyMessageKey,
        Translator $translator,
        bool $showDueTime = false,
        bool $showDueDate = false,
        DateTimeZone|CalendarDateFormatter|null $timezone = null
    ): void {
        $dateFormatter = $timezone instanceof CalendarDateFormatter
            ? $timezone
            : new CalendarDateFormatter('gregorian', $timezone ?? TimezoneHelper::getClientTimezone());
        $displayTimezone = $dateFormatter->timezone;
        $items = $taskItems ?? [];
        if (empty($items)) {
            $emptyClass = $showDueDate ? 'emptyTask allTasksEmpty' : 'emptyTask';
            $emptyId = $showDueDate ? ' id="allTasksEmpty"' : '';
            $escapedMsg = htmlspecialchars($translator->translate($emptyMessageKey), ENT_QUOTES, 'UTF-8');
            echo "<li class=\"{$emptyClass}\"{$emptyId} data-i18n=\"{$emptyMessageKey}\">{$escapedMsg}</li>\n";
            return;
        }

        foreach ($items as $task) {
            renderSingleTaskItem($task, $viewName, $showDueDate, $showDueTime, $displayTimezone, $translator, $dateFormatter);
        }
    }
}

/** Forwards the task list and its display preferences to the shared row renderer. */
$renderTaskItems = static function (
    ?array $taskItems,
    string $viewName,
    string $emptyMessageKey,
    Translator $translator,
    bool $showDueTime = false,
    bool $showDueDate = false,
    DateTimeZone|CalendarDateFormatter|null $timezone = null
): void {
    renderTaskItems($taskItems, $viewName, $emptyMessageKey, $translator, $showDueTime, $showDueDate, $timezone);
};
