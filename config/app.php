<?php

declare(strict_types=1);

use App\Config\EnvironmentLoader;

return [
    'title' => 'MyTodo',
    'environment' => EnvironmentLoader::get('APP_ENV'),
    'debug' => filter_var(
        EnvironmentLoader::get('APP_DEBUG'),
        FILTER_VALIDATE_BOOLEAN
    ),
    'base_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR,
    'base_url' => '/',
    'timezone' => EnvironmentLoader::get('APP_TIMEZONE'),
];
