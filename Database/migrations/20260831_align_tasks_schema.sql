-- Upgrade an existing MyTodo database to the current tasks schema.
-- This migration intentionally fails if tasks contain an unknown user_id.

ALTER TABLE `tasks`
  MODIFY COLUMN `title` varchar(512)
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci
    NOT NULL,
  ADD CONSTRAINT `tasks_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE;
