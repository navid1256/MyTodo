-- One immutable reminder/browser pair prevents retries from reaching browsers that already accepted a push.
CREATE TABLE `task_reminder_deliveries` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `reminder_id` int(10) UNSIGNED NOT NULL,
  `subscription_id` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','leased','retryable','sent','failed','expired','unknown','unavailable') NOT NULL DEFAULT 'pending',
  `attempt_count` smallint UNSIGNED NOT NULL DEFAULT 0,
  `last_attempt_at` datetime DEFAULT NULL,
  `next_attempt_at` datetime DEFAULT NULL,
  `claimed_at` datetime DEFAULT NULL,
  `claim_token` char(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `http_status` smallint UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `task_reminder_deliveries_pair` (`reminder_id`, `subscription_id`),
  KEY `task_reminder_deliveries_ready` (`status`, `next_attempt_at`, `id`),
  KEY `task_reminder_deliveries_subscription` (`subscription_id`),
  CONSTRAINT `task_reminder_deliveries_reminder_foreign` FOREIGN KEY (`reminder_id`)
    REFERENCES `task_reminders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
