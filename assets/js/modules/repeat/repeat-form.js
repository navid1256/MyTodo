export function createRepeatForm(options) {
    const customRepeatSection = document.getElementById('customRepeatSection');
    const repeatInterval = document.getElementById('repeatInterval');
    const repeatUnit = document.getElementById('repeatUnit');
    const repeatOnWeek = document.getElementById('repeatOnWeek');
    const repeatOnMonth = document.getElementById('repeatOnMonth');
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

    function ensureRepeatOnSelection() {
        const baseDate = options.getBaseDate();

        if (repeatUnit && repeatUnit.value === 'week' && !weekDayInputs.some(function (input) { return input.checked; })) {
            const baseWeekDay = weekDayInputs.find(function (input) {
                return Number(input.value) === baseDate.getDay();
            });

            if (baseWeekDay) {
                baseWeekDay.checked = true;
            }
        }

        if (repeatUnit && repeatUnit.value === 'month' && !monthDayInputs.some(function (input) { return input.checked; })) {
            selectValue(monthDayInputs, baseDate.getDate());
        }
    }

    function update() {
        const frequency = selectedValue(frequencyInputs);
        const endType = selectedValue(endInputs);
        const isCustom = frequency === 'custom';
        const customUnit = repeatUnit ? repeatUnit.value : 'day';

        if (customRepeatSection) {
            customRepeatSection.hidden = !isCustom;
        }

        ensureRepeatOnSelection();

        if (repeatOnWeek) {
            repeatOnWeek.hidden = !isCustom || customUnit !== 'week';
        }

        if (repeatOnMonth) {
            repeatOnMonth.hidden = !isCustom || customUnit !== 'month';
        }

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
        selectValue(monthDayInputs, rule.month_day || baseDate.getDate());

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
        let monthDay = selectedMonthDay ? Number(selectedMonthDay.value) : baseDate.getDate();
        const endType = selectedValue(endInputs);

        if (frequency === 'weekly') {
            weekDays = [baseDate.getDay()];
        }

        if (frequency === 'monthly') {
            monthDay = baseDate.getDate();
        }

        return {
            frequency,
            interval,
            unit,
            repeat_on: unit === 'week' ? weekDays : (unit === 'month' ? monthDay : null),
            week_days: weekDays,
            month_day: monthDay,
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

    if (repeatUnit) {
        repeatUnit.addEventListener('change', handleControlChange);
    }

    return {
        collect,
        populate,
        update
    };
}
