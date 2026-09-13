<?php

declare(strict_types=1);

use App\Exceptions\RepeatValidationException;
use App\Exceptions\RepeatRuleNotFoundException;
use App\Exceptions\RepeatRuleStateException;
use App\Exceptions\ReminderValidationException;
use App\Repositories\ReminderRepository;
use App\Repositories\RepeatRuleRepository;
use App\Repositories\TaskRepository;
use App\Services\ReminderService;
use App\Services\RepeatService;
use App\Services\RepeatRuleValidator;
use App\Services\RepeatOccurrencePlanner;
use App\Services\RepeatScheduleCalculator;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            "%s\nExpected: %s\nActual: %s",
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assertValidationFails(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (RepeatValidationException) {
        return;
    }

    throw new RuntimeException($message);
}

$timezone = new DateTimeZone('Asia/Tehran');
$start = new DateTimeImmutable('2026-09-05 09:30:00', $timezone);
$validator = new RepeatRuleValidator();
$calculator = new RepeatScheduleCalculator();
$planner = new RepeatOccurrencePlanner($calculator);

$weeklyRule = $validator->validate([
    'frequency' => 'weekly',
    'interval' => 1,
    'unit' => 'week',
    'repeat_on' => [1, 3],
    'week_days' => [3, 1, 3],
    'month_day' => null,
    'month_day_mode' => 'clamp',
    'ends' => [
        'type' => 'endlessly',
        'date' => '',
        'count' => null,
    ],
], $start);

assertSameValue([1, 3], $weeklyRule['week_days'], 'Weekdays must be normalized and deduplicated.');
assertSameValue(
    '2026-09-07 09:30:00',
    $calculator->nextOccurrence($weeklyRule, $start, $start)->format('Y-m-d H:i:s'),
    'A weekly rule must select the next configured weekday.'
);

$monthlyRule = $validator->validate([
    'frequency' => 'monthly',
    'interval' => 1,
    'unit' => 'month',
    'repeat_on' => 31,
    'week_days' => [],
    'month_day' => 31,
    'month_day_mode' => 'clamp',
    'ends' => [
        'type' => 'count',
        'date' => '',
        'count' => 3,
    ],
], new DateTimeImmutable('2027-01-31 08:00:00', $timezone));

assertSameValue(
    '2027-02-28 08:00:00',
    $calculator->nextOccurrence(
        $monthlyRule,
        new DateTimeImmutable('2027-01-31 08:00:00', $timezone),
        new DateTimeImmutable('2027-01-31 08:00:00', $timezone)
    )->format('Y-m-d H:i:s'),
    'Day 31 must clamp to the final valid day of a shorter month.'
);

$customDailyRule = $validator->validate([
    'frequency' => 'custom',
    'interval' => 3,
    'unit' => 'day',
    'repeat_on' => null,
    'week_days' => [],
    'month_day' => null,
    'month_day_mode' => 'clamp',
    'ends' => [
        'type' => 'date',
        'date' => '2026-09-30',
        'count' => null,
    ],
], $start);

assertSameValue(
    '2026-09-08 09:30:00',
    $calculator->nextOccurrence($customDailyRule, $start, $start)->format('Y-m-d H:i:s'),
    'A custom daily interval must preserve the local wall-clock time.'
);

$countRule = $validator->validate([
    'frequency' => 'daily',
    'interval' => 1,
    'unit' => 'day',
    'week_days' => [],
    'month_day' => null,
    'month_day_mode' => 'clamp',
    'ends' => [
        'type' => 'count',
        'date' => '',
        'count' => 2,
    ],
], $start);
$countPlan = $planner->plan(
    $countRule,
    $start,
    new DateTimeImmutable('2026-09-06 09:30:00', $timezone),
    new DateTimeImmutable('2026-10-05 23:59:59', $timezone),
    0
);

