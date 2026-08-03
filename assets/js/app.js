import { initTheme } from './modules/theme.js';
import { initNavigation } from './modules/navigation.js';
import { initProfileMenu } from './modules/profile-menu.js';
import { initTaskModal } from './modules/task-modal.js';
import { initDateTimePicker } from './modules/date-time-picker.js';
import { initReminderPicker } from './modules/reminder-picker.js';
import { initRepeatPicker } from './modules/repeat-picker.js';
import { initTaskCalendar } from './modules/task-calendar.js';

initTheme();
initNavigation();
initProfileMenu();
initTaskCalendar();

const dateTimePicker = initDateTimePicker();
const reminderPicker = initReminderPicker();
const repeatPicker = initRepeatPicker();
initTaskModal(dateTimePicker, reminderPicker, repeatPicker);
