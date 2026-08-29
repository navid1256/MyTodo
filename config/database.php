<?php

declare(strict_types=1);

use App\Config\EnvironmentLoader;

return [
    'driver' => EnvironmentLoader::get('DB_DRIVER'),
    'host' => EnvironmentLoader::get('DB_HOST'),
    'port' => (int) EnvironmentLoader::get('DB_PORT'),
    'database' => EnvironmentLoader::get('DB_DATABASE'),
    'username' => EnvironmentLoader::get('DB_USERNAME'),
    'password' => EnvironmentLoader::get('DB_PASSWORD'),
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];
