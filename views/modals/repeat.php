<?php
/** @var string $calendarCssClass */
/** @var \App\Localization\Translator $translator */
?>
<dialog class="repeatModalBackdrop" id="repeatModal" aria-labelledby="repeatModalTitle">
    <div class="repeatModal">
        <button class="repeatModalClose" id="closeRepeatModal" type="button" data-i18n-aria-label="repeat.modal.close" aria-label="<?= htmlspecialchars($translator->translate('repeat.modal.close'), ENT_QUOTES, 'UTF-8') ?>">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>

        <div class="repeatModalScroll">
            <h2 id="repeatModalTitle" data-i18n="repeat.modal.title"><?= htmlspecialchars($translator->translate('repeat.modal.title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="repeatModalHint" data-i18n="repeat.modal.hint"><?= htmlspecialchars($translator->translate('repeat.modal.hint'), ENT_QUOTES, 'UTF-8') ?></p>

            <fieldset class="repeatFrequencyOptions">
                <legend class="srOnly" data-i18n="repeat.modal.frequency"><?= htmlspecialchars($translator->translate('repeat.modal.frequency'), ENT_QUOTES, 'UTF-8') ?></legend>
                <label>
                    <input type="radio" name="task_repeat_frequency" value="daily" checked>
                    <span data-i18n="repeat.frequency.daily"><?= htmlspecialchars($translator->translate('repeat.frequency.daily'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <label>
                    <input type="radio" name="task_repeat_frequency" value="weekly">
                    <span data-i18n="repeat.frequency.weekly"><?= htmlspecialchars($translator->translate('repeat.frequency.weekly'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <label>
                    <input type="radio" name="task_repeat_frequency" value="monthly">
                    <span data-i18n="repeat.frequency.monthly"><?= htmlspecialchars($translator->translate('repeat.frequency.monthly'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <label>
                    <input type="radio" name="task_repeat_frequency" value="custom">
                    <span data-i18n="repeat.frequency.custom"><?= htmlspecialchars($translator->translate('repeat.frequency.custom'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
            </fieldset>

            <section class="customRepeatSection" id="customRepeatSection" aria-labelledby="customRepeatTitle" hidden>
                <h3 id="customRepeatTitle" data-i18n="repeat.every"><?= htmlspecialchars($translator->translate('repeat.every'), ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="repeatEveryFields">
                    <label class="srOnly" for="repeatInterval" data-i18n="repeat.interval"><?= htmlspecialchars($translator->translate('repeat.interval'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input id="repeatInterval" type="number" min="1" max="999" step="1" inputmode="numeric" value="1">
                    <label class="srOnly" for="repeatUnit" data-i18n="repeat.interval_unit"><?= htmlspecialchars($translator->translate('repeat.interval_unit'), ENT_QUOTES, 'UTF-8') ?></label>
                    <select id="repeatUnit">
                        <option value="day" data-i18n="repeat.unit.day"><?= htmlspecialchars($translator->translate('repeat.unit.day'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="week" data-i18n="repeat.unit.week"><?= htmlspecialchars($translator->translate('repeat.unit.week'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="month" data-i18n="repeat.unit.month"><?= htmlspecialchars($translator->translate('repeat.unit.month'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </div>

            </section>

            <fieldset class="repeatOnWeek" id="repeatOnWeek" hidden>
                <legend data-i18n="repeat.on_week"><?= htmlspecialchars($translator->translate('repeat.on_week'), ENT_QUOTES, 'UTF-8') ?></legend>
                <div class="repeatWeekDays">
                    <label><input type="checkbox" value="0"><span data-i18n="calendar.weekday.sun.short"><?= htmlspecialchars($translator->translate('calendar.weekday.sun.short'), ENT_QUOTES, 'UTF-8') ?></span></label>
                    <label><input type="checkbox" value="1"><span data-i18n="calendar.weekday.mon.short"><?= htmlspecialchars($translator->translate('calendar.weekday.mon.short'), ENT_QUOTES, 'UTF-8') ?></span></label>
                    <label><input type="checkbox" value="2"><span data-i18n="calendar.weekday.tue.short"><?= htmlspecialchars($translator->translate('calendar.weekday.tue.short'), ENT_QUOTES, 'UTF-8') ?></span></label>
                    <label><input type="checkbox" value="3"><span data-i18n="calendar.weekday.wed.short"><?= htmlspecialchars($translator->translate('calendar.weekday.wed.short'), ENT_QUOTES, 'UTF-8') ?></span></label>
                    <label><input type="checkbox" value="4"><span data-i18n="calendar.weekday.thu.short"><?= htmlspecialchars($translator->translate('calendar.weekday.thu.short'), ENT_QUOTES, 'UTF-8') ?></span></label>
                    <label><input type="checkbox" value="5"><span data-i18n="calendar.weekday.fri.short"><?= htmlspecialchars($translator->translate('calendar.weekday.fri.short'), ENT_QUOTES, 'UTF-8') ?></span></label>
                    <label><input type="checkbox" value="6"><span data-i18n="calendar.weekday.sat.short"><?= htmlspecialchars($translator->translate('calendar.weekday.sat.short'), ENT_QUOTES, 'UTF-8') ?></span></label>
                </div>
            </fieldset>

            <fieldset class="repeatOnMonth" id="repeatOnMonth" hidden>
                <legend data-i18n="repeat.on_month_day"><?= htmlspecialchars($translator->translate('repeat.on_month_day'), ENT_QUOTES, 'UTF-8') ?></legend>
                <div class="repeatMonthDays">
                    <?php for ($monthDay = 1; $monthDay <= 31; $monthDay++): ?>
                        <label>
                            <input type="radio" name="repeat_month_day" value="<?= $monthDay ?>">
                            <span><?= $monthDay ?></span>
                        </label>
                    <?php endfor; ?>
                </div>
                <label class="repeatMonthLastDay">
                    <input type="radio" name="repeat_month_day" value="last">
                    <span data-i18n="repeat.last_day_of_month"><?= htmlspecialchars($translator->translate('repeat.last_day_of_month'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <p class="repeatMonthHint" id="repeatMonthHint" aria-live="polite" hidden></p>
            </fieldset>

            <section class="repeatEndsSection" aria-labelledby="repeatEndsTitle">
                <h3 id="repeatEndsTitle" data-i18n="repeat.ends_at"><?= htmlspecialchars($translator->translate('repeat.ends_at'), ENT_QUOTES, 'UTF-8') ?></h3>
                <fieldset class="repeatEndOptions">
                    <legend class="srOnly" data-i18n="repeat.ending"><?= htmlspecialchars($translator->translate('repeat.ending'), ENT_QUOTES, 'UTF-8') ?></legend>
                    <label>
                        <input type="radio" name="task_repeat_end" value="endlessly" checked>
                        <span data-i18n="repeat.end.endlessly"><?= htmlspecialchars($translator->translate('repeat.end.endlessly'), ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                    <label>
                        <input type="radio" name="task_repeat_end" value="date">
                        <span data-i18n="repeat.end.date"><?= htmlspecialchars($translator->translate('repeat.end.date'), ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                    <label>
                        <input type="radio" name="task_repeat_end" value="count">
                        <span data-i18n="repeat.end.count"><?= htmlspecialchars($translator->translate('repeat.end.count'), ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                </fieldset>

                <div class="repeatEndDateSection <?= $calendarCssClass ?>" id="repeatEndDateSection" hidden>
                    <div class="repeatCalendarHeader">
                        <button id="previousRepeatMonth" type="button" data-i18n-aria-label="common.previous_month" aria-label="<?= htmlspecialchars($translator->translate('common.previous_month'), ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                        </button>
                        <h4 id="repeatCalendarMonth" data-i18n="repeat.calendar"><?= htmlspecialchars($translator->translate('repeat.calendar'), ENT_QUOTES, 'UTF-8') ?></h4>
                        <button id="nextRepeatMonth" type="button" data-i18n-aria-label="common.next_month" aria-label="<?= htmlspecialchars($translator->translate('common.next_month'), ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="repeatCalendarWeekdays" aria-hidden="true">
                        <span data-i18n="calendar.weekday.sun.short"><?= htmlspecialchars($translator->translate('calendar.weekday.sun.short'), ENT_QUOTES, 'UTF-8') ?></span><span data-i18n="calendar.weekday.mon.short"><?= htmlspecialchars($translator->translate('calendar.weekday.mon.short'), ENT_QUOTES, 'UTF-8') ?></span><span data-i18n="calendar.weekday.tue.short"><?= htmlspecialchars($translator->translate('calendar.weekday.tue.short'), ENT_QUOTES, 'UTF-8') ?></span><span data-i18n="calendar.weekday.wed.short"><?= htmlspecialchars($translator->translate('calendar.weekday.wed.short'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span data-i18n="calendar.weekday.thu.short"><?= htmlspecialchars($translator->translate('calendar.weekday.thu.short'), ENT_QUOTES, 'UTF-8') ?></span><span data-i18n="calendar.weekday.fri.short"><?= htmlspecialchars($translator->translate('calendar.weekday.fri.short'), ENT_QUOTES, 'UTF-8') ?></span><span data-i18n="calendar.weekday.sat.short"><?= htmlspecialchars($translator->translate('calendar.weekday.sat.short'), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="repeatCalendarDays" id="repeatCalendarDays" role="grid" aria-labelledby="repeatCalendarMonth"></div>
                    <p class="repeatEndDateSummary" id="repeatEndDateSummary" data-i18n="repeat.select_end_date"><?= htmlspecialchars($translator->translate('repeat.select_end_date'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>

                <label class="repeatCountField" id="repeatCountField" for="repeatCount" hidden>
                    <span data-i18n="repeat.number_of_repeats"><?= htmlspecialchars($translator->translate('repeat.number_of_repeats'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input id="repeatCount" type="number" min="1" max="9999" step="1" inputmode="numeric" value="10">
                </label>
            </section>

            <p class="repeatModalMessage" id="repeatModalMessage" role="alert" aria-live="polite"></p>

            <div class="repeatModalActions">
                <button class="cancelRepeatButton" id="cancelRepeatButton" type="button" data-i18n="common.cancel"><?= htmlspecialchars($translator->translate('common.cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                <button class="applyRepeatButton" id="applyRepeatButton" type="button" data-i18n="common.apply"><?= htmlspecialchars($translator->translate('common.apply'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </div>
    </div>
</dialog>
