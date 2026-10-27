<?php
/** @var string $calendarCssClass */
/** @var \App\Localization\Translator $translator */
?>
<dialog class="dateTimeModalBackdrop" id="dateTimeModal" aria-labelledby="dateTimeModalTitle">
    <section
        class="dateTimeModal <?= $calendarCssClass ?>">
        <button class="dateTimeModalClose" id="closeDateTimeModal" type="button" data-i18n-aria-label="date_time.close" aria-label="<?= htmlspecialchars($translator->translate('date_time.close'), ENT_QUOTES, 'UTF-8') ?>">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>

        <div class="calendarHeader">
            <button class="calendarNavButton" id="previousCalendarMonth" type="button" data-i18n-aria-label="common.previous_month" aria-label="<?= htmlspecialchars($translator->translate('common.previous_month'), ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
            </button>
            <h2 id="dateTimeModalTitle" data-i18n="date_time.calendar"><?= htmlspecialchars($translator->translate('date_time.calendar'), ENT_QUOTES, 'UTF-8') ?></h2>
            <button class="calendarNavButton" id="nextCalendarMonth" type="button" data-i18n-aria-label="common.next_month" aria-label="<?= htmlspecialchars($translator->translate('common.next_month'), ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </button>
        </div>

        <div class="calendarWeekdays" aria-hidden="true">
            <span data-i18n="calendar.weekday.sun.short"><?= htmlspecialchars($translator->translate('calendar.weekday.sun.short'), ENT_QUOTES, 'UTF-8') ?></span>
            <span data-i18n="calendar.weekday.mon.short"><?= htmlspecialchars($translator->translate('calendar.weekday.mon.short'), ENT_QUOTES, 'UTF-8') ?></span>
            <span data-i18n="calendar.weekday.tue.short"><?= htmlspecialchars($translator->translate('calendar.weekday.tue.short'), ENT_QUOTES, 'UTF-8') ?></span>
            <span data-i18n="calendar.weekday.wed.short"><?= htmlspecialchars($translator->translate('calendar.weekday.wed.short'), ENT_QUOTES, 'UTF-8') ?></span>
            <span data-i18n="calendar.weekday.thu.short"><?= htmlspecialchars($translator->translate('calendar.weekday.thu.short'), ENT_QUOTES, 'UTF-8') ?></span>
            <span data-i18n="calendar.weekday.fri.short"><?= htmlspecialchars($translator->translate('calendar.weekday.fri.short'), ENT_QUOTES, 'UTF-8') ?></span>
            <span data-i18n="calendar.weekday.sat.short"><?= htmlspecialchars($translator->translate('calendar.weekday.sat.short'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="calendarDays" id="calendarDays" role="grid" aria-labelledby="dateTimeModalTitle"></div>

        <fieldset class="quickDateOptions">
            <legend class="srOnly" data-i18n="date_time.quick_selection"><?= htmlspecialchars($translator->translate('date_time.quick_selection'), ENT_QUOTES, 'UTF-8') ?></legend>
            <label>
                <input type="radio" name="quick_task_date" value="today" checked>
                <span data-i18n="date_time.today"><?= htmlspecialchars($translator->translate('date_time.today'), ENT_QUOTES, 'UTF-8') ?></span>
            </label>
            <label>
                <input type="radio" name="quick_task_date" value="tomorrow">
                <span data-i18n="date_time.tomorrow"><?= htmlspecialchars($translator->translate('date_time.tomorrow'), ENT_QUOTES, 'UTF-8') ?></span>
            </label>
            <label>
                <input type="radio" name="quick_task_date" value="no-date">
                <span data-i18n="date_time.no_date"><?= htmlspecialchars($translator->translate('date_time.no_date'), ENT_QUOTES, 'UTF-8') ?></span>
            </label>
        </fieldset>

        <section class="setTimeSection" id="setTimeSection" aria-labelledby="setTimeTitle">
            <div class="setTimeHeader">
                <h3 id="setTimeTitle" data-i18n="date_time.set_time"><?= htmlspecialchars($translator->translate('date_time.set_time'), ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="setTimeToggle" role="radiogroup" data-i18n-aria-label="date_time.enable_time" aria-label="<?= htmlspecialchars($translator->translate('date_time.enable_time'), ENT_QUOTES, 'UTF-8') ?>">
                    <label>
                        <input id="setTimeYes" type="radio" name="set_task_time" value="yes" checked>
                        <span data-i18n="date_time.yes"><?= htmlspecialchars($translator->translate('date_time.yes'), ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                    <label>
                        <input id="setTimeNo" type="radio" name="set_task_time" value="no">
                        <span data-i18n="date_time.no"><?= htmlspecialchars($translator->translate('date_time.no'), ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                </div>
            </div>

            <div class="timePicker" id="timePicker">
                <label>
                    <span class="timeFieldLabel" data-i18n="date_time.hour"><?= htmlspecialchars($translator->translate('date_time.hour'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input id="taskTimeHour" type="number" min="1" max="12" step="1" value="12" inputmode="numeric" data-i18n-aria-label="date_time.hour" aria-label="<?= htmlspecialchars($translator->translate('date_time.hour'), ENT_QUOTES, 'UTF-8') ?>" required>
                </label>
                <span class="timeSeparator" aria-hidden="true">:</span>
                <label>
                    <span class="timeFieldLabel" data-i18n="date_time.minute"><?= htmlspecialchars($translator->translate('date_time.minute'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input id="taskTimeMinute" type="number" min="0" max="59" step="1" value="0" inputmode="numeric" data-i18n-aria-label="date_time.minute" aria-label="<?= htmlspecialchars($translator->translate('date_time.minute'), ENT_QUOTES, 'UTF-8') ?>" required>
                </label>
                <label>
                    <span class="timeFieldLabel" data-i18n="date_time.period"><?= htmlspecialchars($translator->translate('date_time.period'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select id="taskTimePeriod" data-i18n-aria-label="date_time.am_or_pm" aria-label="<?= htmlspecialchars($translator->translate('date_time.am_or_pm'), ENT_QUOTES, 'UTF-8') ?>">
                        <option value="AM" data-i18n="date_time.am"><?= htmlspecialchars($translator->translate('date_time.am'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="PM" data-i18n="date_time.pm"><?= htmlspecialchars($translator->translate('date_time.pm'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </label>
            </div>
        </section>

        <p class="dateTimeModalMessage" id="dateTimeModalMessage" role="alert" aria-live="polite"></p>

        <div class="dateTimeModalActions">
            <button class="cancelDateTimeButton" id="cancelDateTimeButton" type="button" data-i18n="common.cancel"><?= htmlspecialchars($translator->translate('common.cancel'), ENT_QUOTES, 'UTF-8') ?></button>
            <button class="applyDateTimeButton" id="applyDateTimeButton" type="button" data-i18n="common.apply"><?= htmlspecialchars($translator->translate('common.apply'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </section>
</dialog>
