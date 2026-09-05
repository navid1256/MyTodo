CREATE TABLE `task_repeat_rules` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_at` datetime NOT NULL,
  `has_time` tinyint(1) NOT NULL DEFAULT 0,
  `timezone` varchar(100) NOT NULL,
  `frequency` enum('daily','weekly','monthly','custom') NOT NULL,
  `interval_value` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `interval_unit` enum('day','week','month') NOT NULL,
  `week_days` varchar(32) DEFAULT NULL,
  `month_day` tinyint(3) UNSIGNED DEFAULT NULL,
  `month_day_mode` enum('clamp','last_day') NOT NULL DEFAULT 'clamp',
  `end_type` enum('endlessly','date','count') NOT NULL DEFAULT 'endlessly',
  `end_date` date DEFAULT NULL,
  `repeat_count` smallint(5) UNSIGNED DEFAULT NULL,
  `generated_repeats` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `next_occurrence_at` datetime DEFAULT NULL,
  `status` enum('active','paused','cancelled','completed') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_repeat_rules_due` (`status`, `next_occurrence_at`),
  KEY `idx_repeat_rules_user` (`user_id`, `status`),
  CONSTRAINT `task_repeat_rules_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_repeat_reminder_templates` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `repeat_rule_id` int(10) UNSIGNED NOT NULL,
  `offset_value` int(10) UNSIGNED NOT NULL,
  `offset_unit` enum('minute','hour','day') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `repeat_reminder_template_unique` (`repeat_rule_id`, `offset_value`, `offset_unit`),
  CONSTRAINT `repeat_reminder_templates_rule_foreign`
    FOREIGN KEY (`repeat_rule_id`) REFERENCES `task_repeat_rules` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `tasks`
  ADD COLUMN `repeat_rule_id` int(10) UNSIGNED DEFAULT NULL AFTER `user_id`,
  ADD COLUMN `repeat_occurrence_number` smallint(5) UNSIGNED DEFAULT NULL AFTER `repeat_rule_id`,
  ADD UNIQUE KEY `tasks_repeat_occurrence_unique` (`repeat_rule_id`, `repeat_occurrence_number`),
  ADD CONSTRAINT `tasks_repeat_rule_id_foreign`
    FOREIGN KEY (`repeat_rule_id`) REFERENCES `task_repeat_rules` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;
