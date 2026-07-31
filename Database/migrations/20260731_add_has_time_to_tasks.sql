ALTER TABLE `tasks`
  ADD COLUMN `has_time` tinyint(1) NOT NULL DEFAULT 0 AFTER `due_at`;

UPDATE `tasks`
SET `has_time` = 1
WHERE `due_at` IS NOT NULL;
