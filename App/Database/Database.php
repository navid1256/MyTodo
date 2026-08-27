<?php

declare(strict_types=1);

namespace App\Database;

use App\Http\Response;
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

            return new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options']
            );
        } catch (PDOException $exception) {
            error_log('Database connection failed: ' . $exception->getMessage());

            Response::text('Database connection failed.', 500)->send();
        }
    }
}
