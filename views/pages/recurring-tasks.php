<?php

declare(strict_types=1);

use App\Helpers\TimezoneHelper;
use Hekmatinasser\Verta\Verta;

/** @var array<int, object> $repeatRules */
/** @var string $repeatFilter */

$repeatRules = isset($repeatRules) && is_array($repeatRules) ? $repeatRules : [];
$repeatStatuses = ['active', 'paused', 'completed', 'cancelled'];
$repeatFilters = ['all', ...$repeatStatuses];
$repeatFilter = in_array($repeatFilter ?? '', $repeatFilters, true) ? $repeatFilter : 'all';
$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$text = static fn(string $key, array $values = []): string => $escape($translator->translate($key, $values));
$displayTimezone = new DateTimeZone($renderTimezone);
$formatDate = static function (?string $value, bool $hasTime = false, bool $dateOnly = false) use ($displayTimezone, $calendarSystem, $translator): string {
    if ($value === null || $value === '') {
        return $translator->translate('recurring.summary.no_next');
    }
    $date = new DateTimeImmutable($value, $dateOnly ? $displayTimezone : TimezoneHelper::getApplicationTimezone());
    $date = $date->setTimezone($displayTimezone);
    $format = $hasTime ? 'Y/m/d H:i' : 'Y/m/d';
    return $calendarSystem === 'jalali'
        ? Verta::instance($date->format('Y-m-d H:i:s'), $displayTimezone)->format($format)
        : $date->format($format);
};
$weekdayKeys = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
?>
<section class="recurringTasksPage" aria-labelledby="recurringTasksTitle">
    <header class="recurringTasksHeader">
        <h1 id="recurringTasksTitle" data-i18n="recurring.title"><?= $text('recurring.title') ?></h1>
        <nav class="recurringFilters" aria-label="<?= $text('recurring.filter.label') ?>" data-i18n-aria-label="recurring.filter.label">
            <?php foreach ($repeatFilters as $filter): ?>
                <a href="/recurring-tasks?filter=<?= $filter ?>" data-dashboard-link data-repeat-filter="<?= $filter ?>" data-i18n="recurring.filter.<?= $filter ?>"<?= $repeatFilter === $filter ? ' aria-current="page"' : '' ?>><?= $text('recurring.filter.' . $filter) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>
    <p id="recurringTasksStatus" class="recurringMessage" role="status" aria-live="polite" hidden></p>
    <?php if ($repeatRules === []): ?>
        <p class="recurringEmpty" data-i18n="recurring.empty"><?= $text('recurring.empty') ?></p>
    <?php endif; ?>
    <div class="recurringRules">
        <?php foreach ($repeatRules as $rule): ?>
            <?php
            $ruleId = max(0, (int) ($rule->id ?? 0));
            $status = in_array($rule->status ?? '', $repeatStatuses, true) ? $rule->status : 'cancelled';
            $unit = in_array($rule->interval_unit ?? '', ['day', 'week', 'month'], true) ? $rule->interval_unit : 'day';
            $interval = max(1, (int) ($rule->interval_value ?? 1));
            $weekDays = json_decode((string) ($rule->week_days ?? '[]'), true);
            $weekDays = is_array($weekDays) ? array_values(array_filter($weekDays, static fn(mixed $day): bool => is_int($day) && $day >= 0 && $day <= 6)) : [];
            $schedule = $translator->translate('recurring.summary.every_' . $unit, ['count' => $interval]);
            if ($unit === 'week') {
                $days = array_map(static fn(int $day): string => $translator->translate('calendar.weekday.' . $weekdayKeys[$day] . '.short'), $weekDays);
                $schedule .= ' · ' . implode(' / ', $days);
            } elseif ($unit === 'month') {
                $schedule .= ' · ' . $translator->translate(
                    ($rule->month_day_mode ?? '') === 'last_day' ? 'recurring.summary.last_day' : 'recurring.summary.month_day',
                    ['day' => max(1, min(31, (int) ($rule->month_day ?? 1)))]
                );
            }
            $endType = in_array($rule->end_type ?? '', ['endlessly', 'date', 'count'], true) ? $rule->end_type : 'endlessly';
            $endSummary = match ($endType) {
                'date' => $translator->translate('recurring.summary.ends_date', ['date' => $formatDate($rule->end_date, false, true)]),
                'count' => $translator->translate('recurring.summary.ends_count', ['count' => max(0, (int) $rule->repeat_count)]),
                default => $translator->translate('recurring.summary.endlessly'),
            };
            // The generation cursor can be months ahead; show the earliest already generated task first.
            $nextAt = $rule->first_future_task_at ?? ($status === 'active' ? ($rule->next_occurrence_at ?? null) : null);
            $payload = [
                'id' => $ruleId,
                'title' => (string) ($rule->title ?? ''),
                'status' => $status,
                'start_at' => (string) ($rule->start_at ?? ''),
                'has_time' => !empty($rule->has_time),
                'timezone' => (string) ($rule->timezone ?? 'UTC'),
                'frequency' => in_array($rule->frequency ?? '', ['daily', 'weekly', 'monthly', 'custom'], true) ? $rule->frequency : 'custom',
                'interval' => $interval,
                'unit' => $unit,
                'week_days' => $weekDays,
                'month_day' => isset($rule->month_day) ? (int) $rule->month_day : null,
                'month_day_mode' => ($rule->month_day_mode ?? '') === 'last_day' ? 'last_day' : 'clamp',
                'ends' => ['type' => $endType, 'date' => $rule->end_date ?? null, 'count' => isset($rule->repeat_count) ? (int) $rule->repeat_count : null],
            ];
            $encodedRule = json_encode($payload, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
            $actions = match ($status) {
                'active' => ['edit', 'pause', 'cancel'],
                'paused' => ['edit', 'resume', 'cancel'],
                default => [],
            };
            ?>
            <article class="recurringRule" data-repeat-rule-id="<?= $ruleId ?>" data-repeat-status="<?= $status ?>" data-repeat-rule="<?= $escape($encodedRule) ?>" aria-labelledby="recurringRuleTitle<?= $ruleId ?>">
                <div class="recurringRuleMain">
                    <h2 id="recurringRuleTitle<?= $ruleId ?>"><bdi><?= $escape($rule->title ?? '') ?></bdi></h2>
                    <p class="recurringSchedule"><?= $escape($schedule) ?></p>
                    <dl class="recurringDetails">
                        <div><dt><?= $text('recurring.summary.next') ?></dt><dd><?= $escape($formatDate($nextAt, !empty($rule->has_time))) ?></dd></div>
                        <div><dt><?= $text('recurring.summary.ends') ?></dt><dd><?= $escape($endSummary) ?></dd></div>
                        <div><dt><?= $text('recurring.summary.tasks') ?></dt><dd><?= max(0, (int) ($rule->task_count ?? 0)) ?></dd></div>
                    </dl>
                </div>
                <span class="recurringStatus" data-i18n="recurring.status.<?= $status ?>"><?= $text('recurring.status.' . $status) ?></span>
                <?php if ($actions !== []): ?>
                    <div class="recurringActions" role="group" aria-label="<?= $text('recurring.action.for_title', ['title' => $rule->title ?? '']) ?>">
                        <?php foreach ($actions as $action): ?>
                            <button type="button" data-repeat-action="<?= $action ?>" data-repeat-rule-id="<?= $ruleId ?>" data-i18n="recurring.action.<?= $action ?>"><?= $text('recurring.action.' . $action) ?></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
