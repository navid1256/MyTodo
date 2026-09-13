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
use App\Services\RepeatScheduleCalculator;
use App\Services\RepeatService;

// Never load application bootstrap or .env: an explicitly configured test database is mandatory.
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

$pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
assertLifecycleValue($databaseNames[0], $pdo->query('SELECT DATABASE()')->fetchColumn(), 'Connected database must match the validated test DSN.');
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
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "PASS repeat-lifecycle integration: ownership, pause/resume/cancel, reminders, cursor/count, terminal states, and outer transaction verified; fixtures rolled back.\n";
