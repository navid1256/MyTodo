<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Config\EnvironmentLoader;

final class ViteHelper
{
    private const DEVELOPMENT_SERVER_ORIGIN = 'https://mytodo.php:5173';

    public static function isDevelopment(): bool
    {
        return EnvironmentLoader::get('APP_ENV') === 'local';
    }

    public static function developmentAssetUrl(string $path): string
    {
        return self::DEVELOPMENT_SERVER_ORIGIN . '/' . ltrim($path, '/');
    }
}
