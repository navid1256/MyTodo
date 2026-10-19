<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotificationPayloadValidationException;
use App\Exceptions\PushSubscriptionValidationException;
use App\Localization\Translator;
use App\Notifications\NotificationSender;
use App\Notifications\NotificationSendResult;
use App\Notifications\ReminderDeliverySummary;
use DateTimeImmutable;
use DateTimeZone;
use Hekmatinasser\Verta\Verta;
use Throwable;

final class ReminderNotificationWorker
{
    /** Keeps provider I/O separate from durable reminder reservation and result policy. */
    public function __construct(
        private readonly ReminderDispatchService $dispatch,
        private readonly NotificationSender $sender,
        private readonly UserSettingsService $settings,
        private readonly string $languageDirectory,
    ) {}

    /** Processes one bounded batch and reports only aggregate, non-sensitive outcomes. */
    public function run(int $limit = 100, ?DateTimeImmutable $now = null): array
    {
        return $this->processClaims($this->dispatch->reserveBatch($limit, $now), $now);
    }

    /** Processes at most one explicit reminder/browser pair without draining the shared queue. */
    public function runTarget(int $reminderId, int $subscriptionId, ?DateTimeImmutable $now = null): array
    {
        return $this->processClaims($this->dispatch->reserveTarget($reminderId, $subscriptionId, $now), $now);
    }

    /** Applies the same payload, sendability and result policy to already-reserved deliveries. */
    private function processClaims(array $claims, ?DateTimeImmutable $now): array
    {
        $summary = ReminderDeliverySummary::create(count($claims));
        $settingsByUser = [];
        $translators = [];

        foreach ($claims as $claim) {
            if (!$this->dispatch->claimIsSendable($claim, $now)) {
                $summary['skipped']++;
                continue;
            }
            try {
                $userId = (int) $claim['user_id'];
                $userSettings = $settingsByUser[$userId] ??= $this->settings->getForUser($userId);
                $language = $userSettings['effective_language'];
                $translator = $translators[$language] ??= new Translator($language, $this->languageDirectory);
                $payload = $this->createPayload($claim, $userSettings, $translator);
            } catch (Throwable) {
                // No provider request started, so a bounded retry cannot duplicate this send.
                $result = NotificationSendResult::fromHttpStatus(503);
                if ($this->dispatch->recordResult($claim, $result, $now)) {
                    $summary[$result->outcome]++;
                } else {
                    $summary['skipped']++;
                }
                continue;
            }

            try {
                $result = $this->sender->send([
                    'endpoint' => $claim['endpoint'],
                    'p256dh' => $claim['p256dh'],
                    'auth' => $claim['auth'],
                    'content_encoding' => $claim['content_encoding'],
                ], $payload);
            } catch (NotificationPayloadValidationException | PushSubscriptionValidationException) {
                $result = NotificationSendResult::fromHttpStatus(400);
            } catch (Throwable) {
                // An exception after the request starts cannot prove the provider rejected it.
                $result = NotificationSendResult::fromHttpStatus(null);
            }

            if (!$this->dispatch->recordResult($claim, $result, $now)) {
                $summary['skipped']++;
                continue;
            }
            $summary[$result->outcome]++;
        }

        return $summary;
    }

    /** Builds a same-origin notification using the user's saved calendar and timezone. */
    private function createPayload(array $claim, array $settings, Translator $translator): array
    {
        $timezone = new DateTimeZone($settings['timezone']);
        $dueAt = new DateTimeImmutable((string) $claim['task_due_at'], new DateTimeZone('UTC'));
        $localDueAt = $dueAt->setTimezone($timezone);
        if ($settings['calendar_system'] === 'jalali') {
            $formattedDueAt = Verta::instance($localDueAt->format('Y-m-d H:i:s'), $timezone)->format('j F Y H:i');
            $formattedDueAt = strtr($formattedDueAt, [
                '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
                '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
            ]);
        } else {
            $formattedDueAt = $localDueAt->format('M j, Y h:i A');
        }

        return [
            'title' => (string) $claim['task_title'],
            'body' => $translator->translate('push.reminder.due_at', ['date' => $formattedDueAt]),
            'tag' => 'mytodo-reminder-' . (int) $claim['reminder_id'],
            'url' => '/manage-tasks',
        ];
    }
}
