<?php

declare(strict_types=1);

namespace App\Database;

use App\Exceptions\DatabaseConnectionException;
use PDO;
use PDOException;

final class Database
{
    /**
     * @param array{
     *     driver: string,
     *     host: string,
     *     port: int,
     *     database: string,
     *     username: string,
     *     password: string,
     *     charset: string,
     *     options: array<int, mixed>
     * } $config
     */
    public static function connect(array $config): PDO
    {
        try {
            $dsn = sprintf(
                '%s:host=%s;port=%d;dbname=%s;charset=%s',
                $config['driver'],
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );

            $pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options']
            );
            $pdo->exec("SET time_zone = '+00:00'");

            return $pdo;
        } catch (PDOException $exception) {
            throw new DatabaseConnectionException(
                'Unable to connect to the database.',
                0,
                $exception
            );
        }
    }
}
