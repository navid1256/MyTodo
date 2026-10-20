<?php

declare(strict_types=1);

use App\Exceptions\RepeatRuleNotFoundException;
use App\Exceptions\RepeatRuleStateException;
use App\Repositories\ReminderRepository;
use App\Repositories\RepeatRuleRepository;
use App\Repositories\TaskRepository;
use App\Services\ReminderService;
use App\Services\RepeatOccurrencePlanner;
use App\Services\RepeatRuleValidator;
use App\Services\RepeatRuleUpdateData;
use App\Services\RepeatScheduleCalculator;
use App\Services\RepeatService;

// The default entrypoint still requires a dedicated _test database.
// The separate isolated runner may supply only a fully prepared, table-restricted connection.
$isolated = isset($isolatedRepeatConnection)
    && $isolatedRepeatConnection instanceof IsolatedRepeatTestConnection
    && $isolatedRepeatConnection->isReady();
if (!$isolated) {
    $dsn = getenv('MYTODO_TEST_DSN');
    $username = getenv('MYTODO_TEST_DB_USER');
    $password = getenv('MYTODO_TEST_DB_PASSWORD');
    if ($dsn === false || $dsn === '' || $username === false || $username === '' || $password === false) {
        echo "SKIP repeat-lifecycle integration: set MYTODO_TEST_DSN, MYTODO_TEST_DB_USER, and MYTODO_TEST_DB_PASSWORD for an existing migrated MySQL database ending in _test (an empty password is allowed). No database connection was opened.\n";
        exit(0);
    }

    if (!str_starts_with($dsn, 'mysql:')) {
        fwrite(STDERR, "REFUSED repeat-lifecycle integration: use an explicit mysql: DSN with one database name ending in _test.\n");
        exit(1);
    }

    $databaseNames = [];
    foreach (explode(';', substr($dsn, 6)) as $part) {
        [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
        if (strtolower(trim($key)) === 'dbname') {
            $databaseNames[] = trim($value);
        }
    }
    if (count($databaseNames) !== 1 || preg_match('/\A[A-Za-z0-9_]+_test\z/', $databaseNames[0]) !== 1) {
        fwrite(STDERR, "REFUSED repeat-lifecycle integration: the DSN must name exactly one database ending in _test. No database connection was opened.\n");
        exit(1);
    }
}

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

function assertLifecycleValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

function assertLifecycleException(string $exceptionClass, callable $operation): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        if ($exception instanceof $exceptionClass) {
            return;
        }
        throw $exception;
    }
    throw new RuntimeException('Expected ' . $exceptionClass);
}

$pdo = $isolated ? $isolatedRepeatConnection : new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
assertLifecycleValue($isolated ? 'mytodo' : $databaseNames[0], $pdo->query('SELECT DATABASE()')->fetchColumn(), 'Connected database must match the authorized target.');
$tableEngines = $pdo->query(
    "SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME IN ('users', 'tasks', 'task_repeat_rules', 'task_repeat_reminder_templates', 'task_reminders')"
)->fetchAll(PDO::FETCH_KEY_PAIR);
assertLifecycleValue(5, count($tableEngines), 'The test database must already contain the five migrated lifecycle tables.');
foreach ($tableEngines as $table => $engine) {
    assertLifecycleValue('InnoDB', $engine, $table . ' must support rollback before any fixture is written.');
}

$ruleRepository = new RepeatRuleRepository($pdo);
$taskRepository = new TaskRepository($pdo);
$reminderRepository = new ReminderRepository($pdo);
$calculator = new RepeatScheduleCalculator();
$service = new RepeatService($ruleRepository, new RepeatRuleValidator(), $calculator, new RepeatOccurrencePlanner($calculator), $taskRepository, new ReminderService($reminderRepository));
$now = new DateTimeImmutable('2026-09-16 12:00:00', new DateTimeZone('Asia/Tehran'));

