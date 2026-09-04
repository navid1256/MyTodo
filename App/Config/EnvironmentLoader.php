<?php

declare(strict_types=1);

namespace App\Config;

use App\Exceptions\EnvironmentVariableException;
use Dotenv\Dotenv;

final class EnvironmentLoader
{
    public static function load(string $rootPath): void
    {
        $dotenv = Dotenv::createImmutable($rootPath);
        $dotenv->safeLoad();

        $dotenv->required([
            'APP_ENV',
            'APP_DEBUG',
            'APP_TIMEZONE',
            'DB_DRIVER',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
        ]);

        $dotenv->required('DB_PORT')->isInteger();
        $dotenv->required('APP_DEBUG')->isBoolean();
        $dotenv->required('APP_ENV')->allowedValues(['local', 'testing', 'production']);
    }

    public static function get(string $key): string
    {
        $processValue = getenv($key);
        if ($processValue !== false) {
            return $processValue;
        }

        if (array_key_exists($key, $_SERVER)) {
            return (string) $_SERVER[$key];
        }

        if (array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }

        throw new EnvironmentVariableException("Environment variable {$key} is not configured.");
    }
}
