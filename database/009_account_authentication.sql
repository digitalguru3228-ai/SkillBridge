-- SKILLBRIDGE Migration 009: Real Account & Authentication System
USE industry_hub;

ALTER TABLE users
  MODIFY COLUMN role ENUM('industry','institute','student','admin') NOT NULL,
  MODIFY COLUMN password_hash VARCHAR(255) NULL;

SET @db := DATABASE();

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='auth_provider') = 0,
  'ALTER TABLE users ADD COLUMN auth_provider ENUM(''password'',''google'') NOT NULL DEFAULT ''password'' AFTER password_hash',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='google_sub') = 0,
  'ALTER TABLE users ADD COLUMN google_sub VARCHAR(255) NULL AFTER auth_provider',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='avatar_url') = 0,
  'ALTER TABLE users ADD COLUMN avatar_url VARCHAR(500) NULL AFTER google_sub',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='email_verified_at') = 0,
  'ALTER TABLE users ADD COLUMN email_verified_at TIMESTAMP NULL AFTER avatar_url',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND INDEX_NAME='uk_users_google_sub') = 0,
  'ALTER TABLE users ADD UNIQUE KEY uk_users_google_sub (google_sub)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND INDEX_NAME='idx_users_email_status') = 0,
  'ALTER TABLE users ADD INDEX idx_users_email_status (email, status)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
