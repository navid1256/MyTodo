<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Config\EnvironmentLoader;
use App\Exceptions\AssetManifestException;
use JsonException;

final class ViteHelper
{
    private const DEVELOPMENT_SERVER_ORIGIN = 'https://mytodo.php:5180';

    /** Determines whether assets should be served by the local Vite development server. */
    public static function isDevelopment(): bool
    {
        return EnvironmentLoader::get('APP_ENV') === 'local';
    }

    /** Creates an asset URL on the local HTTPS development server. */
    public static function developmentAssetUrl(string $path): string
    {
        return self::DEVELOPMENT_SERVER_ORIGIN . '/' . ltrim($path, '/');
    }

    /**
     * Resolves the dashboard bundle and its font stylesheet from the Vite build manifest.
     *
     * @return array{script: string, stylesheets: list<string>}
     */
    public static function productionAssets(string $rootPath): array
    {
        $manifestPath = $rootPath . '/public/build/manifest.json';
        if (!is_readable($manifestPath)) {
            throw new AssetManifestException('Vite assets are missing. Run npm run build before deployment.');
        }

        try {
            $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new AssetManifestException('The Vite build manifest is invalid.', 0, $exception);
        }

        $entry = is_array($manifest) ? ($manifest['assets/js/app.js'] ?? null) : null;
        if (!is_array($entry) || !is_string($entry['file'] ?? null) || $entry['file'] === '') {
            throw new AssetManifestException('The dashboard entry is missing from the Vite build manifest.');
        }
        $stylesheets = $entry['css'] ?? [];
        if (!is_array($stylesheets)) {
            throw new AssetManifestException('The Vite stylesheet list is invalid.');
        }
        $urls = [];
        foreach ($stylesheets as $stylesheet) {
            if (!is_string($stylesheet) || $stylesheet === '') {
                throw new AssetManifestException('The Vite stylesheet path is invalid.');
            }
            $urls[] = '/build/' . $stylesheet;
        }

        return ['script' => '/build/' . $entry['file'], 'stylesheets' => $urls];
    }
}
