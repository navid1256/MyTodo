<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// Render a real dashboard without sessions, a database, or a running Vite server.
$environment = $argv[1] ?? 'production';
$_SERVER['APP_ENV'] = $environment;
$_SESSION = [];
$activeView = 'account-settings';
$effectiveLanguage = $argv[2] ?? 'english';
ob_start();
require dirname(__DIR__, 2) . '/views/layouts/dashboard.php';
$html = (string) ob_get_clean();

if ($environment === 'local') {
    if (!str_contains($html, 'https://mytodo.php:5180/@vite/client') || str_contains($html, '/build/assets/app-')) {
        throw new RuntimeException('Local development must retain Vite HMR instead of production assets.');
    }
} elseif (
    !preg_match('#<script type="module" src="/build/assets/app-[^"/]+\.js">#', $html)
    || !preg_match('#<link rel="stylesheet" href="/build/assets/app-[^"/]+\.css">#', $html)
    || str_contains($html, ':5180')
) {
    throw new RuntimeException('Production must include the built dashboard script and font stylesheet without HMR.');
}

$expectedDirection = $effectiveLanguage === 'persian' ? 'rtl' : 'ltr';
if (!str_contains($html, 'dir="' . $expectedDirection . '"')) {
    throw new RuntimeException('Production assets must preserve the selected document direction.');
}
echo "PASS {$environment} dashboard assets ({$expectedDirection}) without database I/O\n";
