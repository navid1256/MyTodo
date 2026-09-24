<?php

declare(strict_types=1);

function assertContainsText(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}

function assertTextCount(int $expected, string $needle, string $haystack, string $message): void
{
    $actual = substr_count($haystack, $needle);

    if ($actual !== $expected) {
        throw new RuntimeException(sprintf('%s Expected %d occurrence(s), found %d.', $message, $expected, $actual));
    }
}

function extractMethodSource(string $source, string $methodName): string
{
    $tokens = token_get_all($source);
    $tokenCount = count($tokens);

    for ($index = 0; $index < $tokenCount; $index++) {
        if (!is_array($tokens[$index]) || $tokens[$index][0] !== T_FUNCTION) {
            continue;
        }

        $nameIndex = $index + 1;
        while ($nameIndex < $tokenCount) {
            $token = $tokens[$nameIndex];

            if (is_array($token) && $token[0] === T_STRING) {
                break;
            }

            $nameIndex++;
        }

        if ($nameIndex >= $tokenCount || $tokens[$nameIndex][1] !== $methodName) {
            continue;
        }

        $methodSource = '';
        $braceDepth = 0;
        $bodyStarted = false;

        for ($methodIndex = $index; $methodIndex < $tokenCount; $methodIndex++) {
            $token = $tokens[$methodIndex];
            $text = is_array($token) ? $token[1] : $token;
            $methodSource .= $text;

            if ($token === '{') {
                $bodyStarted = true;
                $braceDepth++;
            } elseif ($token === '}') {
                $braceDepth--;

                if ($bodyStarted && $braceDepth === 0) {
                    return $methodSource;
                }
            }
        }
    }

    throw new RuntimeException(sprintf('Required repository method %s() was not found.', $methodName));
}

$repeatRepository = file_get_contents(dirname(__DIR__, 2) . '/App/Repositories/RepeatRuleRepository.php');
$taskRepository = file_get_contents(dirname(__DIR__, 2) . '/App/Repositories/TaskRepository.php');
$reminderRepository = file_get_contents(dirname(__DIR__, 2) . '/App/Repositories/ReminderRepository.php');

$getForUser = extractMethodSource($repeatRepository, 'getForUser');
assertContainsText('r.user_id = :user_id', $getForUser, 'Repeat-rule listing must be scoped to the user.');
assertContainsText('t.repeat_rule_id = r.id', $getForUser, 'Repeat-rule aggregates must correlate by rule ID.');
assertTextCount(2, 't.user_id = r.user_id', $getForUser, 'Both repeat-rule task aggregates must preserve ownership.');
assertContainsText('t.due_at >= :cutoff_at', $getForUser, 'Future-task aggregation must use the supplied cutoff.');

$findOwnedRule = extractMethodSource($repeatRepository, 'findByIdForUserForUpdate');
assertContainsText('id = :repeat_rule_id', $findOwnedRule, 'Owned rule locking must select the requested rule ID.');
assertContainsText('user_id = :user_id', $findOwnedRule, 'Owned rule locking must check the user in SQL.');
assertContainsText('FOR UPDATE', $findOwnedRule, 'Owned rule lookup must lock the selected row.');

$updateRuleStatus = extractMethodSource($repeatRepository, 'updateStatus');
assertContainsText('id = :repeat_rule_id', $updateRuleStatus, 'Rule status updates must target the requested rule ID.');
assertContainsText('user_id = :user_id', $updateRuleStatus, 'Rule status updates must check ownership in SQL.');

$updateGenerationState = extractMethodSource($repeatRepository, 'updateGenerationState');
assertContainsText('id = :repeat_rule_id', $updateGenerationState, 'Generation-state updates must target the requested rule ID.');
assertContainsText('user_id = :user_id', $updateGenerationState, 'Generation-state updates must check ownership in SQL.');
assertContainsText('int $userId', $updateGenerationState, 'Generation-state updates must require a user ID.');

$updateRule = extractMethodSource($repeatRepository, 'updateRule');
assertContainsText('id = :repeat_rule_id', $updateRule, 'Rule edits must target the requested rule ID.');
assertContainsText('user_id = :user_id', $updateRule, 'Rule edits must check ownership in SQL.');

$replaceTemplates = extractMethodSource($repeatRepository, 'replaceReminderTemplates');
assertContainsText('templates.repeat_rule_id = :repeat_rule_id', $replaceTemplates, 'Template deletion must target the requested rule ID.');
assertContainsText('r.id = :repeat_rule_id', $replaceTemplates, 'Template insertion must target the requested rule ID.');
assertTextCount(2, 'r.user_id = :user_id', $replaceTemplates, 'Template deletion and insertion must each check rule ownership.');

$findRecurringTask = extractMethodSource($taskRepository, 'findRecurringByIdForUpdate');
assertContainsText('t.id = :task_id', $findRecurringTask, 'Recurring task locking must select the requested task ID.');
assertContainsText('t.user_id = :user_id', $findRecurringTask, 'Recurring task locking must check ownership in SQL.');
assertContainsText('t.repeat_rule_id IS NOT NULL', $findRecurringTask, 'Recurring task locking must reject non-recurring tasks.');
assertContainsText('FOR UPDATE', $findRecurringTask, 'Recurring task lookup must lock the selected row.');

