<?php

declare(strict_types=1);

namespace App\Config;

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
    }
}