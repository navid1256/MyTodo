CREATE TABLE `push_subscriptions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `endpoint` varchar(2048) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `endpoint_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `p256dh` varchar(87) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `auth` varchar(22) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `content_encoding` varchar(16) NOT NULL DEFAULT 'aes128gcm',
  `device_label` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `push_subscriptions_endpoint_unique` (`endpoint_hash`),
  KEY `push_subscriptions_user_index` (`user_id`),
  CONSTRAINT `push_subscriptions_user_foreign` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
