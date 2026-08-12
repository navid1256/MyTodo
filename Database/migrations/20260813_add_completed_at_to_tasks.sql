ALTER TABLE `tasks`
    ADD COLUMN `completed_at` DATETIME NULL AFTER `is_done`,
    ADD INDEX `idx_tasks_user_completed_at` (`user_id`, `is_done`, `completed_at`);

UPDATE `tasks`
SET `completed_at` = `updated_at`
WHERE `is_done` = 1
  AND `completed_at` IS NULL;
