export const PRESET_VALUES = {
    'on-due-time': { value: 0, unit: 'minutes' },
    '30-minutes': { value: 30, unit: 'minutes' },
    '1-hour': { value: 1, unit: 'hours' },
    '12-hours': { value: 12, unit: 'hours' },
    '24-hours': { value: 24, unit: 'hours' }
};

export const DEFAULT_REMINDERS = [
    { preset: '30-minutes', customValue: 30, customUnit: 'minutes' },
    { preset: '1-hour', customValue: 1, customUnit: 'hours' },
    { preset: '12-hours', customValue: 12, customUnit: 'hours' },
    { preset: '24-hours', customValue: 24, customUnit: 'hours' },
    { preset: 'custom', customValue: 2, customUnit: 'days' }
];

export function cloneReminder(reminder) {
    return {
        preset: reminder.preset,
        customValue: reminder.customValue,
        customUnit: reminder.customUnit
    };
}

export function createDefaultReminder(index) {
    return cloneReminder(DEFAULT_REMINDERS[index] || DEFAULT_REMINDERS[0]);
}

export function getReminderPayload(reminder) {
    if (reminder.preset !== 'custom') {
        return {
            value: PRESET_VALUES[reminder.preset].value,
            unit: PRESET_VALUES[reminder.preset].unit
        };
    }

    return {
        value: Number(reminder.customValue),
        unit: reminder.customUnit
    };
}