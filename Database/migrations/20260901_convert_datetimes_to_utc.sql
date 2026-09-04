-- Legacy MyTodo DATETIME values were stored in Asia/Tehran local time.
-- Iran uses UTC+03:30 for every date currently stored by this application.

UPDATE `users`
SET `created_at` = DATE_SUB(`created_at`, INTERVAL 210 MINUTE),
    `updated_at` = DATE_SUB(`updated_at`, INTERVAL 210 MINUTE);

UPDATE `tasks`
SET `due_at` = IF(`due_at` IS NULL, NULL, DATE_SUB(`due_at`, INTERVAL 210 MINUTE)),
    `completed_at` = IF(`completed_at` IS NULL, NULL, DATE_SUB(`completed_at`, INTERVAL 210 MINUTE)),
    `created_at` = DATE_SUB(`created_at`, INTERVAL 210 MINUTE),
    `updated_at` = DATE_SUB(`updated_at`, INTERVAL 210 MINUTE);

UPDATE `task_reminders`
SET `remind_at` = DATE_SUB(`remind_at`, INTERVAL 210 MINUTE),
    `last_attempt_at` = IF(`last_attempt_at` IS NULL, NULL, DATE_SUB(`last_attempt_at`, INTERVAL 210 MINUTE)),
    `sent_at` = IF(`sent_at` IS NULL, NULL, DATE_SUB(`sent_at`, INTERVAL 210 MINUTE)),
    `created_at` = DATE_SUB(`created_at`, INTERVAL 210 MINUTE),
    `updated_at` = DATE_SUB(`updated_at`, INTERVAL 210 MINUTE);

UPDATE `user_settings`
SET `created_at` = DATE_SUB(`created_at`, INTERVAL 210 MINUTE),
    `updated_at` = DATE_SUB(`updated_at`, INTERVAL 210 MINUTE);
