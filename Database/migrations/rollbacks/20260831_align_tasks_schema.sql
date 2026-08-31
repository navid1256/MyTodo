-- Roll back Database/migrations/20260831_align_tasks_schema.sql.
-- Run this only when task titles contain no utf8mb4-only characters because
-- converting the title column back to utf8 cannot preserve four-byte Unicode.

ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

ALTER TABLE `tasks`
  DROP FOREIGN KEY `tasks_user_id_foreign`,
  MODIFY COLUMN `title` varchar(512)
    CHARACTER SET utf8
    COLLATE utf8_general_ci
    NOT NULL;
