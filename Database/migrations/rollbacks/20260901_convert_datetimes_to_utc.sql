UPDATE `users`
SET `created_at` = DATE_ADD(`created_at`, INTERVAL 210 MINUTE),
    `updated_at` = DATE_ADD(`updated_at`, INTERVAL 210 MINUTE);

UPDATE `tasks`
SET `due_at` = IF(`due_at` IS NULL, NULL, DATE_ADD(`due_at`, INTERVAL 210 MINUTE)),
    `completed_at` = IF(`completed_at` IS NULL, NULL, DATE_ADD(`completed_at`, INTERVAL 210 MINUTE)),
    `created_at` = DATE_ADD(`created_at`, INTERVAL 210 MINUTE),
    `updated_at` = DATE_ADD(`updated_at`, INTERVAL 210 MINUTE);

UPDATE `task_reminders`
SET `remind_at` = DATE_ADD(`remind_at`, INTERVAL 210 MINUTE),
    `last_attempt_at` = IF(`last_attempt_at` IS NULL, NULL, DATE_ADD(`last_attempt_at`, INTERVAL 210 MINUTE)),
    `sent_at` = IF(`sent_at` IS NULL, NULL, DATE_ADD(`sent_at`, INTERVAL 210 MINUTE)),
    `created_at` = DATE_ADD(`created_at`, INTERVAL 210 MINUTE),
    `updated_at` = DATE_ADD(`updated_at`, INTERVAL 210 MINUTE);

UPDATE `user_settings`
SET `created_at` = DATE_ADD(`created_at`, INTERVAL 210 MINUTE),
    `updated_at` = DATE_ADD(`updated_at`, INTERVAL 210 MINUTE);
