-- Restores Messages visibility only; Tasks, Reminders, and delivery outcomes remain intact.
ALTER TABLE task_reminders
    DROP COLUMN messages_cleared_at;