assertSameValue(2, count($countPlan['occurrences']), 'Repeat Counts must create the requested number of repeats after the original task.');
assertSameValue(2, $countPlan['generated_repeats'], 'Generated repeat count must exclude the original task.');
assertSameValue('completed', $countPlan['status'], 'A count-limited rule must complete after all repeats are planned.');
assertSameValue(null, $countPlan['next_occurrence'], 'A completed rule must not retain another occurrence.');

$resumeStart = new DateTimeImmutable('2026-09-16 12:00:00', $timezone);
$resumeCandidate = $calculator->nextOccurrence($weeklyRule, $start, $resumeStart);

assertSameValue(
    '2026-09-21 09:30:00',
    $resumeCandidate->format('Y-m-d H:i:s'),
    'Resume must skip missed weekly occurrences and retain the original weekday/time anchor.'
);

$exhaustedPlan = $planner->plan(
    $countRule,
    $start,
    new DateTimeImmutable('2026-09-08 09:30:00', $timezone),
    new DateTimeImmutable('2026-10-05 23:59:59', $timezone),
    2
);

assertSameValue([], $exhaustedPlan['occurrences'], 'An exhausted count rule must not create another task.');
assertSameValue('completed', $exhaustedPlan['status'], 'An exhausted count rule must be terminal.');

$rollingPlan = $planner->plan(
    $weeklyRule,
    $start,
    new DateTimeImmutable('2026-09-07 09:30:00', $timezone),
    new DateTimeImmutable('2026-10-05 23:59:59', $timezone),
    0
);

assertSameValue('active', $rollingPlan['status'], 'An endless rule must remain active after filling the rolling window.');
assertSameValue(true, $rollingPlan['next_occurrence'] > new DateTimeImmutable('2026-10-05 23:59:59', $timezone), 'The next occurrence must move beyond the generated horizon.');

assertValidationFails(
    static fn() => $validator->validate($weeklyRule, null),
    'Repeat settings must require a task due date.'
);

assertValidationFails(
    static fn() => $validator->validate([
        'frequency' => 'weekly',
        'interval' => 1,
        'unit' => 'week',
        'week_days' => [],
        'month_day' => null,
        'month_day_mode' => 'clamp',
        'ends' => ['type' => 'endlessly', 'date' => '', 'count' => null],
    ], $start),
    'A weekly rule must require at least one weekday.'
);

assertValidationFails(
    static fn() => $validator->validate([
        'frequency' => 'daily',
        'interval' => 1,
        'unit' => 'day',
        'week_days' => [],
        'month_day' => null,
        'month_day_mode' => 'clamp',
        'ends' => ['type' => 'date', 'date' => '2026-09-05', 'count' => null],
    ], $start),
    'A repeat end date must be after the first task date.'
);

// Exercise real service/repository SQL against a fresh in-memory database.
// SQLite cannot verify MySQL row locks; the opt-in integration test covers that dialect.
final class LifecycleTestPdo extends PDO
{
    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->exec('PRAGMA foreign_keys = ON');
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace('FOR UPDATE', '', $query), $options);
    }
}

