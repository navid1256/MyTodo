<?php

declare(strict_types=1);

// Run only inside the built Linux container, with tests mounted read-only and networking disabled.
/** Verifies a container invariant without inspecting credentials or connecting to a database. */
function checkDockerRuntime(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

checkDockerRuntime(PHP_OS_FAMILY === 'Linux' && posix_geteuid() === 33, 'The container must run as the non-root www-data user.');
// Reject conflicting MPMs before checking HTTP endpoints or application assets.
exec('apache2ctl -M 2>&1', $apacheModuleOutput, $apacheModuleExitCode);
$apacheModules = implode("\n", $apacheModuleOutput);
checkDockerRuntime($apacheModuleExitCode === 0, 'Apache module configuration must be valid.');
checkDockerRuntime(
    preg_match_all('/^\s*mpm_\w+_module\b/m', $apacheModules) === 1
    && str_contains($apacheModules, 'mpm_prefork_module'),
    'Apache must load only the prefork MPM required by mod_php.'
);
foreach (['curl', 'fileinfo', 'gd', 'gmp', 'mbstring', 'openssl', 'pdo_mysql', 'Zend OPcache'] as $extension) {
    checkDockerRuntime(extension_loaded($extension), "Missing runtime extension: {$extension}");
}
checkDockerRuntime(function_exists('proc_open'), 'The existing worker launcher needs proc_open.');
checkDockerRuntime(
    !filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN)
    && !filter_var(ini_get('expose_php'), FILTER_VALIDATE_BOOLEAN),
    'Production must not expose PHP errors or its version.'
);

$root = dirname(__DIR__, 2);
foreach (['.env', '.env.local', '.git', 'node_modules', 'storage/private/vapid.json'] as $privatePath) {
    checkDockerRuntime(!file_exists($root . '/' . $privatePath), "Local/private files must not be shipped: {$privatePath}");
}
checkDockerRuntime(!is_file('/usr/local/bin/composer') && !is_file('/usr/local/bin/node'), 'Build tools must remain outside the runtime image.');
checkDockerRuntime(!is_writable($root . '/App') && !is_writable($root . '/vendor'), 'Application code and dependencies must not be writable.');
checkDockerRuntime(is_writable($root . '/storage/logs') && is_writable($root . '/public/storage/avatars'), 'Runtime data directories must be writable.');
require $root . '/vendor/autoload.php';
$assets = App\Helpers\ViteHelper::productionAssets($root);
foreach ([$assets['script'], ...$assets['stylesheets']] as $asset) {
    checkDockerRuntime(is_file($root . '/public' . $asset), 'Manifest assets must exist in the final image.');
}

$port = getenv('PORT') ?: '8080';
$headers = get_headers('http://127.0.0.1:' . $port . '/healthz');
checkDockerRuntime(is_array($headers) && str_contains($headers[0], '200'), 'The PHP liveness endpoint must return HTTP 200 without a database.');
checkDockerRuntime(file_get_contents('http://127.0.0.1:' . $port . '/healthz') === "ok\n", 'The health endpoint must execute PHP rather than expose source.');
foreach ([$assets['script'], ...$assets['stylesheets']] as $asset) {
    $assetHeaders = get_headers('http://127.0.0.1:' . $port . $asset);
    checkDockerRuntime(is_array($assetHeaders) && str_contains($assetHeaders[0], '200'), 'Built JS and font CSS must be served by Apache.');
}
$hiddenHeaders = get_headers('http://127.0.0.1:' . $port . '/.env');
checkDockerRuntime(is_array($hiddenHeaders) && str_contains($hiddenHeaders[0], '403'), 'Dotfiles must be denied by Apache.');
$uploadProbe = $root . '/public/storage/avatars/docker-smoke-' . bin2hex(random_bytes(8)) . '.php';
try {
    file_put_contents($uploadProbe, '<?php echo "must-not-execute";');
    $uploadHeaders = get_headers('http://127.0.0.1:' . $port . '/storage/avatars/' . basename($uploadProbe));
    checkDockerRuntime(is_array($uploadHeaders) && str_contains($uploadHeaders[0], '403'), 'Uploaded PHP files must never execute.');
} finally {
    if (is_file($uploadProbe)) {
        unlink($uploadProbe);
    }
}

if (($argv[1] ?? '') === '--proxy-session') {
    checkDockerRuntime(
        getenv('DB_HOST') === '127.0.0.1' && getenv('DB_PORT') === '1',
        'The proxy/session probe requires an unreachable fixture database, never a real database.'
    );
    $httpsContext = stream_context_create(['http' => ['header' => "Host: mytodo.example\r\nX-Forwarded-Proto: https\r\n"]]);
    $responseHeaders = get_headers('http://127.0.0.1:' . $port . '/auth', false, $httpsContext);
    // Select cookie headers for flag checks without printing their values.
    $cookies = array_filter($responseHeaders ?: [], static fn(string $header): bool => str_starts_with(strtolower($header), 'set-cookie:'));
    $sessionCookie = strtolower(implode(';', $cookies));
    checkDockerRuntime(
        str_contains($sessionCookie, 'phpsessid=') && str_contains($sessionCookie, 'secure') && str_contains($sessionCookie, 'httponly'),
        'TLS-terminated requests must create secure HttpOnly session cookies before the fixture database refuses connection.'
    );
    echo "PASS custom PORT and secure session cookies behind the TLS proxy (unreachable fixture database)\n";
}
echo "PASS non-root Docker runtime, prefork-only Apache, extensions, immutable code, excluded secrets, assets and PHP liveness\n";
