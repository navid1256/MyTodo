<?php

declare(strict_types=1);

if (!function_exists('formatTaskTimeInfo')) {
    function formatTaskTimeInfo(object $task, bool $showDueDate, bool $showDueTime): string
    {
        $dueAt = (string) ($task->due_at ?? '');
        $hasTime = !empty($task->has_time);
        $createdAt = (string) ($task->created_at ?? '');

        $content = match (true) {
            $showDueDate && $dueAt !== '' => 'Due ' . date('M j, Y', strtotime($dueAt)) . ' ' . ($hasTime ? 'at ' . date('h:i A', strtotime($dueAt)) : '· No time'),
            $showDueDate => 'No due date',
            $showDueTime && $dueAt !== '' => $hasTime ? 'Due at ' . date('h:i A', strtotime($dueAt)) : 'No time set',
            default => 'Created At ' . htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8'),
        };

        return '<span class="created-at">' . $content . '</span>';
    }
}

if (!function_exists('renderSingleTaskItem')) {
    function renderSingleTaskItem(object $task, string $viewName, bool $showDueDate, bool $showDueTime): void
    {
        $taskId = (int) ($task->id ?? 0);
        $title = (string) ($task->title ?? '');
        $isDone = !empty($task->is_done);
        $dueAt = (string) ($task->due_at ?? '');
        $taskDate = $dueAt !== '' ? substr($dueAt, 0, 10) : '';

        $itemClass = $isDone ? 'taskItem checked' : 'taskItem';
        $iconClass = $isDone ? 'fa-regular fa-square-check' : 'fa-regular fa-square';
        $ariaPressed = $isDone ? 'true' : 'false';
        $ariaLabel = $isDone ? 'Mark task as incomplete' : 'Mark task as completed';
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $timeInfo = formatTaskTimeInfo($task, $showDueDate, $showDueTime);
        $deleteUrl = '?view=' . urlencode($viewName) . '&amp;delete_task=' . $taskId;

        echo "<li class=\"{$itemClass}\" data-task-id=\"{$taskId}\" data-task-date=\"{$taskDate}\">\n"
            . "    <button class=\"taskToggleButton\" type=\"button\" data-task-toggle data-task-id=\"{$taskId}\" aria-pressed=\"{$ariaPressed}\" aria-label=\"{$ariaLabel}\">\n"
            . "        <i class=\"{$iconClass}\" aria-hidden=\"true\"></i>\n"
            . "    </button>\n"
            . "    <span>{$escapedTitle}</span>\n"
            . "    <div class=\"info\">\n"
            . "        {$timeInfo}\n"
            . "        <a class=\"deleteTaskLink\" href=\"{$deleteUrl}\" aria-label=\"Delete task {$escapedTitle}\" onclick=\"return confirm('Are You Sure To Delete This Task ?\\n{$escapedTitle}')\">\n"
            . "            <i class=\"fa-regular fa-trash-can\" aria-hidden=\"true\"></i>\n"
            . "        </a>\n"
            . "    </div>\n"
            . "</li>\n";
    }
}

if (!function_exists('renderTaskItems')) {
    function renderTaskItems(
        ?array $taskItems,
        string $viewName,
        string $emptyMessage,
        bool $showDueTime = false,
        bool $showDueDate = false
    ): void {
        $items = $taskItems ?? [];
        if (empty($items)) {
            $emptyClass = $showDueDate ? 'emptyTask allTasksEmpty' : 'emptyTask';
            $emptyId = $showDueDate ? ' id="allTasksEmpty"' : '';
            $escapedMsg = htmlspecialchars($emptyMessage, ENT_QUOTES, 'UTF-8');
            echo "<li class=\"{$emptyClass}\"{$emptyId}>{$escapedMsg}</li>\n";
            return;
        }

        foreach ($items as $task) {
            renderSingleTaskItem($task, $viewName, $showDueDate, $showDueTime);
        }
    }
}

$renderTaskItems = static function (
    ?array $taskItems,
    string $viewName,
    string $emptyMessage,
    bool $showDueTime = false,
    bool $showDueDate = false
): void {
    renderTaskItems($taskItems, $viewName, $emptyMessage, $showDueTime, $showDueDate);
};
