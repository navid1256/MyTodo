-- Archive-only metadata: never delete reminder or browser delivery history when clearing notifications.
ALTER TABLE task_reminders
    ADD COLUMN read_at datetime DEFAULT NULL AFTER sent_at,
    ADD COLUMN cleared_at datetime DEFAULT NULL AFTER read_at,
    ADD KEY idx_task_reminders_archive (status, cleared_at, sent_at, id);