/** @return array{LifecycleTestPdo, RepeatService, RepeatRuleRepository, TaskRepository, ReminderRepository} */
function lifecycleFixture(string $status = 'active', string $endType = 'endlessly', ?int $repeatCount = null, ?string $endDate = null): array
{
    $pdo = new LifecycleTestPdo();
    $pdo->exec('CREATE TABLE task_repeat_rules (
        id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT, start_at TEXT, has_time INTEGER,
        timezone TEXT, frequency TEXT, interval_value INTEGER, interval_unit TEXT, week_days TEXT,
        month_day INTEGER, month_day_mode TEXT, end_type TEXT, end_date TEXT, repeat_count INTEGER,
        generated_repeats INTEGER, next_occurrence_at TEXT, status TEXT, created_at TEXT
    )');
    $pdo->exec('CREATE TABLE tasks (
        id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT, due_at TEXT, has_time INTEGER,
        repeat_rule_id INTEGER, repeat_occurrence_number INTEGER, is_done INTEGER DEFAULT 0,
        completed_at TEXT, UNIQUE (repeat_rule_id, repeat_occurrence_number)
    )');
    $pdo->exec('CREATE TABLE task_repeat_reminder_templates (
        id INTEGER PRIMARY KEY, repeat_rule_id INTEGER, offset_value INTEGER, offset_unit TEXT
    )');
    $pdo->exec('CREATE TABLE task_reminders (
        id INTEGER PRIMARY KEY, task_id INTEGER REFERENCES tasks(id) ON DELETE CASCADE,
        offset_value INTEGER, offset_unit TEXT, remind_at TEXT
    )');
    $insertRule = $pdo->prepare('INSERT INTO task_repeat_rules VALUES (
        ?, ?, \'Weekly task\', \'2026-09-07 06:00:00\', 1, \'Asia/Tehran\', \'weekly\',
        1, \'week\', \'[1]\', NULL, \'clamp\', ?, ?, ?, 8, ?, ?, \'2026-09-01 00:00:00\'
    )');
    $insertRule->execute([1, 10, $endType, $endDate, $repeatCount, $status === 'active' ? '2026-10-05 06:00:00' : null, $status]);
    $insertRule->execute([2, 20, 'endlessly', null, null, '2026-10-05 06:00:00', 'active']);
    $pdo->exec("INSERT INTO tasks (id, user_id, title, due_at, has_time, repeat_rule_id, repeat_occurrence_number, is_done) VALUES
        (1, 10, 'Anchor', '2026-09-07 06:00:00', 1, 1, 0, 0),
        (2, 10, 'Historical', '2026-09-14 06:00:00', 1, 1, 1, 0),
        (3, 10, 'At cutoff', '2026-09-16 08:30:00', 1, 1, 2, 0),
        (4, 10, 'Future incomplete', '2026-09-21 06:00:00', 1, 1, 3, 0),
        (5, 10, 'Future completed', '2026-09-28 06:00:00', 1, 1, 8, 1),
        (6, 20, 'Other user future', '2026-09-21 06:00:00', 1, 2, 1, 0)");
    $pdo->exec("INSERT INTO task_repeat_reminder_templates VALUES (1, 1, 30, 'minute')");
    $pdo->exec("INSERT INTO task_reminders (task_id, offset_value, offset_unit, remind_at)
        SELECT id, 0, 'minute', due_at FROM tasks");
    $ruleRepository = new RepeatRuleRepository($pdo);
    $taskRepository = new TaskRepository($pdo);
    $reminderRepository = new ReminderRepository($pdo);
    $calculator = new RepeatScheduleCalculator();
    $service = new RepeatService($ruleRepository, new RepeatRuleValidator(), $calculator, new RepeatOccurrencePlanner($calculator), $taskRepository, new ReminderService($reminderRepository));

    return [$pdo, $service, $ruleRepository, $taskRepository, $reminderRepository];
}

function assertLifecycleThrows(string $exceptionClass, callable $operation): void
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

$lifecycleNow = new DateTimeImmutable('2026-09-16 12:00:00', $timezone);
$lifecycleTests = [
    'generated reminders use the supplied instant' => static function (): void {
        $service = new ReminderService(new ReminderRepository(new LifecycleTestPdo()));
        $dueAt = new DateTimeImmutable('2001-01-01 12:00:00', new DateTimeZone('UTC'));
        $prepared = $service->prepareGeneratedTaskReminders(
            [['value' => 0, 'unit' => 'minute'], ['value' => 30, 'unit' => 'minute'], ['value' => 1, 'unit' => 'hour']],
            $dueAt, true, new DateTimeImmutable('2001-01-01 11:30:00', new DateTimeZone('UTC'))
        );
        assertSameValue([0], array_column($prepared, 'offset_value'), 'Only reminders strictly after the supplied clock are eligible.');
        assertSameValue('2001-01-01 12:00:00', $prepared[0]['remind_at']->format('Y-m-d H:i:s'), 'Reminder offsets must retain the task date.');
    },
    'pause owns its transaction and preserves historical and completed tasks' => static function () use ($lifecycleNow): void {
        [$pdo, $service, $rules, , $reminders] = lifecycleFixture();
        assertSameValue(['status' => 'paused', 'deleted_task_ids' => [3, 4]], $service->pauseRule(1, 10, $lifecycleNow), 'Pause must include the UTC equality boundary.');
        assertSameValue([1, 2, 5, 6], $pdo->query('SELECT id FROM tasks ORDER BY id')->fetchAll(PDO::FETCH_COLUMN), 'Pause must preserve historical, completed, and other-user rows.');
        assertSameValue([], $reminders->getByTaskId(4), 'Deleted tasks must cascade reminders.');
        assertSameValue(1, count($reminders->getByTaskId(5)), 'Completed future reminders must survive.');
        assertSameValue(null, $rules->findByIdForUserForUpdate(1, 10)->next_occurrence_at, 'Paused rules cannot retain a generation cursor.');
        assertSameValue(false, $pdo->inTransaction(), 'A transaction started by Pause must finish.');
        assertSameValue(['status' => 'paused', 'deleted_task_ids' => []], $service->pauseRule(1, 10, $lifecycleNow), 'Pause must be idempotent.');
    },
    'lifecycle rejects foreign rules without closing an outer transaction' => static function () use ($lifecycleNow): void {
        [$pdo, $service] = lifecycleFixture();
        $pdo->beginTransaction();
        foreach (['pauseRule', 'resumeRule', 'cancelRule'] as $method) {
            assertLifecycleThrows(RepeatRuleNotFoundException::class, fn() => $service->$method(2, 10, $lifecycleNow));
            assertSameValue(true, $pdo->inTransaction(), 'Ownership failure must leave the caller transaction open.');
        }
        $service->pauseRule(1, 10, $lifecycleNow);
        assertSameValue(true, $pdo->inTransaction(), 'Successful Pause must leave the caller transaction open.');
        $pdo->rollBack();
        assertSameValue(6, (int) $pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn(), 'The caller must be able to roll back Pause.');
        assertSameValue('active', $pdo->query('SELECT status FROM task_repeat_rules WHERE id = 1')->fetchColumn(), 'Caller rollback must restore the rule.');
    },
    'resume skips missed and retained occurrences with independent count and cursor' => static function () use ($lifecycleNow): void {
        [$pdo, $service, $rules, $tasks, $reminders] = lifecycleFixture();
        $service->pauseRule(1, 10, $lifecycleNow);
        $result = $service->resumeRule(1, 10, $lifecycleNow);
        assertSameValue(['status' => 'active', 'generated_count' => 3, 'next_occurrence_at' => '2026-10-19 06:00:00'], $result, 'Resume must fill 30 days while preserving the Monday anchor and skipping the retained Sept 28 task.');
        $generated = $pdo->query('SELECT due_at, repeat_occurrence_number FROM tasks WHERE repeat_rule_id = 1 AND repeat_occurrence_number > 8 ORDER BY due_at')->fetchAll(PDO::FETCH_ASSOC);
        assertSameValue([
            ['due_at' => '2026-09-21 06:00:00', 'repeat_occurrence_number' => 9],
            ['due_at' => '2026-10-05 06:00:00', 'repeat_occurrence_number' => 10],
            ['due_at' => '2026-10-12 06:00:00', 'repeat_occurrence_number' => 11],
        ], $generated, 'Generated dates must skip missed dates and existing completed dates without cursor collisions.');
        assertSameValue(5, (int) $rules->findByIdForUserForUpdate(1, 10)->generated_repeats, 'Persist the surviving repeat count, excluding occurrence zero.');
        $firstFuture = $tasks->findFirstFutureIncompleteForRule(1, 10, '2026-09-16 08:30:00');
        assertSameValue(1, count($reminders->getByTaskId((int) $firstFuture->id)), 'New tasks must receive eligible template reminders.');
        assertSameValue(['status' => 'active', 'generated_count' => 0, 'next_occurrence_at' => '2026-10-19 06:00:00'], $service->resumeRule(1, 10, $lifecycleNow->modify('+40 days')), 'Already-active Resume must return its existing state even with a later clock.');
    },
    'resume enforces count and date exhaustion including a retained first candidate' => static function () use ($lifecycleNow): void {
        [$pdo, $service, $rules] = lifecycleFixture('active', 'count', 3);
        $service->pauseRule(1, 10, $lifecycleNow);
        $pdo->exec("UPDATE tasks SET due_at = '2026-09-21 06:00:00' WHERE id = 5");
        assertSameValue(['status' => 'completed', 'generated_count' => 1, 'next_occurrence_at' => null], $service->resumeRule(1, 10, $lifecycleNow), 'Retained occurrences must count once without spending the remaining count again.');
        assertSameValue('2026-09-28 06:00:00', $pdo->query('SELECT due_at FROM tasks WHERE repeat_rule_id = 1 AND repeat_occurrence_number = 9')->fetchColumn(), 'Resume must skip an already materialized first candidate.');
        assertSameValue(3, (int) $rules->findByIdForUserForUpdate(1, 10)->generated_repeats, 'Count-limited generation must save actual materialized repeats.');
        foreach ([['count', 2, null], ['date', null, '2026-09-20']] as [$endType, $count, $endDate]) {
            [, $service] = lifecycleFixture('active', $endType, $count, $endDate);
            $service->pauseRule(1, 10, $lifecycleNow);
            assertSameValue(['status' => 'completed', 'generated_count' => 0, 'next_occurrence_at' => null], $service->resumeRule(1, 10, $lifecycleNow), 'Exhausted end rules must complete without generating tasks.');
        }
    },
    'cancel is idempotent and completed and cancelled rules remain terminal' => static function () use ($lifecycleNow): void {
        foreach (['active', 'paused'] as $status) {
            [$pdo, $service, $rules] = lifecycleFixture($status);
            assertSameValue(['status' => 'cancelled', 'deleted_task_ids' => [3, 4]], $service->cancelRule(1, 10, $lifecycleNow), 'Cancel must clean future incomplete tasks from active or paused rules.');
            assertSameValue(['status' => 'cancelled', 'deleted_task_ids' => []], $service->cancelRule(1, 10, $lifecycleNow), 'Repeated Cancel must succeed.');
            assertSameValue(null, $rules->findByIdForUserForUpdate(1, 10)->next_occurrence_at, 'Cancelled rules cannot retain a cursor.');
            foreach (['pauseRule', 'resumeRule'] as $method) {
                assertLifecycleThrows(RepeatRuleStateException::class, fn() => $service->$method(1, 10, $lifecycleNow));
            }
            assertSameValue(false, $pdo->inTransaction(), 'Failed transitions must roll back their own transaction.');
        }
        [, $service] = lifecycleFixture('completed');
        foreach (['pauseRule', 'resumeRule', 'cancelRule'] as $method) {
            assertLifecycleThrows(RepeatRuleStateException::class, fn() => $service->$method(1, 10, $lifecycleNow));
        }
    },
    'generation failures roll back only service-owned transactions' => static function () use ($lifecycleNow): void {
        foreach ([false, true] as $outerTransaction) {
            [$pdo, $service] = lifecycleFixture();
            $service->pauseRule(1, 10, $lifecycleNow);
            $pdo->exec("INSERT INTO task_repeat_reminder_templates VALUES (2, 1, 30, 'minute')");
            if ($outerTransaction) {
                $pdo->beginTransaction();
            }
            assertLifecycleThrows(ReminderValidationException::class, fn() => $service->resumeRule(1, 10, $lifecycleNow));
            assertSameValue($outerTransaction, $pdo->inTransaction(), 'Failure must preserve transaction ownership.');
            if ($outerTransaction) {
                $pdo->rollBack();
            }
            assertSameValue('paused', $pdo->query('SELECT status FROM task_repeat_rules WHERE id = 1')->fetchColumn(), 'Rollback must restore the paused status.');
            assertSameValue(4, (int) $pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn(), 'Rollback must remove partially generated tasks.');
        }
    },
    'listing applies ownership status and the UTC cutoff' => static function () use ($lifecycleNow): void {
        [, $service] = lifecycleFixture();
        $rules = $service->getRulesForUser(10, 'active', $lifecycleNow);
        assertSameValue(1, count($rules), 'Listing must not expose another user rule.');
        assertSameValue(5, (int) $rules[0]->task_count, 'Listing must include the full owned task count.');
        assertSameValue('2026-09-16 08:30:00', $rules[0]->first_future_task_at, 'Listing must convert its clock to application UTC.');
        assertSameValue([], $service->getRulesForUser(10, 'paused', $lifecycleNow), 'Status filter must apply.');
        assertLifecycleThrows(InvalidArgumentException::class, fn() => $service->getRulesForUser(10, 'invalid', $lifecycleNow));
    },
    'generation state updates require matching rule ownership' => static function (): void {
        [$pdo, , $rules] = lifecycleFixture();
        $before = $pdo->query('SELECT id, generated_repeats, next_occurrence_at, status FROM task_repeat_rules ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $rules->updateGenerationState(
            repeatRuleId: 1, userId: 20, generatedRepeats: 99, nextOccurrenceAt: null, status: 'completed'
        );
        assertSameValue($before, $pdo->query('SELECT id, generated_repeats, next_occurrence_at, status FROM task_repeat_rules ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'A mismatched owner must not change generation count, cursor, or status.');
        $rules->updateGenerationState(
            repeatRuleId: 1, userId: 10, generatedRepeats: 2, nextOccurrenceAt: null, status: 'completed'
        );
        assertSameValue([
            ['id' => 1, 'generated_repeats' => 2, 'next_occurrence_at' => null, 'status' => 'completed'],
            $before[1],
        ], $pdo->query('SELECT id, generated_repeats, next_occurrence_at, status FROM task_repeat_rules ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'The matching owner must update only the requested rule.');
    },
    'Cron generation reconciles count and skips retained dates' => static function (): void {
        [$pdo, $service, $rules] = lifecycleFixture('active', 'count', 5);
        $pdo->exec('DELETE FROM tasks WHERE id IN (3, 4)');
        $rules->updateGenerationState(1, 10, 8, '2026-09-21 06:00:00', 'active');
        $pdo->beginTransaction();
        assertSameValue(3, $service->generateInitialWindow(1, new DateTimeImmutable('2026-10-16 00:00:00', new DateTimeZone('UTC'))), 'Generation must use surviving repeat count, not stale generated_repeats or the unique cursor.');
        assertSameValue(true, $pdo->inTransaction(), 'Initial generation must leave the TaskService transaction open.');
        assertSameValue(11, (int) $pdo->query('SELECT MAX(repeat_occurrence_number) FROM tasks WHERE repeat_rule_id = 1')->fetchColumn(), 'Generation cursor must advance from the surviving maximum.');
        assertSameValue('completed', $rules->findByIdForUserForUpdate(1, 10)->status, 'Retained dates must not consume the remaining count twice.');
        $pdo->rollBack();
    },
];

$lifecycleFailures = 0;
foreach ($lifecycleTests as $name => $test) {
    try {
        $test();
        echo 'PASS ' . $name . "\n";
    } catch (Throwable $exception) {
        $lifecycleFailures++;
        fwrite(STDERR, 'FAIL ' . $name . ': ' . $exception->getMessage() . "\n");
    }
}
if ($lifecycleFailures > 0) {
    exit(1);
}

echo "repeat-backend tests passed (scheduling regressions + 10 lifecycle checks)\n";
