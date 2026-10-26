import { translate } from '../../utils/i18n.js';

export function createRepeatForm(options) {
    const customRepeatSection = document.getElementById('customRepeatSection');
    const repeatInterval = document.getElementById('repeatInterval');
    const repeatUnit = document.getElementById('repeatUnit');
    const repeatOnWeek = document.getElementById('repeatOnWeek');
    const repeatOnMonth = document.getElementById('repeatOnMonth');
    const repeatMonthHint = document.getElementById('repeatMonthHint');
    const repeatEndDateSection = document.getElementById('repeatEndDateSection');
    const repeatCountField = document.getElementById('repeatCountField');
    const repeatCount = document.getElementById('repeatCount');
    const frequencyInputs = Array.from(document.querySelectorAll('input[name="task_repeat_frequency"]'));
    const endInputs = Array.from(document.querySelectorAll('input[name="task_repeat_end"]'));
    const weekDayInputs = Array.from(document.querySelectorAll('#repeatOnWeek input[type="checkbox"]'));
    const monthDayInputs = Array.from(document.querySelectorAll('input[name="repeat_month_day"]'));

    function selectedValue(inputs) {
        const selected = inputs.find(function (input) {
            return input.checked;
        });

        return selected ? selected.value : '';
    }

    function selectValue(inputs, value) {
        inputs.forEach(function (input) {
            input.checked = input.value === String(value);
        });
    }

    function getRuleUnit(frequency) {
        if (frequency === 'weekly') {
            return 'week';
        }

        if (frequency === 'monthly') {
            return 'month';
        }

        return frequency === 'custom' && repeatUnit ? repeatUnit.value : 'day';
    }

    function ensureRepeatOnSelection(unit) {
        const baseDate = options.getBaseDate();

        if (unit === 'week' && !weekDayInputs.some(function (input) { return input.checked; })) {
            const baseWeekDay = weekDayInputs.find(function (input) {
                return Number(input.value) === baseDate.getDay();
            });

            if (baseWeekDay) {
                baseWeekDay.checked = true;
            }
        }

        if (unit === 'month' && !monthDayInputs.some(function (input) { return input.checked; })) {
            selectValue(monthDayInputs, baseDate.getDate());
        }
    }

    function updateMonthHint(unit) {
        if (!repeatMonthHint) {
            return;
        }

        const selectedMonthDay = monthDayInputs.find(function (input) {
            return input.checked;
        });
        const selectedValue = selectedMonthDay ? selectedMonthDay.value : '';

        if (unit !== 'month' || selectedValue === '') {
            repeatMonthHint.hidden = true;
            repeatMonthHint.textContent = '';
            return;
        }

        if (selectedValue === 'last') {
            repeatMonthHint.textContent = translate('repeat.hint.last_day', {}, 'This task will repeat on the last day of every month.');
            repeatMonthHint.hidden = false;
            return;
        }

        if (Number(selectedValue) >= 29) {
            repeatMonthHint.textContent = translate('repeat.hint.short_months', {}, 'For shorter months, this task will repeat on the last day of the month.');
            repeatMonthHint.hidden = false;
            return;
        }

        repeatMonthHint.hidden = true;
        repeatMonthHint.textContent = '';
    }

    function update() {
        const frequency = selectedValue(frequencyInputs);
        const endType = selectedValue(endInputs);
        const isCustom = frequency === 'custom';
        const unit = getRuleUnit(frequency);

        if (customRepeatSection) {
            customRepeatSection.hidden = !isCustom;
        }

        ensureRepeatOnSelection(unit);

        if (repeatOnWeek) {
            repeatOnWeek.hidden = unit !== 'week';
        }

        if (repeatOnMonth) {
            repeatOnMonth.hidden = unit !== 'month';
        }

        updateMonthHint(unit);

        if (repeatEndDateSection) {
            repeatEndDateSection.hidden = endType !== 'date';
        }

        if (repeatCountField) {
            repeatCountField.hidden = endType !== 'count';
        }

        if (endType === 'date') {
            options.calendar.render();
        }
    }

    function populate(rule) {
        const baseDate = options.getBaseDate();

        selectValue(frequencyInputs, rule.frequency);
        selectValue(endInputs, rule.ends.type);

        if (repeatInterval) {
            repeatInterval.value = String(rule.interval || 1);
        }

        if (repeatUnit) {
            repeatUnit.value = rule.unit || 'day';
        }

        weekDayInputs.forEach(function (input) {
            input.checked = (rule.week_days || []).includes(Number(input.value));
        });
        selectValue(
            monthDayInputs,
            rule.month_day_mode === 'last_day' ? 'last' : (rule.month_day || baseDate.getDate())
        );

        if (repeatCount) {
            repeatCount.value = String(rule.ends.count || 10);
        }

        options.calendar.setEndDate(rule.ends.date || '', baseDate);
        update();
    }

    function collect() {
        const frequency = selectedValue(frequencyInputs);
        const baseDate = options.getBaseDate();
        const unitByFrequency = {
            daily: 'day',
            weekly: 'week',
            monthly: 'month'
        };
        const unit = frequency === 'custom' && repeatUnit ? repeatUnit.value : unitByFrequency[frequency];
        const interval = frequency === 'custom' && repeatInterval ? Number(repeatInterval.value) : 1;
        let weekDays = weekDayInputs
            .filter(function (input) { return input.checked; })
            .map(function (input) { return Number(input.value); })
            .sort(function (first, second) { return first - second; });
        const selectedMonthDay = monthDayInputs.find(function (input) { return input.checked; });
        const repeatsOnLastDay = Boolean(selectedMonthDay && selectedMonthDay.value === 'last');
        const monthDay = repeatsOnLastDay
            ? null
            : (selectedMonthDay ? Number(selectedMonthDay.value) : baseDate.getDate());
        const endType = selectedValue(endInputs);

        return {
            frequency,
            interval,
            unit,
            repeat_on: unit === 'week'
                ? weekDays
                : (unit === 'month' ? (repeatsOnLastDay ? 'last_day' : monthDay) : null),
            week_days: weekDays,
            month_day: monthDay,
            month_day_mode: repeatsOnLastDay ? 'last_day' : 'clamp',
            ends: {
                type: endType,
                date: endType === 'date' ? options.calendar.getEndDate() : '',
                count: endType === 'count' && repeatCount ? Number(repeatCount.value) : null
            }
        };
    }

    function handleControlChange() {
        options.setMessage('');
        update();
    }

    frequencyInputs.forEach(function (input) {
        input.addEventListener('change', handleControlChange);
    });

    endInputs.forEach(function (input) {
        input.addEventListener('change', handleControlChange);
    });

    monthDayInputs.forEach(function (input) {
        input.addEventListener('change', handleControlChange);
    });

    if (repeatUnit) {
        repeatUnit.addEventListener('change', handleControlChange);
    }

    return {
        collect,
        populate,
        update
    };
}
