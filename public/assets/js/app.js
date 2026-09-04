import '@fontsource/vazirmatn/arabic-400.css';
import '@fontsource/vazirmatn/arabic-600.css';

import { initTheme } from './modules/theme.js';
import { initNavigation } from './modules/navigation.js';
import { initProfileMenu } from './modules/profile-menu.js';
import { initAvatarPicker } from './modules/avatar/avatar-picker.js';
import { initTaskModal } from './modules/task-modal.js';
import { initDateTimePicker } from './modules/date-time/date-time-picker.js';
import { initReminderPicker } from './modules/reminder/reminder-picker.js';
import { initRepeatPicker } from './modules/repeat/repeat-picker.js';
import { initTaskCalendar } from './modules/task-calendar.js';
import { initPasswordChange } from './modules/password-change.js';
import { initNotificationCenter } from './modules/notification-center.js';
import { initTaskCompletion } from './modules/task-completion.js';
import { initHomeDayRefresh } from './modules/home-day-refresh.js';
import { initActivityFilter } from './modules/activity-filter.js';
import { initAccountSettings } from './modules/account-settings.js';
import { initProfileBirthDate } from './modules/profile-birth-date.js';

let dashboardViewController = null;

function initDashboardView() {
    if (dashboardViewController) {
        dashboardViewController.abort();
    }

    dashboardViewController = new AbortController();
    const signal = dashboardViewController.signal;

    initTaskCalendar(signal);
    initNotificationCenter(signal);
    initTaskCompletion(signal);
    initHomeDayRefresh(signal);
    initActivityFilter(signal);
    initAccountSettings(signal);
    initProfileBirthDate(signal);

    const dateTimePicker = initDateTimePicker();
    const reminderPicker = initReminderPicker(signal);
    const repeatPicker = initRepeatPicker();
    initTaskModal(dateTimePicker, reminderPicker, repeatPicker);
}

initTheme();
initProfileMenu();
initAvatarPicker();
initPasswordChange();
initNavigation({ onViewLoaded: initDashboardView });
initDashboardView();
