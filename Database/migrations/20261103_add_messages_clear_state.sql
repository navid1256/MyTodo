-- Messages visibility is independent of the sent archive and operational delivery history.
ALTER TABLE task_reminders
    ADD COLUMN messages_cleared_at datetime DEFAULT NULL AFTER cleared_at;
