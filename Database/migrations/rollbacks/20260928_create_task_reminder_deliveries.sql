-- Intentionally discards per-browser delivery history; run only when reverting this migration.
DROP TABLE IF EXISTS `task_reminder_deliveries`;
