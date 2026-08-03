const MONTH_NAMES = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

const WEEKDAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function cloneRule(rule) {
    return rule ? JSON.parse(JSON.stringify(rule)) : null;
}

function normaliseDate(date) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate(), 12);
}

function dateToKey(date) {
    return date.getFullYear()
        + '-' + String(date.getMonth() + 1).padStart(2, '0')
        + '-' + String(date.getDate()).padStart(2, '0');
}

function keyToDate(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) {
        return null;
    }

    var parts = value.split('-').map(Number);
    var date = new Date(parts[0], parts[1] - 1, parts[2], 12);

    if (
        date.getFullYear() !== parts[0]
        || date.getMonth() !== parts[1] - 1
        || date.getDate() !== parts[2]
    ) {
        return null;
    }

    return date;
}

function formatDate(date) {
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
}

export function initRepeatPicker() {
    var repeatModal = document.getElementById('repeatModal');
    var setTaskRepeatButton = document.getElementById('setTaskRepeatButton');
    var closeRepeatModalButton = document.getElementById('closeRepeatModal');
    var cancelRepeatButton = document.getElementById('cancelRepeatButton');
    var applyRepeatButton = document.getElementById('applyRepeatButton');
    var taskRepeat = document.getElementById('taskRepeat');
    var taskRepeatSummary = document.getElementById('taskRepeatSummary');
    var taskDueAt = document.getElementById('taskDueAt');
    var customRepeatSection = document.getElementById('customRepeatSection');
    var repeatInterval = document.getElementById('repeatInterval');
    var repeatUnit = document.getElementById('repeatUnit');
    var repeatOnWeek = document.getElementById('repeatOnWeek');
    var repeatOnMonth = document.getElementById('repeatOnMonth');
    var repeatEndDateSection = document.getElementById('repeatEndDateSection');
    var repeatCountField = document.getElementById('repeatCountField');
    var repeatCount = document.getElementById('repeatCount');
    var repeatCalendarMonth = document.getElementById('repeatCalendarMonth');
    var repeatCalendarDays = document.getElementById('repeatCalendarDays');
    var previousRepeatMonth = document.getElementById('previousRepeatMonth');
    var nextRepeatMonth = document.getElementById('nextRepeatMonth');
    var repeatEndDateSummary = document.getElementById('repeatEndDateSummary');
    var repeatModalMessage = document.getElementById('repeatModalMessage');
    var frequencyInputs = Array.from(document.querySelectorAll('input[name="task_repeat_frequency"]'));
    var endInputs = Array.from(document.querySelectorAll('input[name="task_repeat_end"]'));
    var weekDayInputs = Array.from(document.querySelectorAll('#repeatOnWeek input[type="checkbox"]'));
    var monthDayInputs = Array.from(document.querySelectorAll('input[name="repeat_month_day"]'));
    var appliedRule = null;
    var draftEndDate = '';
    var calendarDate = normaliseDate(new Date());
    var lastTrigger = null;

    function selectedValue(inputs) {
        var selected = inputs.find(function (input) {
            return input.checked;
        });

        return selected ? selected.value : '';
    }

    function selectValue(inputs, value) {
        inputs.forEach(function (input) {
            input.checked = input.value === String(value);
        });
    }

    function getTaskStartDate() {
        if (!taskDueAt || !taskDueAt.value) {
            return null;
        }

        return keyToDate(taskDueAt.value.slice(0, 10));
    }

    function getBaseDate() {
        return getTaskStartDate() || normaliseDate(new Date());
    }

    function setMessage(message) {
        if (repeatModalMessage) {
            repeatModalMessage.textContent = message;
        }
    }

    function createDefaultRule() {
        var baseDate = getBaseDate();

        return {
            frequency: 'daily',
            interval: 1,
            unit: 'day',
            repeat_on: null,
            week_days: [baseDate.getDay()],
            month_day: baseDate.getDate(),
            ends: {
                type: 'endlessly',
                date: '',
                count: 10
            }
        };
    }

    function ensureRepeatOnSelection() {
        var baseDate = getBaseDate();

        if (repeatUnit && repeatUnit.value === 'week' && !weekDayInputs.some(function (input) { return input.checked; })) {
            var baseWeekDay = weekDayInputs.find(function (input) {
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

    function updateConditionalSections() {
        var frequency = selectedValue(frequencyInputs);
        var endType = selectedValue(endInputs);
        var isCustom = frequency === 'custom';
        var customUnit = repeatUnit ? repeatUnit.value : 'day';

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
            renderCalendar();
        }
    }

    function getMinimumEndDate() {
        return getTaskStartDate() || normaliseDate(new Date());
    }

    function renderCalendar() {
        if (!repeatCalendarDays || !repeatCalendarMonth) {
            return;
        }

        var year = calendarDate.getFullYear();
        var month = calendarDate.getMonth();
        var firstOfMonth = new Date(year, month, 1, 12);
        var firstVisibleDate = new Date(year, month, 1 - firstOfMonth.getDay(), 12);
        var minimumEndDate = getMinimumEndDate();
        var selectedEndDate = keyToDate(draftEndDate);
        var minimumMonth = new Date(minimumEndDate.getFullYear(), minimumEndDate.getMonth(), 1, 12);

        repeatCalendarMonth.textContent = MONTH_NAMES[month] + ' ' + year;
        repeatCalendarDays.textContent = '';

        if (previousRepeatMonth) {
            previousRepeatMonth.disabled = firstOfMonth <= minimumMonth;
        }

        for (var index = 0; index < 42; index++) {
            var day = new Date(
                firstVisibleDate.getFullYear(),
                firstVisibleDate.getMonth(),
                firstVisibleDate.getDate() + index,
                12
            );
            var dayKey = dateToKey(day);
            var button = document.createElement('button');
            var isDisabled = day <= minimumEndDate;

            button.type = 'button';
            button.className = 'repeatCalendarDay';
            button.textContent = String(day.getDate());
            button.setAttribute('role', 'gridcell');
            button.setAttribute('aria-label', formatDate(day));
            button.disabled = isDisabled;

            if (day.getMonth() !== month) {
                button.classList.add('outsideMonth');
            }

            if (selectedEndDate && dayKey === dateToKey(selectedEndDate)) {
                button.classList.add('selected');
                button.setAttribute('aria-selected', 'true');
            }

            button.addEventListener('click', function (selectedDate, selectedKey) {
                return function () {
                    draftEndDate = selectedKey;
                    calendarDate = new Date(
                        selectedDate.getFullYear(),
                        selectedDate.getMonth(),
                        1,
                        12
                    );
                    setMessage('');
                    renderCalendar();
                };
            }(day, dayKey));

            repeatCalendarDays.appendChild(button);
        }

        if (repeatEndDateSummary) {
            repeatEndDateSummary.textContent = selectedEndDate
                ? 'Ends on ' + formatDate(selectedEndDate)
                : 'Select an end date';
        }
    }

    function populateForm(rule) {
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
        selectValue(monthDayInputs, rule.month_day || getBaseDate().getDate());

        draftEndDate = rule.ends.date || '';

        if (repeatCount) {
            repeatCount.value = String(rule.ends.count || 10);
        }

        var calendarBase = keyToDate(draftEndDate) || getBaseDate();
        calendarDate = new Date(calendarBase.getFullYear(), calendarBase.getMonth(), 1, 12);
        updateConditionalSections();
    }

    function collectRule() {
        var frequency = selectedValue(frequencyInputs);
        var baseDate = getBaseDate();
        var unitByFrequency = {
            daily: 'day',
            weekly: 'week',
            monthly: 'month'
        };
        var unit = frequency === 'custom' && repeatUnit ? repeatUnit.value : unitByFrequency[frequency];
        var interval = frequency === 'custom' && repeatInterval ? Number(repeatInterval.value) : 1;
        var weekDays = weekDayInputs
            .filter(function (input) { return input.checked; })
            .map(function (input) { return Number(input.value); })
            .sort(function (first, second) { return first - second; });
        var selectedMonthDay = monthDayInputs.find(function (input) { return input.checked; });
        var monthDay = selectedMonthDay ? Number(selectedMonthDay.value) : baseDate.getDate();
        var endType = selectedValue(endInputs);

        if (frequency === 'weekly') {
            weekDays = [baseDate.getDay()];
        }

        if (frequency === 'monthly') {
            monthDay = baseDate.getDate();
        }

        return {
            frequency: frequency,
            interval: interval,
            unit: unit,
            repeat_on: unit === 'week' ? weekDays : (unit === 'month' ? monthDay : null),
            week_days: weekDays,
            month_day: monthDay,
            ends: {
                type: endType,
                date: endType === 'date' ? draftEndDate : '',
                count: endType === 'count' && repeatCount ? Number(repeatCount.value) : null
            }
        };
    }

    function validateRule(rule) {
        var startDate = getTaskStartDate();

        if (!startDate) {
            return 'Please set a task date before adding repeat settings.';
        }

        if (rule.frequency === 'custom') {
            if (!Number.isInteger(rule.interval) || rule.interval < 1 || rule.interval > 999) {
                return 'Repeat Every must be a whole number between 1 and 999.';
            }

            if (rule.unit === 'week' && rule.week_days.length === 0) {
                return 'Choose at least one weekday for the repeat schedule.';
            }

            if (rule.unit === 'month' && (rule.month_day < 1 || rule.month_day > 31)) {
                return 'Choose a valid day of the month.';
            }
        }

        if (rule.ends.type === 'date') {
            var endDate = keyToDate(rule.ends.date);

            if (!endDate) {
                return 'Please select an end date from the calendar.';
            }

            if (endDate <= startDate) {
                return 'The repeat end date must be after the task date.';
            }
        }

        if (
            rule.ends.type === 'count'
            && (!Number.isInteger(rule.ends.count) || rule.ends.count < 1 || rule.ends.count > 9999)
        ) {
            return 'Repeat Counts must be a whole number between 1 and 9999.';
        }

        return '';
    }

    function formatRuleSummary(rule) {
        var scheduleText;

        if (rule.frequency === 'daily') {
            scheduleText = 'Repeats daily';
        } else if (rule.frequency === 'weekly') {
            scheduleText = 'Repeats weekly on ' + WEEKDAY_NAMES[rule.week_days[0]];
        } else if (rule.frequency === 'monthly') {
            scheduleText = 'Repeats monthly on day ' + rule.month_day;
        } else if (rule.unit === 'day') {
            scheduleText = 'Repeats every ' + rule.interval + (rule.interval === 1 ? ' day' : ' days');
        } else if (rule.unit === 'week') {
            scheduleText = 'Repeats every ' + rule.interval + (rule.interval === 1 ? ' week' : ' weeks')
                + ' on ' + rule.week_days.map(function (day) { return WEEKDAY_NAMES[day]; }).join(', ');
        } else {
            scheduleText = 'Repeats every ' + rule.interval + (rule.interval === 1 ? ' month' : ' months')
                + ' on day ' + rule.month_day;
        }

        if (rule.ends.type === 'date') {
            return scheduleText + ' · Until ' + formatDate(keyToDate(rule.ends.date));
        }

        if (rule.ends.type === 'count') {
            return scheduleText + ' · ' + rule.ends.count + (rule.ends.count === 1 ? ' repeat' : ' repeats');
        }

        return scheduleText + ' · Endlessly';
    }

    function close(restoreFocus) {
        if (!repeatModal) {
            return;
        }

        repeatModal.hidden = true;
        setMessage('');

        if (setTaskRepeatButton) {
            setTaskRepeatButton.setAttribute('aria-expanded', 'false');
        }

        if (restoreFocus !== false && lastTrigger && typeof lastTrigger.focus === 'function') {
            lastTrigger.focus();
        }
    }

    function open(trigger) {
        if (!repeatModal) {
            return;
        }

        lastTrigger = trigger || document.activeElement;
        populateForm(cloneRule(appliedRule) || createDefaultRule());
        setMessage('');
        repeatModal.hidden = false;

        if (setTaskRepeatButton) {
            setTaskRepeatButton.setAttribute('aria-expanded', 'true');
        }

        window.requestAnimationFrame(function () {
            var selectedFrequency = frequencyInputs.find(function (input) { return input.checked; });

            if (selectedFrequency) {
                selectedFrequency.focus();
            }
        });
    }

    frequencyInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            setMessage('');
            updateConditionalSections();
        });
    });

    endInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            setMessage('');
            updateConditionalSections();
        });
    });

    if (repeatUnit) {
        repeatUnit.addEventListener('change', function () {
            setMessage('');
            updateConditionalSections();
        });
    }

    if (previousRepeatMonth) {
        previousRepeatMonth.addEventListener('click', function () {
            calendarDate = new Date(calendarDate.getFullYear(), calendarDate.getMonth() - 1, 1, 12);
            renderCalendar();
        });
    }

    if (nextRepeatMonth) {
        nextRepeatMonth.addEventListener('click', function () {
            calendarDate = new Date(calendarDate.getFullYear(), calendarDate.getMonth() + 1, 1, 12);
            renderCalendar();
        });
    }

    if (setTaskRepeatButton) {
        setTaskRepeatButton.addEventListener('click', function () {
            open(setTaskRepeatButton);
        });
    }

    if (closeRepeatModalButton) {
        closeRepeatModalButton.addEventListener('click', function () {
            close();
        });
    }

    if (cancelRepeatButton) {
        cancelRepeatButton.addEventListener('click', function () {
            close();
        });
    }

    if (repeatModal) {
        repeatModal.addEventListener('click', function (event) {
            if (event.target === repeatModal) {
                close();
            }
        });
    }

    if (applyRepeatButton) {
        applyRepeatButton.addEventListener('click', function () {
            var rule = collectRule();
            var validationMessage = validateRule(rule);

            if (validationMessage) {
                setMessage(validationMessage);
                return;
            }

            appliedRule = cloneRule(rule);

            if (taskRepeat) {
                taskRepeat.value = JSON.stringify(appliedRule);
            }

            if (taskRepeatSummary) {
                taskRepeatSummary.textContent = formatRuleSummary(appliedRule);
            }

            if (setTaskRepeatButton) {
                setTaskRepeatButton.classList.add('has-repeat');
            }

            close();
        });
    }

    return {
        close: close,
        isOpen: function () {
            return Boolean(repeatModal && !repeatModal.hidden);
        },
        validate: function () {
            return appliedRule ? validateRule(appliedRule) : '';
        }
    };
}
