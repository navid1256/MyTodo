<?php

declare(strict_types=1);

use App\Exceptions\AssetManifestException;
use App\Helpers\ViteHelper;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

/** Fails the asset contract test when the expected outcome is not observed. */
function checkViteAssets(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$fixtureRoot = sys_get_temp_dir() . '/mytodo-vite-' . bin2hex(random_bytes(8));
$buildDirectory = $fixtureRoot . '/public/build';
mkdir($buildDirectory, 0700, true);
$manifestPath = $buildDirectory . '/manifest.json';

try {
    foreach ([
        null,
        '{}',
        '{invalid',
        '{"assets/js/app.js":{"file":42}}',
        '{"assets/js/app.js":{"file":"app.js","css":"invalid"}}',
        '{"assets/js/app.js":{"file":"app.js","css":[42]}}',
    ] as $invalidManifest) {
        if ($invalidManifest !== null) {
            file_put_contents($manifestPath, $invalidManifest);
        }
        try {
            ViteHelper::productionAssets($fixtureRoot);
        } catch (AssetManifestException) {
            continue;
        }
        throw new RuntimeException('Missing or invalid production assets must fail clearly.');
    }

    file_put_contents($manifestPath, json_encode([
        'assets/js/app.js' => ['file' => 'assets/app-123.js', 'css' => ['assets/app-456.css']],
    ], JSON_THROW_ON_ERROR));
    checkViteAssets(ViteHelper::productionAssets($fixtureRoot) === [
        'script' => '/build/assets/app-123.js',
        'stylesheets' => ['/build/assets/app-456.css'],
    ], 'Production HTML must reference the built JavaScript and font stylesheet.');

    file_put_contents($manifestPath, json_encode([
        'assets/js/app.js' => ['file' => 'assets/app-123.js'],
    ], JSON_THROW_ON_ERROR));
    checkViteAssets(ViteHelper::productionAssets($fixtureRoot)['stylesheets'] === [], 'Entries without CSS must remain valid.');
    echo "PASS Vite production manifest assets and failure boundaries\n";
} finally {
    if (is_file($manifestPath)) {
        unlink($manifestPath);
    }
    rmdir($buildDirectory);
    rmdir($fixtureRoot . '/public');
    rmdir($fixtureRoot);
}
