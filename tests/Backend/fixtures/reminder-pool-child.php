<?php

declare(strict_types=1);

// This fixture never loads application settings or contacts a push provider.
$slot = (int) substr($argv[1] ?? '', strlen('--worker='));
if ($slot === 2) {
    fwrite(STDERR, "Simulated child failure: must not leak into the pool output.\n");
    exit(1);
}
if ($slot === 3) {
    echo json_encode(['status' => 'busy']);
    exit;
}
if ($slot === 4) {
    echo 'invalid child output';
    exit;
}
echo json_encode(['status' => 'completed', 'summary' => [
    'claimed' => 200, 'accepted' => 200, 'retryable' => 0, 'expired' => 0,
    'failed' => 0, 'unknown' => 0, 'skipped' => 0,
]]);
