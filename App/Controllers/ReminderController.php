<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ReminderValidationException;
use App\Exceptions\TaskValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\CsrfMiddleware;
use App\Services\AuthService;
use App\Services\ReminderService;
use App\Services\UserSettingsService;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;

final class ReminderController
{
    private const AUTH_REQUIRED_MESSAGE = 'Authentication required.';
    private const SESSION_EXPIRED_MESSAGE = 'Your session has expired. Please refresh the page and try again.';

    public function __construct(
        private readonly ReminderService $reminderService,
        private readonly AuthService $authService,
        private readonly UserSettingsService $settingsService
    ) {}

    public function preview(Request $request): Response
    {
        if ($this->authService->getCurrentUserId() <= 0) {
            return Response::json(['success' => false, 'message' => self::AUTH_REQUIRED_MESSAGE], 401);
        }

        if (!CsrfMiddleware::isValid($request->post('csrf_token'))) {
            return Response::json(['success' => false, 'message' => self::SESSION_EXPIRED_MESSAGE], 403);
        }

        return $this->processPreview($request);
    }

    private function processPreview(Request $request): Response
    {
        try {
            $dueAtValue = $request->postString('due_at');
            $hasTime = $request->postString('has_time') === '1';
            $remindersJson = $request->postString('reminders', '[]');

            $timezone = $this->settingsService->getTimezoneForUser(
                $this->authService->getCurrentUserId(),
                $request->cookieString('mytodo_timezone')
            );
            $dueAt = $this->parseDueAt($dueAtValue, $timezone);
            $reminders = $this->parseRemindersJson($remindersJson);

            $prepared = $this->reminderService->prepareTaskReminders($reminders, $dueAt, $hasTime);
            $previewItems = array_map(static function (array $item): array {
                /** @var DateTimeImmutable $remindAt */
                $remindAt = $item['remind_at'];

                return [
                    'offset_value' => $item['offset_value'],
                    'offset_unit' => $item['offset_unit'],
                    'remind_at' => $remindAt->format('Y-m-d H:i:s'),
                    'formatted' => $remindAt->format('M j, Y \a\t h:i A'),
                ];
            }, $prepared);

            return Response::json(['success' => true, 'reminders' => $previewItems]);
        } catch (TaskValidationException | ReminderValidationException $exception) {
            return Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    private function parseDueAt(string $dueAtValue, DateTimeZone $timezone): ?DateTimeImmutable
    {
        if ($dueAtValue === '') {
            return null;
        }

        $dueAt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $dueAtValue, $timezone);
        $dateErrors = DateTimeImmutable::getLastErrors();

        if (
            !$dueAt
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $dueAt->format('Y-m-d\TH:i') !== $dueAtValue
        ) {
            throw new TaskValidationException('Please select a valid task date and time.');
        }

        return $dueAt;
    }

    /**
     * @return array<int, mixed>
     */
    private function parseRemindersJson(string $remindersJson): array
    {
        try {
            $reminders = json_decode($remindersJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ReminderValidationException('The reminder settings are invalid.');
        }

        if (!is_array($reminders) || !array_is_list($reminders)) {
            throw new ReminderValidationException('The reminder settings are invalid.');
        }

        return $reminders;
    }
}
