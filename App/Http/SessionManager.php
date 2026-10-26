<?php

declare(strict_types=1);

namespace App\Http;

use App\Exceptions\SessionInitializationException;

final class SessionManager
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $sessionPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mytodo-sessions';

        if (!is_dir($sessionPath) && !mkdir($sessionPath, 0775, true) && !is_dir($sessionPath)) {
            throw new SessionInitializationException('Unable to create the session storage directory.');
        }

        $httpsValue = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        $isHttpsRequest = in_array($httpsValue, ['on', '1', 'true'], true)
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

        $requestHost = strtolower((string) parse_url(
            'https://' . ($_SERVER['HTTP_HOST'] ?? ''),
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
}
