<?php

declare(strict_types=1);

use App\Config\EnvironmentLoader;

return [
    'workers' => EnvironmentLoader::get('REMINDER_WORKERS', '4'),
    'runtime_seconds' => EnvironmentLoader::get('REMINDER_RUNTIME_SECONDS', '45'),
];
