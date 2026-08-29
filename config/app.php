<?php

declare(strict_types=1);

return [
    'title' => 'MyTodo',
    'environment' => $_ENV['APP_ENV'],
    'debug' => filter_var(
        $_ENV['APP_DEBUG'],
        FILTER_VALIDATE_BOOLEAN
    ),
    'base_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR,
    'base_url' => '/',
    'timezone' => $_ENV['APP_TIMEZONE'],
];
