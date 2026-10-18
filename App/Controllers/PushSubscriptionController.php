<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\PushSubscriptionValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Localization\Translator;
use App\Middleware\CsrfMiddleware;
use App\Services\PushSubscriptionService;
use App\Services\UserSettingsService;

final class PushSubscriptionController
{
    public function __construct(
        private readonly PushSubscriptionService $service,
        private readonly UserSettingsService $settingsService
    ) {}

    public function status(Request $request): Response
    {
        return $this->handle($request, function (int $userId, Translator $translator) use ($request): array {
            return ['success' => true, ...$this->service->status($userId, $request->postString('endpoint'))];
        });
    }

    public function subscribe(Request $request): Response
    {
        return $this->handle($request, function (int $userId, Translator $translator) use ($request): array {
            $this->service->subscribe($userId, $request->postString('subscription'), $request->header('User-Agent') ?? '');

            return ['success' => true, 'message' => $translator->translate('push.enabled')];
        });
    }

    public function unsubscribe(Request $request): Response
    {
        return $this->handle($request, function (int $userId, Translator $translator) use ($request): array {
            $this->service->unsubscribe($userId, $request->postString('endpoint'));

            return ['success' => true, 'message' => $translator->translate('push.disabled')];
        });
    }

    private function handle(Request $request, \Closure $action): Response
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        if ($userId < 1) {
            return Response::json(['success' => false, 'message' => 'Authentication required.'], 401);
        }
        $settings = $this->settingsService->getForUser($userId, $request->cookieString('mytodo_timezone'), $request->header('Accept-Language'));
        $translator = new Translator($settings['effective_language'], dirname(__DIR__, 2) . '/resources/lang');
        if (!CsrfMiddleware::isValid($request->post('csrf_token'))) {
            return Response::json(['success' => false, 'message' => $translator->translate('settings.validation.session_expired')], 403);
        }
        try {
            return Response::json($action($userId, $translator), 200, ['Cache-Control' => 'no-store']);
        } catch (PushSubscriptionValidationException $exception) {
            return Response::json([
                'success' => false,
                'code' => $exception->translationKey(),
                'message' => $translator->translate($exception->translationKey()),
            ], $exception->statusCode());
        }
    }
}
