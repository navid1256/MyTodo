<?php

declare(strict_types=1);

use App\Exceptions\PushSubscriptionValidationException;
use App\Services\PushSubscriptionValidator;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

function checkPush(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$validator = new PushSubscriptionValidator();
$publicKey = hex2bin('046b17d1f2e12c4247f8bce6e563a440f277037d812deb33a0f4a13945d898c2964fe342e2fe1a7f9b8ee7eb4a7c0f9e162bce33576b315ececbb6406837bf51f5');
$input = [
    'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-device',
    'keys' => ['p256dh' => rtrim(strtr(base64_encode($publicKey), '+/', '-_'), '='), 'auth' => rtrim(strtr(base64_encode(str_repeat('a', 16)), '+/', '-_'), '=')],
];
$validated = $validator->validate($input);
checkPush($validated['endpoint'] === $input['endpoint'], 'Valid browser subscription must round-trip.');
foreach ([
    'http://fcm.googleapis.com/a',
    'https://localhost/a',
    'https://127.0.0.1/a',
    'https://fcm.googleapis.com.attacker.test/a',
    'https://attacker.test/fcm.googleapis.com/a',
    'https://user:password@fcm.googleapis.com/a',
    'https://fcm.googleapis.com:8443/a',
    'https://fcm.googleapis.com/a#fragment',
] as $endpoint) {
    try {
        $validator->validate(array_replace($input, ['endpoint' => $endpoint]));
        throw new RuntimeException('Unsafe endpoint was accepted.');
    } catch (PushSubscriptionValidationException $exception) {
        checkPush($exception->translationKey() === 'push.invalid_subscription', 'Invalid input must have a translated error.');
    }
}
foreach ([null, [], ['keys' => []], array_replace($input, ['keys' => ['p256dh' => 'invalid', 'auth' => 'invalid']])] as $invalid) {
    try {
        $validator->validate($invalid);
        throw new RuntimeException('Invalid subscription was accepted.');
    } catch (PushSubscriptionValidationException) {
        // Expected: malformed input is rejected before reaching the repository.
    }
}
foreach (['https://updates.push.services.mozilla.com/wpush/v2/a', 'https://web.push.apple.com/a', 'https://wns2-bl2p.notify.windows.com/a'] as $endpoint) {
    checkPush($validator->validate(array_replace($input, ['endpoint' => $endpoint]))['endpoint'] === $endpoint, 'Supported push providers must be accepted.');
}
echo "PASS push subscription validation and SSRF boundaries\n";
