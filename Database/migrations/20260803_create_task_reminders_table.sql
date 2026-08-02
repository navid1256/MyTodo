CREATE TABLE IF NOT EXISTS `task_reminders` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` int(10) UNSIGNED NOT NULL,
  `offset_value` int(10) UNSIGNED NOT NULL,
  `offset_unit` enum('minute','hour','day') NOT NULL,
  `remind_at` datetime NOT NULL,
  `status` enum('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
  `attempt_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `last_attempt_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `task_reminders_task_time_unique` (`task_id`, `remind_at`),
  KEY `idx_task_reminders_delivery` (`status`, `remind_at`),
  CONSTRAINT `task_reminders_task_id_foreign`
    FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
