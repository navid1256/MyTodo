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

initTheme();
initNavigation();
initProfileMenu();
initAvatarPicker();
initTaskCalendar();
initPasswordChange();
initNotificationCenter();
initTaskCompletion();

const dateTimePicker = initDateTimePicker();
const reminderPicker = initReminderPicker();
const repeatPicker = initRepeatPicker();
initTaskModal(dateTimePicker, reminderPicker, repeatPicker);
