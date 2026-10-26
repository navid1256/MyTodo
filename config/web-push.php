<?php

declare(strict_types=1);

use App\Config\EnvironmentLoader;

return [
    'public_key' => EnvironmentLoader::get('VAPID_PUBLIC_KEY', ''),
    'private_key' => EnvironmentLoader::get('VAPID_PRIVATE_KEY', ''),
    'subject' => EnvironmentLoader::get('VAPID_SUBJECT', ''),
    // Never put this file under public/. The default directory is gitignored.
    'keys_file' => EnvironmentLoader::get('VAPID_KEYS_FILE', dirname(__DIR__) . '/storage/private/vapid.json'),
];
