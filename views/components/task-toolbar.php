<?php

declare(strict_types=1);

/** @var int $completedTasksToday */

$completedCount = isset($completedTasksToday) ? max(0, (int) $completedTasksToday) : 0;
$escapedCount = htmlspecialchars((string) $completedCount, ENT_QUOTES, 'UTF-8');
?>
<div class="viewHeader">
    <div class="functions">
        <button class="button active" id="openTaskModal" type="button">
            Add New Task
        </button>
        <a class="button completedButton" href="/activity?filter=today" data-dashboard-link aria-label="View <?= $escapedCount ?> tasks completed today">
            <span class="completedCount"><?= $escapedCount ?></span>
            <span>Completed</span>
        </a>
    </div>
</div>
