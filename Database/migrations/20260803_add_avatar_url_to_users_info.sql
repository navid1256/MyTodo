ALTER TABLE `users_info`
  ADD COLUMN IF NOT EXISTS `avatar_url` varchar(512) DEFAULT NULL AFTER `country`;