$pdo->beginTransaction();
try {
    $userIds = [];
    $insertUser = $pdo->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
    $suffix = bin2hex(random_bytes(8));
    foreach (['a', 'b'] as $label) {
        $insertUser->execute(['lifecycle_' . $label . '_' . $suffix, $label . '_' . $suffix . '@example.test', 'test-only-not-a-login']);
        $userIds[] = (int) $pdo->lastInsertId();
    }
    [$userA, $userB] = $userIds;
    $ruleData = [
        'user_id' => $userA, 'title' => 'Lifecycle fixture', 'start_at' => '2026-09-07 06:00:00',
        'has_time' => true, 'timezone' => 'Asia/Tehran', 'frequency' => 'weekly', 'interval' => 1,
        'unit' => 'week', 'week_days' => [1], 'month_day' => null, 'month_day_mode' => 'clamp',
        'end_type' => 'endlessly', 'end_date' => null, 'repeat_count' => null,
        'next_occurrence_at' => '2026-10-05 06:00:00',
    ];
    $ruleA = $ruleRepository->create($ruleData);
    $ruleData['user_id'] = $userB;
    $ruleB = $ruleRepository->create($ruleData);
    $ruleRepository->saveReminderTemplates($ruleA, [['offset_value' => 30, 'offset_unit' => 'minute']]);
    $tasks = [];
    foreach (['a' => [$userA, $ruleA], 'b' => [$userB, $ruleB]] as $label => [$userId, $ruleId]) {
        foreach ([0 => '2026-09-07 06:00:00', 1 => '2026-09-14 06:00:00', 2 => '2026-09-16 08:30:00', 3 => '2026-09-21 06:00:00', 8 => '2026-09-28 06:00:00'] as $number => $dueAt) {
            $taskId = $taskRepository->create($userId, 'Lifecycle task', $dueAt, true, $ruleId, $number);
            $tasks[$label][$number] = $taskId;
            $reminderRepository->create($taskId, 0, 'minute', $dueAt);
            if ($number === 8) {
                $taskRepository->updateStatus($taskId, $userId, true, '2026-09-15 06:00:00');
            }
        }
        $ruleRepository->updateGenerationState($ruleId, $userId, 8, '2026-10-05 06:00:00', 'active');
    }

    $paused = $service->pauseRule($ruleA, $userA, $now);
    assertLifecycleValue(['status' => 'paused', 'deleted_task_ids' => [$tasks['a'][2], $tasks['a'][3]]], $paused, 'Pause must remove only owned future incomplete tasks, including the exact UTC cutoff.');
    assertLifecycleValue(null, $ruleRepository->findByIdForUserForUpdate($ruleA, $userA)->next_occurrence_at, 'Pause must clear the cursor.');
    assertLifecycleValue([], $reminderRepository->getByTaskId($tasks['a'][3]), 'Deleting future tasks must cascade their reminders.');
    foreach ([$tasks['a'][0], $tasks['a'][1], $tasks['a'][8], ...array_values($tasks['b'])] as $preservedId) {
        assertLifecycleValue(1, count($reminderRepository->getByTaskId($preservedId)), 'Preserved task reminders must survive.');
    }
    assertLifecycleValue(['status' => 'paused', 'deleted_task_ids' => []], $service->pauseRule($ruleA, $userA, $now), 'Pause is idempotent.');
    foreach (['pauseRule', 'resumeRule', 'cancelRule'] as $method) {
        assertLifecycleException(RepeatRuleNotFoundException::class, fn() => $service->$method($ruleB, $userA, $now));
        assertLifecycleValue(true, $pdo->inTransaction(), 'Rejected ownership must preserve the caller transaction.');
    }

    $resumed = $service->resumeRule($ruleA, $userA, $now);
    assertLifecycleValue('active', $resumed['status'], 'Resume must activate a paused rule.');
    assertLifecycleValue(3, $resumed['generated_count'], 'Resume must immediately fill its next 30 days without duplicating the retained completed September 28 task.');
    assertLifecycleValue('2026-10-19 06:00:00', $resumed['next_occurrence_at'], 'Resume must advance the generation cursor beyond the window.');
    $firstFuture = $taskRepository->findFirstFutureIncompleteForRule($ruleA, $userA, '2026-09-16 08:30:00');
    assertLifecycleValue('2026-09-21 06:00:00', $firstFuture->due_at, 'A Wednesday resume must first generate next Monday at the original local time.');
    assertLifecycleValue(9, (int) $firstFuture->repeat_occurrence_number, 'The unique cursor must advance past surviving occurrence eight.');
    assertLifecycleValue(5, (int) $ruleRepository->findByIdForUserForUpdate($ruleA, $userA)->generated_repeats, 'Materialized repeat count must exclude occurrence zero and deleted rows.');
    $dateCount = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE repeat_rule_id = ? AND user_id = ? AND due_at = ?');
    $dateCount->execute([$ruleA, $userA, '2026-09-28 06:00:00']);
    assertLifecycleValue(1, (int) $dateCount->fetchColumn(), 'A retained completed date inside the generated window must remain unique.');
    assertLifecycleValue(1, count($reminderRepository->getByTaskId((int) $firstFuture->id)), 'Resume must recreate reminders using its supplied clock.');
    assertLifecycleValue(['status' => 'active', 'generated_count' => 0, 'next_occurrence_at' => '2026-10-19 06:00:00'], $service->resumeRule($ruleA, $userA, $now), 'Repeated Resume must not fill a second window.');

    assertLifecycleValue('cancelled', $service->cancelRule($ruleA, $userA, $now)['status'], 'An active rule can be cancelled.');
    assertLifecycleValue(['status' => 'cancelled', 'deleted_task_ids' => []], $service->cancelRule($ruleA, $userA, $now), 'Cancel is idempotent.');
    assertLifecycleValue(null, $ruleRepository->findByIdForUserForUpdate($ruleA, $userA)->next_occurrence_at, 'Cancel must clear the generation cursor.');
    assertLifecycleException(RepeatRuleStateException::class, fn() => $service->resumeRule($ruleA, $userA, $now));
    assertLifecycleException(RepeatRuleStateException::class, fn() => $service->pauseRule($ruleA, $userA, $now));
    $service->pauseRule($ruleB, $userB, $now);
    assertLifecycleValue('cancelled', $service->cancelRule($ruleB, $userB, $now)['status'], 'A paused rule can be cancelled.');
    $ruleRepository->updateStatus($ruleB, $userB, 'completed', null);
    foreach (['pauseRule', 'resumeRule', 'cancelRule'] as $method) {
        assertLifecycleException(RepeatRuleStateException::class, fn() => $service->$method($ruleB, $userB, $now));
    }
    assertLifecycleValue(true, $pdo->inTransaction(), 'Lifecycle calls must not commit the outer fixture transaction.');

    // Edit validation uses the real clock; keep these fixtures inside the next generation window.
    $editNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Tehran'));
    $editDueAt = $editNow->modify('+14 days')->setTime(11, 0);
    $originalEditAt = $editDueAt->modify('-2 hours');
    $siblingEditAt = $originalEditAt->modify('+7 days');
    $utc = new DateTimeZone('UTC');
    $databaseOriginalEditAt = $originalEditAt->setTimezone($utc)->format('Y-m-d H:i:s');
    $databaseSiblingEditAt = $siblingEditAt->setTimezone($utc)->format('Y-m-d H:i:s');
    $databaseEditDueAt = $editDueAt->setTimezone($utc)->format('Y-m-d H:i:s');
    $editWeekDays = [(int) $editDueAt->format('w')];
    $editRuleData = $ruleData;
    $editRuleData['user_id'] = $userA;
    $editRuleData['title'] = 'Edit fixture';
    $editRuleData['start_at'] = $databaseOriginalEditAt;
    $editRuleData['next_occurrence_at'] = $databaseOriginalEditAt;
    $editRuleData['week_days'] = $editWeekDays;
    $editRule = $ruleRepository->create($editRuleData);
    $selectedEditTask = $taskRepository->create($userA, 'Before edit', $databaseOriginalEditAt, true, $editRule, 1);
    $siblingEditTask = $taskRepository->create($userA, 'Sibling task', $databaseSiblingEditAt, true, $editRule, 2);
    $reminderRepository->create($selectedEditTask, 30, 'minute', $originalEditAt->modify('-30 minutes')->setTimezone($utc)->format('Y-m-d H:i:s'));
    $reminderRepository->create($siblingEditTask, 30, 'minute', $siblingEditAt->modify('-30 minutes')->setTimezone($utc)->format('Y-m-d H:i:s'));
    $foreignEditTask = $taskRepository->create($userB, 'Foreign edit', $databaseOriginalEditAt, true, $ruleB, 10);
    $originalRule = $ruleRepository->findByIdForUserForUpdate($editRule, $userA);

    $singleEdit = $service->updateSingleOccurrence(
        $selectedEditTask,
        $userA,
        'After edit',
        $editDueAt,
        true,
        [['value' => 15, 'unit' => 'minute']]
    );
    assertLifecycleValue('single', $singleEdit['scope'], 'Single edit must identify its scope.');
    $editedTask = $taskRepository->findById($selectedEditTask, $userA);
    assertLifecycleValue('After edit', $editedTask->title, 'Single edit must update only the selected title.');
    assertLifecycleValue($databaseEditDueAt, $editedTask->due_at, 'Single edit must canonicalize due_at to UTC.');
    assertLifecycleValue($editRule, (int) $editedTask->repeat_rule_id, 'Single edit must preserve the repeat rule.');
    assertLifecycleValue(1, (int) $editedTask->repeat_occurrence_number, 'Single edit must preserve the occurrence number.');
    assertLifecycleValue(1, count($reminderRepository->getByTaskId($selectedEditTask)), 'Single edit must replace reminders.');
    assertLifecycleValue('Edit fixture', $ruleRepository->findByIdForUserForUpdate($editRule, $userA)->title, 'Single edit must not change the rule.');
    assertLifecycleValue('Sibling task', $taskRepository->findById($siblingEditTask, $userA)->title, 'Single edit must preserve siblings.');
    assertLifecycleValue(1, count($reminderRepository->getByTaskId($siblingEditTask)), 'Single edit must preserve sibling reminders.');
    $futureEdit = $service->updateThisAndFuture(
        $selectedEditTask,
        $userA,
        'Split edit',
        $editDueAt,
        true,
        [['value' => 30, 'unit' => 'minute']],
        [
            'frequency' => 'weekly', 'week_days' => $editWeekDays, 'ends' => ['type' => 'endlessly'],
        ],
    );
    assertLifecycleValue('future', $futureEdit['scope'], 'Future edit must identify its scope.');
    assertLifecycleValue(0, (int) $taskRepository->findById($selectedEditTask, $userA)->repeat_occurrence_number, 'Split selected task must become occurrence zero.');
    assertLifecycleValue([], $taskRepository->findById($siblingEditTask, $userA) === null ? [] : ['present'], 'Future incomplete siblings must be removed.');
    assertLifecycleValue('completed', $ruleRepository->findByIdForUserForUpdate($editRule, $userA)->status, 'The old rule must become terminal after a split.');
    $splitRule = $ruleRepository->findByIdForUserForUpdate((int) $futureEdit['repeat_rule_id'], $userA);
    assertLifecycleValue('active', $splitRule->status, 'The new split rule must be active.');
    assertLifecycleValue(true, (int) $futureEdit['generated_count'] > 0, 'The split rule must generate its initial 30-day window.');
    $service->pauseRule((int) $futureEdit['repeat_rule_id'], $userA, $editNow);
    $pausedEdit = $service->updateRule(new RepeatRuleUpdateData([
        'repeat_rule_id' => (int) $futureEdit['repeat_rule_id'],
        'user_id' => $userA,
        'title' => 'Paused split edit',
        'due_at' => $editDueAt,
        'has_time' => true,
        'repeat_config' => ['frequency' => 'weekly', 'week_days' => $editWeekDays, 'ends' => ['type' => 'endlessly']],
        'reminders' => [['value' => 15, 'unit' => 'minute']],
        'now' => $editNow,
    ]));
    assertLifecycleValue('paused', $pausedEdit['status'], 'Paused rule edits must stay paused.');
    assertLifecycleValue(null, $ruleRepository->findByIdForUserForUpdate((int) $futureEdit['repeat_rule_id'], $userA)->next_occurrence_at, 'Paused edits must clear the cursor until Resume.');
    assertLifecycleException(RepeatRuleNotFoundException::class, fn() => $service->updateSingleOccurrence(
        $foreignEditTask,
        $userA,
        'Should fail',
        $editDueAt,
        true,
        []
    ));

    // Splits follow occurrence order, not the selected task's original or replacement date.
    foreach (['forward' => [14, 22], 'backward' => [14, -7], 'previously moved' => [24, 22]] as $scenario => [$selectedDay, $replacementDay]) {
        $replacementDate = $editNow->modify($replacementDay . ' days')->setTime(11, 0);
        $splitData = $editRuleData;
        $splitData['start_at'] = $editNow->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s');
        $splitData['next_occurrence_at'] = $splitData['start_at'];
        $sourceRuleId = $ruleRepository->create($splitData);
        $splitTasks = [];
        foreach ([0 => 25, 1 => $selectedDay, 2 => -2, 3 => 21, 4 => 28, 5 => 29] as $number => $days) {
            $taskDate = $editNow->modify($days . ' days')->setTimezone($utc)->format('Y-m-d H:i:s');
            $splitTasks[$number] = $taskRepository->create($userA, 'Split boundary fixture', $taskDate, true, $sourceRuleId, $number);
            $reminderRepository->create($splitTasks[$number], 0, 'minute', $taskDate);
        }
        $taskRepository->updateStatus($splitTasks[4], $userA, true, $editNow->setTimezone($utc)->format('Y-m-d H:i:s'));
        assertLifecycleException(RepeatRuleNotFoundException::class, fn() => $service->updateThisAndFuture(
            $splitTasks[1], $userB, 'Unauthorized split', $replacementDate, true, [],
            ['frequency' => 'daily', 'ends' => ['type' => 'endlessly']]
        ));
        assertLifecycleValue('active', $ruleRepository->findByIdForUserForUpdate($sourceRuleId, $userA)->status, $scenario . ': rejected ownership must leave the rule active.');

        $split = $service->updateThisAndFuture(
            $splitTasks[1], $userA, 'Split boundary edit', $replacementDate, true, [],
            ['frequency' => 'daily', 'ends' => ['type' => 'endlessly']]
        );
        assertLifecycleValue([$splitTasks[3], $splitTasks[5]], $split['deleted_task_ids'], $scenario . ': delete only later incomplete future occurrences.');
        foreach ([3, 5] as $number) {
            assertLifecycleValue(null, $taskRepository->findById($splitTasks[$number], $userA), $scenario . ': replaced siblings must not remain on the old rule.');
            assertLifecycleValue([], $reminderRepository->getByTaskId($splitTasks[$number]), $scenario . ': deleted sibling reminders must cascade.');
        }
        foreach ([0, 2, 4] as $number) {
            assertLifecycleValue($sourceRuleId, (int) $taskRepository->findById($splitTasks[$number], $userA)->repeat_rule_id, $scenario . ': earlier occurrences, history and completed tasks must keep their rule.');
            assertLifecycleValue(1, count($reminderRepository->getByTaskId($splitTasks[$number])), $scenario . ': preserved reminders must survive.');
        }
        $selectedTask = $taskRepository->findById($splitTasks[1], $userA);
        assertLifecycleValue((int) $split['repeat_rule_id'], (int) $selectedTask->repeat_rule_id, $scenario . ': selected task must move to the new rule.');
        assertLifecycleValue(0, (int) $selectedTask->repeat_occurrence_number, $scenario . ': selected task must become occurrence zero.');
        assertLifecycleValue($replacementDate->setTimezone($utc)->format('Y-m-d H:i:s'), $selectedTask->due_at, $scenario . ': selected task must retain the requested replacement date.');
        assertLifecycleValue('Foreign edit', $taskRepository->findById($foreignEditTask, $userB)->title, $scenario . ': another user\'s task must remain unchanged.');
    }

    // Repository cutoff is inclusive at the exact operation second, not the preceding second.
    $cutoffDate = $editNow->setTimezone($utc);
    $cutoffAt = $cutoffDate->format('Y-m-d H:i:s');
    $boundaryRuleId = $ruleRepository->create($editRuleData);
    $selectedBoundaryTask = $taskRepository->create($userA, 'Selected boundary', $databaseEditDueAt, true, $boundaryRuleId, 0);
    $beforeCutoffTask = $taskRepository->create($userA, 'Past boundary', $cutoffDate->modify('-1 second')->format('Y-m-d H:i:s'), true, $boundaryRuleId, 1);
    $atCutoffTask = $taskRepository->create($userA, 'Exact boundary', $cutoffAt, true, $boundaryRuleId, 2);
    $reminderRepository->create($beforeCutoffTask, 0, 'minute', $cutoffAt);
    $reminderRepository->create($atCutoffTask, 0, 'minute', $cutoffAt);
    assertLifecycleValue([$atCutoffTask], $taskRepository->deleteIncompleteFromOccurrence($boundaryRuleId, $userA, $cutoffAt, 1, $selectedBoundaryTask), 'The exact operation cutoff must be included while the preceding second is preserved.');
    assertLifecycleValue(1, count($reminderRepository->getByTaskId($beforeCutoffTask)), 'The past boundary reminder must survive.');
    assertLifecycleValue([], $reminderRepository->getByTaskId($atCutoffTask), 'The exact boundary reminder must cascade.');
    // Exercise the real task-row payload and title-only edit round trip in both UI languages.
    require_once dirname(__DIR__, 2) . '/views/components/task-items.php';
    foreach (['UTC', 'Asia/Tehran', 'America/Los_Angeles'] as $timezoneName) {
        $displayTimezone = new DateTimeZone($timezoneName);
        foreach ([true, false] as $hasTime) {
            $localDate = $editNow->setTimezone($displayTimezone)->modify('+14 days')->setTime($hasTime ? 23 : 0, $hasTime ? 45 : 0);
            $storedDate = $localDate->setTimezone($utc)->format('Y-m-d H:i:s');
            $timezoneRuleData = $editRuleData;
            $timezoneRuleData['start_at'] = $storedDate;
            $timezoneRuleData['next_occurrence_at'] = $storedDate;
            $timezoneRuleData['timezone'] = 'America/New_York';
            $timezoneRuleData['has_time'] = $hasTime;
            $timezoneRuleId = $ruleRepository->create($timezoneRuleData);
            $timezoneTaskId = $taskRepository->create($userA, 'Timezone fixture', $storedDate, $hasTime, $timezoneRuleId, 0);
            foreach (['english', 'persian'] as $language) {
                $task = $taskRepository->findById($timezoneTaskId, $userA);
                $task->edit_payload = $service->getTaskEditPayload($timezoneTaskId, $userA);
                $translator = new App\Localization\Translator($language, dirname(__DIR__, 2) . '/resources/lang');
                ob_start();
                renderSingleTaskItem($task, 'manage-tasks', true, true, $displayTimezone, $translator);
                $taskHtml = ob_get_clean();
                preg_match('/data-task-edit-payload>(.*?)<\/script>/s', $taskHtml, $payloadMatch);
                $input = json_decode($payloadMatch[1], true, 512, JSON_THROW_ON_ERROR);
                assertLifecycleValue($localDate->format('Y-m-d\TH:i'), $input['due_at'], $timezoneName . ': the form must receive the local day and time in ' . $language . '.');
                $parsedDate = App\Helpers\TimezoneHelper::parseCanonicalDateTime($input['due_at'], $displayTimezone);
                $service->updateSingleOccurrence($timezoneTaskId, $userA, 'Title only', $parsedDate, $hasTime, []);
                assertLifecycleValue($storedDate, $taskRepository->findById($timezoneTaskId, $userA)->due_at, 'A title-only edit must not change UTC storage, including tasks without time.');
            }

            // A user timezone change must not silently redefine the schedule's timezone or anchor.
            $ruleRepository->updateStatus($timezoneRuleId, $userA, 'paused', null);
            $service->updateRule(new RepeatRuleUpdateData([
                'repeat_rule_id' => $timezoneRuleId, 'user_id' => $userA, 'title' => 'Rule title only',
                'due_at' => $localDate, 'has_time' => $hasTime,
                'repeat_config' => ['frequency' => 'weekly', 'week_days' => [1], 'ends' => ['type' => 'endlessly']],
                'reminders' => [],
            ]));
            $savedRule = $ruleRepository->findByIdForUserForUpdate($timezoneRuleId, $userA);
            assertLifecycleValue($storedDate, $savedRule->start_at, 'A rule title edit must preserve the UTC anchor.');
            assertLifecycleValue('America/New_York', $savedRule->timezone, 'A rule title edit must preserve the schedule timezone.');
            $ruleRepository->updateStatus($timezoneRuleId, $userA, 'active', $storedDate);
            $activeEdit = $service->updateRule(new RepeatRuleUpdateData([
                'repeat_rule_id' => $timezoneRuleId, 'user_id' => $userA, 'title' => 'Active rule title only',
                'due_at' => $localDate, 'has_time' => $hasTime,
                'repeat_config' => ['frequency' => 'weekly', 'week_days' => [1], 'ends' => ['type' => 'endlessly']],
                'reminders' => [],
            ]));
            $savedRule = $ruleRepository->findByIdForUserForUpdate((int) $activeEdit['repeat_rule_id'], $userA);
            assertLifecycleValue($storedDate, $savedRule->start_at, 'An active split must preserve the submitted UTC anchor.');
            assertLifecycleValue('America/New_York', $savedRule->timezone, 'An active split must also preserve the original schedule timezone.');
        }
    }
    assertLifecycleValue(true, $pdo->inTransaction(), 'Edit calls must preserve the outer fixture transaction.');
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "PASS repeat-lifecycle integration: ownership, pause/resume/cancel, single/future/paused edits, reminders, cursor/count, terminal states, and outer transaction verified; fixtures rolled back.\n";
