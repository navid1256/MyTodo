<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\ReminderWorkerException;
use PDO;

final class ReminderWorkerLockRepository
{
    private readonly string $namespace;

    /** Shares advisory locks across processes connected to the same database and queue. */
    public function __construct(private readonly PDO $pdo, string $queue = 'reminders')
    {
        $database = $pdo->query('SELECT DATABASE()')->fetchColumn();
        $this->namespace = 'mytodo:' . substr(hash('sha256', $database . ':' . $queue), 0, 32);
    }

    /** Acquires a launcher or worker slot without waiting behind a previous Cron run. */
    public function acquire(string $slot): bool
    {
        $statement = $this->pdo->prepare('SELECT GET_LOCK(:name, 0)');
        $statement->execute([':name' => $this->namespace . ':' . $slot]);
        $result = $statement->fetchColumn();
        if ($result === null || $result === false) {
            throw new ReminderWorkerException('Unable to acquire reminder worker lock.');
        }

        return (int) $result === 1;
    }

    /** Releases the slot after the current send finishes; connection loss also releases it. */
    public function release(string $slot): void
    {
        $statement = $this->pdo->prepare('SELECT RELEASE_LOCK(:name)');
        $statement->execute([':name' => $this->namespace . ':' . $slot]);
    }
}
