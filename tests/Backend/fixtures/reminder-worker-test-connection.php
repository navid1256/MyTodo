<?php

declare(strict_types=1);

use App\Config\EnvironmentLoader;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

final class IsolatedReminderTestConnection extends PDO
{
    public const TABLES = ['task_repeat_rules', 'tasks', 'task_reminders', 'push_subscriptions', 'user_settings', 'task_reminder_deliveries'];

    /** Connects to the configured database while restricting repository SQL to test tables. */
    public function __construct(private readonly string $prefix)
    {
        if (preg_match('/\Amytodo_worker_[a-f0-9]{16}_\z/', $prefix) !== 1) {
            throw new RuntimeException('Invalid isolated reminder table prefix.');
        }
        EnvironmentLoader::load(dirname(__DIR__, 3));
        $configuration = require dirname(__DIR__, 3) . '/config/database.php';
        parent::__construct(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $configuration['host'], $configuration['port'], $configuration['database']),
            $configuration['username'], $configuration['password'], $configuration['options']);
        parent::exec("SET time_zone = '+00:00'");
    }

    /** Maps only the six known application tables to this run's isolated fixture tables. */
    private function isolate(string $query): string
    {
        return preg_replace_callback('/\b(' . implode('|', self::TABLES) . ')\b/',
            fn(array $match): string => $this->prefix . $match[1], $query);
    }

    /** Keeps prepared production repository statements inside the isolated test tables. */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare($this->isolate($query), $options);
    }

    /** Applies fixture updates only to tables prefixed for this test run. */
    public function exec(string $statement): int|false
    {
        return parent::exec($this->isolate($statement));
    }
}
