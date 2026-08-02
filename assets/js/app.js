import { initTheme } from './modules/theme.js';
import { initNavigation } from './modules/navigation.js';
import { initProfileMenu } from './modules/profile-menu.js';
import { initTaskModal } from './modules/task-modal.js';
import { initDateTimePicker } from './modules/date-time-picker.js';
import { initReminderPicker } from './modules/reminder-picker.js';

initTheme();
initNavigation();
initProfileMenu();

const dateTimePicker = initDateTimePicker();
const reminderPicker = initReminderPicker();
initTaskModal(dateTimePicker, reminderPicker);
