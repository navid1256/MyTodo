ALTER TABLE tasks
    ADD COLUMN IF NOT EXISTS due_at DATETIME NULL AFTER is_done;

CREATE INDEX IF NOT EXISTS idx_tasks_user_due_at
    ON tasks (user_id, due_at);
