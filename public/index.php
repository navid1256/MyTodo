<?php

use App\Exceptions\SessionInitializationException;

$rootPath = dirname(__DIR__);

$rootPathWithSeparator = $rootPath . DIRECTORY_SEPARATOR;

require_once $rootPathWithSeparator . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$appConfig = require_once $rootPathWithSeparator . 'config' . DIRECTORY_SEPARATOR . 'app.php';
$databaseConfig = require_once $rootPathWithSeparator . 'config' . DIRECTORY_SEPARATOR . 'database.php';

date_default_timezone_set($appConfig['timezone']);

try {
    if (session_status() === PHP_SESSION_NONE) {
        $sessionPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mytodo-sessions';

        if (!is_dir($sessionPath) && !mkdir($sessionPath, 0775, true) && !is_dir($sessionPath)) {
            throw new SessionInitializationException('Unable to create the session storage directory.');
        }

        $httpsValue = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        $isHttpsRequest = in_array($httpsValue, ['on', '1', 'true'], true)
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

        $requestHost = strtolower((string) parse_url(
            'http://' . ($_SERVER['HTTP_HOST'] ?? ''),
            PHP_URL_HOST
        ));
        $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $loopbackAddresses = ['127.0.0.1', '::1'];
        $isLocalRequest = PHP_SAPI === 'cli'
            || (
                in_array($requestHost, ['localhost', ...$loopbackAddresses], true)
                && in_array($remoteAddress, $loopbackAddresses, true)
            );

        if (!$isHttpsRequest && !$isLocalRequest) {
            throw new SessionInitializationException(
                'HTTPS is required before a session cookie can be created.'
            );
        }

        session_save_path($sessionPath);
        session_set_cookie_params([
            'httponly' => true,
            // An insecure cookie is permitted only for local development on a loopback address.
            'secure' => $isHttpsRequest,
            'samesite' => 'Lax',
            'path' => '/',
        ]);

        if (!session_start()) {
            throw new SessionInitializationException('Unable to start the session.');
        }
    }
} catch (SessionInitializationException $exception) {
    error_log('Session initialization failed: ' . $exception->getMessage());

    (new App\Http\Response('Session initialization failed.', 500))->send();
}

try {
    $dsn = sprintf(
        '%s:host=%s;port=%d;dbname=%s;charset=%s',
        $databaseConfig['driver'],
        $databaseConfig['host'],
        $databaseConfig['port'],
        $databaseConfig['database'],
        $databaseConfig['charset']
    );

    $pdo = new PDO(
        $dsn,
        $databaseConfig['username'],
        $databaseConfig['password'],
        $databaseConfig['options']
    );
} catch (PDOException $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());

    (new App\Http\Response('Database connection failed.', 500))->send();
}