$deleteFuture = extractMethodSource($taskRepository, 'deleteFutureIncompleteForRule');
assertContainsText('t.repeat_rule_id = :repeat_rule_id', $deleteFuture, 'Future cleanup must target the requested rule ID.');
assertContainsText('t.user_id = :user_id', $deleteFuture, 'Future cleanup must select only owned tasks.');
assertContainsText('t.is_done = 0', $deleteFuture, 'Future cleanup must preserve completed tasks.');
assertContainsText('t.due_at >= :cutoff_at', $deleteFuture, 'Future cleanup must preserve historical tasks.');
assertContainsText('t.id <> :except_task_id', $deleteFuture, 'Future cleanup must honor its optional task exclusion.');
assertContainsText('FOR UPDATE', $deleteFuture, 'Future cleanup must lock selected task IDs before deletion.');
assertContainsText('$this->deleteOwnedTaskIds($taskIds, $userId)', $deleteFuture, 'Future cleanup must delete only its locked owned IDs.');

$deleteFromOccurrence = extractMethodSource($taskRepository, 'deleteIncompleteFromOccurrence');
assertContainsText('string $cutoffAt', $deleteFromOccurrence, 'Occurrence cleanup must require an explicit time cutoff.');
assertContainsText('t.repeat_rule_id = :repeat_rule_id', $deleteFromOccurrence, 'Occurrence cleanup must target the requested rule ID.');
assertContainsText('t.user_id = :user_id', $deleteFromOccurrence, 'Occurrence cleanup must select only owned tasks.');
assertContainsText('t.is_done = 0', $deleteFromOccurrence, 'Occurrence cleanup must preserve completed tasks.');
assertContainsText('t.due_at >= :cutoff_at', $deleteFromOccurrence, 'Occurrence cleanup must preserve historical tasks.');
assertContainsText('t.repeat_occurrence_number >= :occurrence_number', $deleteFromOccurrence, 'Occurrence cleanup must start at the selected occurrence.');
assertContainsText('t.id <> :except_task_id', $deleteFromOccurrence, 'Occurrence cleanup must preserve the selected task.');
assertContainsText('FOR UPDATE', $deleteFromOccurrence, 'Occurrence cleanup must lock selected task IDs before deletion.');
assertContainsText('$this->deleteOwnedTaskIds($taskIds, $userId)', $deleteFromOccurrence, 'Occurrence cleanup must delete only its locked owned IDs.');

$countRepeats = extractMethodSource($taskRepository, 'countRepeatsForRule');
assertContainsText('t.repeat_rule_id = :repeat_rule_id', $countRepeats, 'Repeat counting must target the requested rule ID.');
assertContainsText('t.user_id = :user_id', $countRepeats, 'Repeat counting must check ownership in SQL.');
assertContainsText('t.repeat_occurrence_number > 0', $countRepeats, 'Repeat counting must exclude anchor occurrence zero.');

$maxOccurrence = extractMethodSource($taskRepository, 'getMaxOccurrenceNumber');
assertContainsText('t.repeat_rule_id = :repeat_rule_id', $maxOccurrence, 'Occurrence cursor lookup must target the requested rule ID.');
assertContainsText('t.user_id = :user_id', $maxOccurrence, 'Occurrence cursor lookup must check ownership in SQL.');

$occurrenceDates = extractMethodSource($taskRepository, 'getOccurrenceDatesForRule');
assertContainsText('t.repeat_rule_id = :repeat_rule_id', $occurrenceDates, 'Materialized occurrence dates must target the requested rule.');
assertContainsText('t.user_id = :user_id', $occurrenceDates, 'Materialized occurrence dates must preserve ownership.');
assertContainsText('t.due_at >= :start_at', $occurrenceDates, 'Occurrence dates must respect the generation window start.');
assertContainsText('t.due_at <= :horizon', $occurrenceDates, 'Occurrence dates must respect the generation horizon.');

$firstFutureTask = extractMethodSource($taskRepository, 'findFirstFutureIncompleteForRule');
assertContainsText('t.repeat_rule_id = :repeat_rule_id', $firstFutureTask, 'Future task lookup must target the requested rule ID.');
assertContainsText('t.user_id = :user_id', $firstFutureTask, 'Future task lookup must check ownership in SQL.');
assertContainsText('t.is_done = 0', $firstFutureTask, 'Future task lookup must ignore completed tasks.');
assertContainsText('t.due_at >= :cutoff_at', $firstFutureTask, 'Future task lookup must use the supplied cutoff.');
assertContainsText('FOR UPDATE', $firstFutureTask, 'Future task lookup must lock its result.');

$attachToRule = extractMethodSource($taskRepository, 'attachToRule');
assertContainsText('t.repeat_rule_id = :repeat_rule_id', $attachToRule, 'Rule attachment must assign the requested destination rule.');
assertContainsText('t.id = :task_id', $attachToRule, 'Rule attachment must target the requested task ID.');
assertContainsText('t.user_id = :user_id', $attachToRule, 'Rule attachment must check task ownership in SQL.');
assertContainsText('r.id = :owned_repeat_rule_id', $attachToRule, 'Rule attachment must target the requested destination rule.');
assertContainsText('r.user_id = :rule_user_id', $attachToRule, 'Rule attachment must check destination-rule ownership in SQL.');
assertContainsText('EXISTS (', $attachToRule, 'Rule attachment must guard the destination through EXISTS.');

$deleteOwnedTasks = extractMethodSource($taskRepository, 'deleteOwnedTaskIds');
assertContainsText('user_id = :user_id', $deleteOwnedTasks, 'Locked task deletion must retain the ownership predicate.');
assertContainsText('id IN (', $deleteOwnedTasks, 'Locked task deletion must be constrained to selected IDs.');

$replaceTaskReminders = extractMethodSource($reminderRepository, 'replaceForTask');
assertContainsText('$this->deleteByTaskId($taskId)', $replaceTaskReminders, 'Task reminder replacement must delete the task reminders first.');
assertContainsText('$this->create(', $replaceTaskReminders, 'Task reminder replacement must insert each replacement reminder.');

echo "repeat-repository-contract tests passed\n";
