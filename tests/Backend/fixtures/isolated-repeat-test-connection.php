<?php

declare(strict_types=1);

use App\Config\EnvironmentLoader;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

final class IsolatedRepeatTestConnection extends PDO
{
    public const TABLES = ['users', 'task_repeat_rules', 'tasks', 'task_repeat_reminder_templates', 'task_reminders'];

    public readonly string $prefix;
    private array $createdTables = [];

    /** Connects only to the explicitly authorized database, with a new fixture namespace. */
    public function __construct()
    {
        EnvironmentLoader::load(dirname(__DIR__, 3));
        $configuration = require dirname(__DIR__, 3) . '/config/database.php';
        if ($configuration['driver'] !== 'mysql' || $configuration['database'] !== 'mytodo') {
            throw new RuntimeException('The isolated Repeat test is authorized only for configured MySQL database mytodo.');
        }
        parent::__construct(
            sprintf('mysql:host=%s;port=%d;dbname=mytodo;charset=utf8mb4', $configuration['host'], $configuration['port']),
            $configuration['username'], $configuration['password'], $configuration['options']
        );
        if (parent::query('SELECT DATABASE()')->fetchColumn() !== 'mytodo') {
            throw new RuntimeException('Unexpected database selected for isolated Repeat tests.');
        }
        parent::exec("SET time_zone = '+00:00'");
        $this->prefix = 'mytodo_repeat_' . bin2hex(random_bytes(8)) . '_';
    }

    /** Routes the supported repository SQL shapes to fixture tables and rejects unsupported targets. */
    public static function isolateStatement(string $statement, string $prefix): string
    {
        if (preg_match('/\Amytodo_repeat_[a-f0-9]{16}_\z/', $prefix) !== 1
            || str_contains($statement, ';')
            || preg_match('/\A\s*(SELECT|INSERT|UPDATE|DELETE)\b/i', $statement, $command) !== 1) {
            throw new InvalidArgumentException('Unsupported isolated Repeat statement or prefix.');
        }
        // Comma-separated table lists are not used by these repositories and must not escape routing.
        preg_match_all('/\b(?:FROM|UPDATE)\s+([^()]*?)(?=\bWHERE\b|\bSET\b|\bGROUP\b|\bORDER\b|\bLIMIT\b|\bFOR\b|$)/is', $statement, $clauses);
        foreach ($clauses[1] as $clause) {
            if (str_contains($clause, ',')) {
                throw new InvalidArgumentException('Unsupported isolated Repeat table list.');
            }
        }
        // Only table-position identifiers are rewritten; bound values and column names remain unchanged.
        $isolated = preg_replace_callback(
            '/\b(FROM|JOIN|UPDATE|INTO)\s+(`?[a-z_][a-z0-9_]*`?(?:\.`?[a-z_][a-z0-9_]*`?)?)/i',
            static function (array $match) use ($prefix, $command): string {
                $table = strtolower(str_replace('`', '', $match[2]));
                if ($table === 'information_schema.tables' && strtoupper($command[1]) === 'SELECT') {
                    return $match[0];
                }
                if (!in_array($table, self::TABLES, true)) {
                    throw new InvalidArgumentException('Statement targets a table outside the Repeat fixture.');
                }
                return $match[1] . ' `' . $prefix . $table . '`';
            },
            $statement
        );
        if (stripos($isolated, 'information_schema.tables') !== false) {
            foreach (self::TABLES as $table) {
                $isolated = str_replace("'" . $table . "'", "'" . $prefix . $table . "'", $isolated);
            }
        }
        return $isolated;
    }

    /** Copies schema, indexes and internal foreign keys without copying any application rows or triggers. */
    public function createFixtureTables(): void
    {
        $names = [];
        foreach (self::TABLES as $table) {
            $names['`' . $table . '`'] = '`' . $this->prefix . $table . '`';
        }
        $constraintNumber = 0;
        foreach (self::TABLES as $table) {
            $schema = parent::query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM)[1];
            if (preg_match('/\bENGINE=InnoDB\b/i', $schema) !== 1) {
                throw new RuntimeException('Repeat fixture sources must use InnoDB.');
            }
            preg_match_all('/\bREFERENCES\s+`([^`]+)`(?:\.`([^`]+)`)?/i', $schema, $references, PREG_SET_ORDER);
            foreach ($references as $reference) {
                $target = $reference[2] ?? $reference[1];
                if ((isset($reference[2]) && $reference[1] !== 'mytodo') || !in_array($target, self::TABLES, true)) {
                    throw new RuntimeException('A copied foreign key would leave the isolated Repeat tables.');
                }
            }
            // Constraint names must also be unique within the shared database.
            $schema = preg_replace_callback('/\bCONSTRAINT\s+`[^`]+`/i',
                function (array $match) use (&$constraintNumber): string {
                    return 'CONSTRAINT `' . $this->prefix . 'fk_' . ++$constraintNumber . '`';
                }, $schema);
            parent::exec(strtr($schema, $names));
            $this->createdTables[] = $this->prefix . $table;
        }
    }

    /** Allows the shared lifecycle suite only after every isolated table has been created. */
    public function isReady(): bool
    {
        return count($this->createdTables) === count(self::TABLES);
    }

    /** Captures original row checksums, counts and schema hashes without exposing row contents. */
    public function snapshotApplicationTables(): array
    {
        $snapshot = [];
        foreach (self::TABLES as $table) {
            $schema = parent::query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM)[1];
            $checksum = parent::query('CHECKSUM TABLE `' . $table . '` EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
            if ($checksum === null) {
                throw new RuntimeException('An application table checksum could not be verified.');
            }
            $snapshot[$table] = [
                'schema' => hash('sha256', $schema),
                'checksum' => (string) $checksum,
                'rows' => (int) parent::query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn(),
            ];
        }
        return $snapshot;
    }

    /** Drops only the exact tables successfully created by this instance, in reverse dependency order. */
    public function dropFixtureTables(): void
    {
        while ($this->createdTables !== []) {
            $table = $this->createdTables[array_key_last($this->createdTables)];
            $source = substr($table, strlen($this->prefix));
            if (!str_starts_with($table, $this->prefix) || !in_array($source, self::TABLES, true)) {
                throw new RuntimeException('Refusing to remove a table not owned by this fixture.');
            }
            parent::exec('DROP TABLE `' . $table . '`');
            array_pop($this->createdTables);
        }
    }

    /** Keeps prepared repository queries inside this run's fixture tables. */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(self::isolateStatement($query, $this->prefix), $options);
    }

    /** Keeps direct fixture writes inside the same isolated namespace. */
    public function exec(string $statement): int|false
    {
        return parent::exec(self::isolateStatement($statement, $this->prefix));
    }

    /** Routes direct reads, including schema verification, to the fixture namespace. */
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $query = self::isolateStatement($query, $this->prefix);
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}
