<?php

declare(strict_types=1);

use Minishlink\WebPush\VAPID;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Selects the bundled Windows OpenSSL configuration without changing php.ini. */
function configureVapidOpenSsl(): void
{
    if (PHP_OS_FAMILY !== 'Windows' || getenv('OPENSSL_CONF')) {
        return;
    }

    $phpDirectory = dirname(PHP_BINARY);
    foreach ([$phpDirectory . '/extras/ssl/openssl.cnf', dirname($phpDirectory) . '/apache/conf/openssl.cnf'] as $candidate) {
        if (is_readable($candidate)) {
            putenv('OPENSSL_CONF=' . $candidate);
            return;
        }
    }
}

$arguments = array_slice($argv, 1);
$usage = "Usage: php process/generate-vapid-keys.php [--production]\n";
if ($arguments === ['--help']) {
    fwrite(STDOUT, $usage . "Default: storage/private/vapid.json\nProduction: storage/private/vapid.production.json\nExisting files are never overwritten; private keys are never printed.\n");
    exit(0);
}
if ($arguments !== [] && $arguments !== ['--production']) {
    fwrite(STDERR, $usage);
    exit(2);
}

$rootPath = dirname(__DIR__);
$directory = $rootPath . '/storage/private';
$filename = $arguments === ['--production'] ? 'vapid.production.json' : 'vapid.json';
$path = $directory . '/' . $filename;
if (file_exists($path) || is_link($path)) {
    fwrite(STDERR, "VAPID keys already exist; they were not changed.\n");
    exit(1);
}
if (!is_file($rootPath . '/vendor/autoload.php')) {
    fwrite(STDERR, "Dependencies are missing. Run composer install from the project root.\n");
    exit(1);
}

// CLI errors must not expose a stack trace or contaminate the success message.
ini_set('display_errors', '0');
$createdFile = false;
$previousUmask = umask(0077);
try {
    require_once $rootPath . '/vendor/autoload.php';
    if (!extension_loaded('openssl')) {
        fwrite(STDERR, "Enable the OpenSSL extension in the CLI php.ini, then retry.\n");
        exit(1);
    }
    configureVapidOpenSsl();
    $keys = VAPID::createVapidKeys();
    $json = json_encode($keys, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new \App\Exceptions\WebPushConfigurationException('Unable to create the private directory.');
    }
    $stream = @fopen($path, 'x');
    if ($stream === false) {
        throw new \App\Exceptions\WebPushConfigurationException('Unable to create the VAPID file.');
    }
    $createdFile = true;
    try {
        if (!chmod($path, 0600)) {
            throw new \App\Exceptions\WebPushConfigurationException('Unable to restrict key file permissions.');
        }
        if (fwrite($stream, $json) !== strlen($json)) {
            throw new \App\Exceptions\WebPushConfigurationException('Unable to write VAPID keys completely.');
        }
    } finally {
        fclose($stream);
    }
    fwrite(STDOUT, "VAPID keys created in storage/private/{$filename}. No private key was printed.\n");
} catch (Throwable) {
    // Remove only an incomplete file created exclusively by this invocation.
    if ($createdFile) {
        @unlink($path);
    }
    fwrite(STDERR, "VAPID generation failed. Check OPENSSL_CONF and write permissions for storage/private.\n");
    exit(1);
} finally {
    umask($previousUmask);
}
