ALTER TABLE `users_info`
    ADD COLUMN IF NOT EXISTS `country` varchar(100) DEFAULT NULL AFTER `gender`;
