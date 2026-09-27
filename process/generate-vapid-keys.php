<?php

declare(strict_types=1);

use Minishlink\WebPush\VAPID;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$rootPath = dirname(__DIR__);
require_once $rootPath . '/vendor/autoload.php';
$directory = $rootPath . '/storage/private';
$path = $directory . '/vapid.json';
if (file_exists($path)) {
    fwrite(STDERR, "VAPID keys already exist; they were not changed.\n");
    exit(1);
}
try {
    $keys = VAPID::createVapidKeys();
    if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
        throw new \App\Exceptions\WebPushConfigurationException('Unable to create the private directory.');
    }
    $stream = fopen($path, 'x');
    if ($stream === false) {
        throw new \App\Exceptions\WebPushConfigurationException('Unable to create the VAPID file.');
    }
    try {
        $json = json_encode($keys, JSON_THROW_ON_ERROR);
        if (fwrite($stream, $json) !== strlen($json)) {
            throw new \App\Exceptions\WebPushConfigurationException('Unable to write VAPID keys completely.');
        }
    } finally {
        fclose($stream);
    }
    chmod($path, 0600);
    fwrite(STDOUT, "VAPID keys created in storage/private/vapid.json. No private key was printed.\n");
} catch (Throwable) {
    fwrite(STDERR, "VAPID generation failed. Check OpenSSL configuration and private directory permissions.\n");
    exit(1);
}
