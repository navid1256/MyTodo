<?php

declare(strict_types=1);

namespace Minishlink\WebPush;

final class VAPID
{
    /** Returns non-secret test values without generating cryptographic keys. */
    public static function createVapidKeys(): array
    {
        if (getenv('VAPID_TEST_FAILURE') === '1') {
            throw new \RuntimeException('fixture-private-key');
        }
        if (PHP_OS_FAMILY === 'Windows' && !getenv('OPENSSL_CONF')) {
            throw new \RuntimeException('The Windows OpenSSL configuration was not selected.');
        }

        return ['publicKey' => 'fixture-public-key', 'privateKey' => 'fixture-private-key'];
    }
}
