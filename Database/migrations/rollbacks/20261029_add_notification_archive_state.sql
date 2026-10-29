-- Reverting discards read/clear metadata and makes cleared sent reminders visible again.
ALTER TABLE task_reminders
    DROP INDEX idx_task_reminders_archive,
    DROP COLUMN cleared_at,
    DROP COLUMN read_at;
