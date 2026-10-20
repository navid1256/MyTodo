<?php

declare(strict_types=1);

/** Fails a generator test without printing key-file contents. */
function checkVapidGenerator(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** Executes only the isolated copy, with a fake key generator and no network access. */
function runVapidGenerator(string $root, array $arguments): array
{
    $process = proc_open(
        [PHP_BINARY, $root . '/process/generate-vapid-keys.php', ...$arguments],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $root
    );
    checkVapidGenerator(is_resource($process), 'Unable to start isolated generator.');
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), (string) $stdout, (string) $stderr];
}

/** Removes only the temporary fixture tree owned by this test. */
function removeVapidFixture(string $path): void
{
    foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            removeVapidFixture($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($path);
}

$root = sys_get_temp_dir() . '/mytodo-vapid-cli-' . bin2hex(random_bytes(8));
mkdir($root . '/process', 0700, true);
mkdir($root . '/vendor', 0700, true);
mkdir($root . '/storage/private', 0700, true);
copy(dirname(__DIR__, 2) . '/process/generate-vapid-keys.php', $root . '/process/generate-vapid-keys.php');
copy(__DIR__ . '/fixtures/vapid-generator-autoload.php', $root . '/vendor/autoload.php');
$previousConfig = getenv('OPENSSL_CONF');
$previousFailure = getenv('VAPID_TEST_FAILURE');

try {
    putenv('OPENSSL_CONF');
    [$status, $stdout] = runVapidGenerator($root, ['--help']);
    checkVapidGenerator($status === 0 && str_contains($stdout, '--production'), 'Help must explain separate production keys.');
    checkVapidGenerator(!file_exists($root . '/storage/private/vapid.json'), 'Help must not create keys.');

    [$status, , $stderr] = runVapidGenerator($root, ['--unknown']);
    checkVapidGenerator($status === 2 && str_contains($stderr, 'Usage:'), 'Unknown arguments must fail before generating keys.');

    file_put_contents($root . '/storage/private/vapid.json', 'existing-development-fixture');
    [$status, $stdout, $stderr] = runVapidGenerator($root, ['--production']);
    checkVapidGenerator($status === 0, 'Production mode must succeed using the fake generator.');
    $productionPath = $root . '/storage/private/vapid.production.json';
    checkVapidGenerator(is_file($productionPath), 'Production keys must use a separate file.');
    $keys = json_decode((string) file_get_contents($productionPath), true, 8, JSON_THROW_ON_ERROR);
    checkVapidGenerator($keys === ['publicKey' => 'fixture-public-key', 'privateKey' => 'fixture-private-key'], 'The saved key format must remain compatible.');
    checkVapidGenerator(file_get_contents($root . '/storage/private/vapid.json') === 'existing-development-fixture', 'Development keys must remain unchanged.');
    checkVapidGenerator(!str_contains($stdout . $stderr, 'fixture-private-key'), 'Private keys must never appear in command output.');

    $productionHash = hash_file('sha256', $productionPath);
    [$status] = runVapidGenerator($root, ['--production']);
    checkVapidGenerator($status === 1 && hash_file('sha256', $productionPath) === $productionHash, 'Existing production keys must never be overwritten.');
    [$status] = runVapidGenerator($root, []);
    checkVapidGenerator($status === 1, 'Default mode must also refuse existing keys.');

    unlink($root . '/storage/private/vapid.json');
    putenv('VAPID_TEST_FAILURE=1');
    [$status, $stdout, $stderr] = runVapidGenerator($root, []);
    checkVapidGenerator($status === 1 && str_contains($stderr, 'OPENSSL_CONF'), 'Generation failure must explain the configuration to check.');
    checkVapidGenerator(!str_contains($stdout . $stderr, 'fixture-private-key'), 'Exception messages must not disclose key material.');
    checkVapidGenerator(!file_exists($root . '/storage/private/vapid.json'), 'Generation failure must not create a key file.');
    putenv('VAPID_TEST_FAILURE');

    [$status] = runVapidGenerator($root, []);
    checkVapidGenerator($status === 0 && is_file($root . '/storage/private/vapid.json'), 'Default mode must retain the development filename.');

    rename($root . '/vendor/autoload.php', $root . '/vendor/autoload.fixture.php');
    [$status, $stdout, $stderr] = runVapidGenerator($root, ['--help']);
    checkVapidGenerator($status === 0, 'Help must work without installed dependencies.');
    unlink($root . '/storage/private/vapid.json');
    [$status, $stdout, $stderr] = runVapidGenerator($root, []);
    checkVapidGenerator($status === 1 && str_contains($stderr, 'composer install'), 'Missing dependencies must produce actionable stderr.');
    checkVapidGenerator(!str_contains($stdout . $stderr, 'Fatal error'), 'Handled failures must not expose a PHP fatal stack trace.');
    checkVapidGenerator(!file_exists($root . '/storage/private/vapid.json'), 'Failure must not leave a key file.');
} finally {
    putenv($previousConfig === false ? 'OPENSSL_CONF' : 'OPENSSL_CONF=' . $previousConfig);
    putenv($previousFailure === false ? 'VAPID_TEST_FAILURE' : 'VAPID_TEST_FAILURE=' . $previousFailure);
    removeVapidFixture($root);
}

echo "PASS VAPID generator CLI (fake keys, isolated files, no database or network)\n";
