-- SKILLBRIDGE Migration 007: Final SIH Integration Hardening
-- Safe/idempotent migration. Run after 001-006.
USE industry_hub;

SET @db := DATABASE();

-- Faculty applications need institute ownership for institute-side isolation.
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA=@db AND TABLE_NAME='faculty_applications' AND COLUMN_NAME='institute_id') = 0,
  'ALTER TABLE faculty_applications ADD COLUMN institute_id INT UNSIGNED NULL AFTER opportunity_id',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Backfill institute_id from the faculty profile relationship.
UPDATE faculty_applications fa
JOIN faculty_profiles fp ON fp.id = fa.faculty_id
SET fa.institute_id = fp.institute_id
WHERE fa.institute_id IS NULL;

-- Useful indexes for portal-side filtering.
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA=@db AND TABLE_NAME='faculty_applications' AND INDEX_NAME='idx_fa_institute') = 0,
  'ALTER TABLE faculty_applications ADD INDEX idx_fa_institute (institute_id)',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Prevent duplicate applications for the same faculty member and opportunity
-- only when the existing table has no duplicates. If duplicates already exist,
-- leave the schema untouched so this migration never destroys application data.
SET @dupes := (
  SELECT COUNT(*) FROM (
    SELECT faculty_id, opportunity_id
    FROM faculty_applications
    GROUP BY faculty_id, opportunity_id
    HAVING COUNT(*) > 1
  ) d
);
SET @sql := IF(
  @dupes = 0 AND
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA=@db AND TABLE_NAME='faculty_applications' AND INDEX_NAME='uk_fa_faculty_opportunity') = 0,
  'ALTER TABLE faculty_applications ADD UNIQUE KEY uk_fa_faculty_opportunity (faculty_id, opportunity_id)',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
