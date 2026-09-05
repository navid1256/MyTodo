ALTER TABLE `tasks`
  DROP FOREIGN KEY `tasks_repeat_rule_id_foreign`,
  DROP INDEX `tasks_repeat_occurrence_unique`,
  DROP COLUMN `repeat_occurrence_number`,
  DROP COLUMN `repeat_rule_id`;

DROP TABLE IF EXISTS `task_repeat_reminder_templates`;
DROP TABLE IF EXISTS `task_repeat_rules`;
