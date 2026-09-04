<?php

declare(strict_types=1);

use App\Helpers\TimezoneHelper;
use App\Localization\Translator;

if (!function_exists('formatTaskTimeInfo')) {
    function formatTaskTimeInfo(
        object $task,
        bool $showDueDate,
        bool $showDueTime,
        DateTimeZone $timezone,
        Translator $translator
    ): string
    {
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
                ['date' => $localizedDueAt->format('M j, Y'), 'time' => $localizedDueAt->format('h:i A')],
            ],
            $showDueDate && $localizedDueAt !== null => [
                'task.due_date_no_time',
                ['date' => $localizedDueAt->format('M j, Y')],
            ],
            $showDueDate => ['task.no_due_date', []],
            $showDueTime && $localizedDueAt !== null && $hasTime => [
                'task.due_at',
                ['time' => $localizedDueAt->format('h:i A')],
            ],
            $showDueTime && $localizedDueAt !== null => ['task.no_time_set', []],
            default => [
                'task.created_at',
                ['date' => $localizedCreatedAt?->format('M j, Y \a\t h:i A') ?? $translator->translate('common.not_available')],
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
    function renderSingleTaskItem(
        object $task,
        string $viewName,
        bool $showDueDate,
        bool $showDueTime,
        DateTimeZone $timezone,
        Translator $translator
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
        $timeInfo = formatTaskTimeInfo($task, $showDueDate, $showDueTime, $timezone, $translator);
        $deleteUrl = '?view=' . urlencode($viewName) . '&amp;delete_task=' . $taskId;
        $deleteLabel = htmlspecialchars(
            $translator->translate('task.delete', ['title' => $title]),
            ENT_QUOTES,
            'UTF-8'
        );
        $confirmation = $translator->translate('task.delete_confirmation', ['title' => $title]);
        $confirmationScript = htmlspecialchars(
            'return confirm(' . json_encode($confirmation, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . ')',
            ENT_QUOTES,
            'UTF-8'
        );

        echo "<li class=\"{$itemClass}\" data-task-id=\"{$taskId}\" data-task-date=\"{$taskDate}\">\n"
            . "    <button class=\"taskToggleButton\" type=\"button\" data-task-toggle data-task-id=\"{$taskId}\" aria-pressed=\"{$ariaPressed}\" data-i18n-aria-label=\"{$toggleTranslationKey}\" aria-label=\"{$ariaLabel}\">\n"
            . "        <i class=\"{$iconClass}\" aria-hidden=\"true\"></i>\n"
            . "    </button>\n"
            . "    <span>{$escapedTitle}</span>\n"
            . "    <div class=\"info\">\n"
            . "        {$timeInfo}\n"
            . "        <a class=\"deleteTaskLink\" href=\"{$deleteUrl}\" data-i18n-aria-label=\"task.delete\" data-title=\"{$escapedTitle}\" aria-label=\"{$deleteLabel}\" onclick=\"{$confirmationScript}\">\n"
            . "            <i class=\"fa-light fa-trash-can\" aria-hidden=\"true\"></i>\n"
            . "        </a>\n"
            . "    </div>\n"
            . "</li>\n";
    }
}

if (!function_exists('renderTaskItems')) {
    function renderTaskItems(
        ?array $taskItems,
        string $viewName,
        string $emptyMessageKey,
        Translator $translator,
        bool $showDueTime = false,
        bool $showDueDate = false,
        ?DateTimeZone $timezone = null
    ): void {
        $displayTimezone = $timezone ?? TimezoneHelper::getClientTimezone();
        $items = $taskItems ?? [];
        if (empty($items)) {
            $emptyClass = $showDueDate ? 'emptyTask allTasksEmpty' : 'emptyTask';
            $emptyId = $showDueDate ? ' id="allTasksEmpty"' : '';
            $escapedMsg = htmlspecialchars($translator->translate($emptyMessageKey), ENT_QUOTES, 'UTF-8');
            echo "<li class=\"{$emptyClass}\"{$emptyId} data-i18n=\"{$emptyMessageKey}\">{$escapedMsg}</li>\n";
            return;
        }

        foreach ($items as $task) {
            renderSingleTaskItem($task, $viewName, $showDueDate, $showDueTime, $displayTimezone, $translator);
        }
    }
}

$renderTaskItems = static function (
    ?array $taskItems,
    string $viewName,
    string $emptyMessageKey,
    Translator $translator,
    bool $showDueTime = false,
    bool $showDueDate = false,
    ?DateTimeZone $timezone = null
): void {
    renderTaskItems($taskItems, $viewName, $emptyMessageKey, $translator, $showDueTime, $showDueDate, $timezone);
};
