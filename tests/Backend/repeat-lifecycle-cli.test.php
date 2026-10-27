<?php

declare(strict_types=1);

/** Runs only pre-connection guards with inherited test credentials removed and PDO drivers disabled. */
function runLifecycleGuard(array $settings): array
{
    $environment = getenv();
    foreach (array_keys($environment) as $key) {
        if (in_array(strtoupper($key), ['MYTODO_TEST_DSN', 'MYTODO_TEST_DB_USER', 'MYTODO_TEST_DB_PASSWORD'], true)) {
            unset($environment[$key]);
        }
    }
    // Refuse the probe itself if this PHP build can connect without its ini file.
    // Set synthetic values inside PHP because Windows drops empty values from a child environment.
    $noDatabaseBootstrap = <<<'PHP'
if (class_exists('PDO') && PDO::getAvailableDrivers() !== []) {
    fwrite(STDERR, "Guard probe requires PHP without PDO drivers.\n");
    exit(3);
}
foreach (json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR) as $key => $value) {
    putenv($key . '=' . $value);
}
require $argv[1];
PHP;
    $command = [PHP_BINARY, '-n', '-r', $noDatabaseBootstrap, __DIR__ . '/repeat-lifecycle.integration.php', json_encode($settings, JSON_THROW_ON_ERROR)];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start the lifecycle guard probe.');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), (string) $stdout, (string) $stderr];
}

$configured = [
    'MYTODO_TEST_DSN' => 'mysql:host=127.0.0.1;port=1;dbname=guard_test',
    'MYTODO_TEST_DB_USER' => 'guard-only-not-a-login',
    'MYTODO_TEST_DB_PASSWORD' => '',
];
$cases = ['all settings missing' => [[], 0, 'SKIP']];
foreach (array_keys($configured) as $key) {
    $missing = $configured;
    unset($missing[$key]);
    $cases['missing ' . $key] = [$missing, 0, 'SKIP'];
}
foreach (['MYTODO_TEST_DSN', 'MYTODO_TEST_DB_USER'] as $key) {
    $cases['empty ' . $key] = [array_replace($configured, [$key => '']), 0, 'SKIP'];
}
foreach ([
    'SQLite is not a MySQL integration target' => 'sqlite::memory:',
    'database is required' => 'mysql:host=127.0.0.1;port=1',
    'application database is refused' => 'mysql:dbname=mytodo',
    'empty database is refused' => 'mysql:dbname=',
    'invalid database name is refused' => 'mysql:dbname=my-todo_test',
    'duplicate test databases are refused' => 'mysql:dbname=first_test;dbname=second_test',
    'mixed-case duplicate database is refused' => 'mysql:dbname=mytodo;DBNAME=guard_test',
] as $name => $dsn) {
    $cases[$name] = [array_replace($configured, ['MYTODO_TEST_DSN' => $dsn]), 1, 'REFUSED'];
}

foreach ($cases as $name => [$settings, $expectedCode, $expectedPrefix]) {
    [$code, $stdout, $stderr] = runLifecycleGuard($settings);
    $output = $stdout . $stderr;
    if ($code !== $expectedCode || !str_starts_with($output, $expectedPrefix . ' repeat-lifecycle integration:')
        || str_contains($output, 'PASS repeat-lifecycle integration:')) {
        throw new RuntimeException('Lifecycle guard failed: ' . $name . "\n" . $output);
    }
    echo 'PASS lifecycle guard: ' . $name . "\n";
}

echo 'PASS ' . count($cases) . " lifecycle CLI guards; MySQL integration was not run and no PDO driver was loaded.\n";

require_once __DIR__ . '/fixtures/isolated-repeat-test-connection.php';
$prefix = 'mytodo_repeat_' . str_repeat('a', 16) . '_';
foreach ([
    'SELECT * FROM tasks WHERE user_id = :user_id' => 'SELECT * FROM `' . $prefix . 'tasks` WHERE user_id = :user_id',
    'INSERT INTO users (username) VALUES (:username)' => 'INSERT INTO `' . $prefix . 'users` (username) VALUES (:username)',
    'DELETE FROM task_reminders WHERE task_id = :id' => 'DELETE FROM `' . $prefix . 'task_reminders` WHERE task_id = :id',
    'UPDATE tasks t JOIN task_repeat_rules r ON r.id = t.repeat_rule_id SET t.has_time = 1' => 'UPDATE `' . $prefix . 'tasks` t JOIN `' . $prefix . 'task_repeat_rules` r ON r.id = t.repeat_rule_id SET t.has_time = 1',
] as $statement => $expected) {
    if (IsolatedRepeatTestConnection::isolateStatement($statement, $prefix) !== $expected) {
        throw new RuntimeException('Repository table references must remain inside the fixture namespace.');
    }
}
foreach ([
    'UPDATE users_info SET firstname = :name',
    'DELETE FROM mytodo.tasks',
    'SELECT * FROM tasks, users_info',
    'DROP TABLE users',
    'DELETE FROM tasks; DELETE FROM users',
] as $statement) {
    try {
        IsolatedRepeatTestConnection::isolateStatement($statement, $prefix);
    } catch (InvalidArgumentException) {
        continue;
    }
    throw new RuntimeException('An unsupported SQL shape must be refused before database access.');
}
echo "PASS isolated Repeat SQL routing and unsupported-statement guards (no database connection)\n";
