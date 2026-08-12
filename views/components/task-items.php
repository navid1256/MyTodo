<?php
$renderTaskItems = static function (
    $taskItems,
    $viewName,
    $emptyMessage,
    $showDueTime = false,
    $showDueDate = false
) {
    if (!sizeof($taskItems)) {
        $emptyClass = $showDueDate ? 'emptyTask allTasksEmpty' : 'emptyTask';
        $emptyId = $showDueDate ? ' id="allTasksEmpty"' : '';
        echo '<li class="' . $emptyClass . '"' . $emptyId . '>'
            . htmlspecialchars($emptyMessage, ENT_QUOTES, 'UTF-8') . '</li>';
        return;
    }

    foreach ($taskItems as $task): ?>
        <?php $taskDate = !empty($task->due_at) ? substr((string) $task->due_at, 0, 10) : ''; ?>
        <li
            class="taskItem<?= $task->is_done ? ' checked' : ''; ?>"
            data-task-date="<?= htmlspecialchars($taskDate, ENT_QUOTES, 'UTF-8') ?>">
            <i class="<?= $task->is_done ? 'fa-regular fa-square-check' : 'fa-regular fa-square'; ?>"></i>
            <span><?= htmlspecialchars($task->title, ENT_QUOTES, 'UTF-8') ?></span>
            <div class="info">
                <?php if ($showDueDate): ?>
                    <?php if (!empty($task->due_at)): ?>
                        <span class="created-at">
                            Due <?= date('M j, Y', strtotime($task->due_at)) ?>
                            <?= !empty($task->has_time) ? 'at ' . date('h:i A', strtotime($task->due_at)) : '· No time' ?>
                        </span>
                    <?php else: ?>
                        <span class="created-at">No due date</span>
                    <?php endif; ?>
                <?php elseif ($showDueTime && !empty($task->due_at)): ?>
                    <?php if (!empty($task->has_time)): ?>
                        <span class="created-at">Due at <?= date('h:i A', strtotime($task->due_at)) ?></span>
                    <?php else: ?>
                        <span class="created-at">No time set</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="created-at">Created At <?= htmlspecialchars($task->created_at, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <a href="?view=<?= urlencode($viewName) ?>&amp;delete_task=<?= $task->id ?>">
                    <i class="fa-regular fa-trash-can" onclick="return confirm('Are You Sure To Delete This Task ?\n<?= htmlspecialchars($task->title, ENT_QUOTES, 'UTF-8') ?>')"></i>
                </a>
            </div>
        </li>
<?php endforeach;
};
?>