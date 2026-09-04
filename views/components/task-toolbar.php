<?php

declare(strict_types=1);

use App\Localization\Translator;

/** @var int $completedTasksToday */
/** @var Translator $translator */

$completedCount = isset($completedTasksToday) ? max(0, (int) $completedTasksToday) : 0;
$escapedCount = htmlspecialchars((string) $completedCount, ENT_QUOTES, 'UTF-8');
?>
<div class="viewHeader">
    <div class="functions">
        <button class="button active" id="openTaskModal" type="button" data-i18n="task.add_new">
            <?= htmlspecialchars($translator->translate('task.add_new'), ENT_QUOTES, 'UTF-8') ?>
        </button>
        <a
            class="button completedButton"
            href="/activity?filter=today"
            data-dashboard-link
            data-i18n-aria-label="task.completed_today_label"
            data-count="<?= $completedCount ?>"
            aria-label="<?= htmlspecialchars($translator->translate('task.completed_today_label', ['count' => $completedCount]), ENT_QUOTES, 'UTF-8') ?>">
            <span class="completedCount"><?= $escapedCount ?></span>
            <span data-i18n="task.completed"><?= htmlspecialchars($translator->translate('task.completed'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
    </div>
</div>
