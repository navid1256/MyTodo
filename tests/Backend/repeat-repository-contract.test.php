<?php

declare(strict_types=1);

function assertContainsText(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}

$repeatRepository = file_get_contents(dirname(__DIR__, 2) . '/App/Repositories/RepeatRuleRepository.php');
$taskRepository = file_get_contents(dirname(__DIR__, 2) . '/App/Repositories/TaskRepository.php');

assertContainsText('findByIdForUserForUpdate', $repeatRepository, 'A user-owned locking lookup is required.');
assertContainsText('user_id = :user_id', $repeatRepository, 'Repeat rule mutations must check ownership in SQL.');
assertContainsText('findRecurringByIdForUpdate', $taskRepository, 'Recurring task edits need a locking lookup.');
assertContainsText('t.user_id = :user_id', $taskRepository, 'Recurring task mutations must check ownership in SQL.');

echo "repeat-repository-contract tests passed\n";
