-- SKILLBRIDGE Migration 006: Production Hardening & Verified Skill Architecture (SIH 2026 PS-44)
USE industry_hub;

SET @dbname = DATABASE();

-- 1. Upgrade candidate_skills table with verified scoring and audit columns
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'candidate_skills' AND COLUMN_NAME = 'score') > 0,
  "SELECT 1",
  "ALTER TABLE candidate_skills ADD COLUMN score DECIMAL(5,2) DEFAULT 0.00 AFTER proficiency;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'candidate_skills' AND COLUMN_NAME = 'verified') > 0,
  "SELECT 1",
  "ALTER TABLE candidate_skills ADD COLUMN verified TINYINT(1) DEFAULT 0 AFTER score;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'candidate_skills' AND COLUMN_NAME = 'source') > 0,
  "SELECT 1",
  "ALTER TABLE candidate_skills ADD COLUMN source ENUM('Self','Assessment','Course','Industry') DEFAULT 'Self' AFTER verified;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'candidate_skills' AND COLUMN_NAME = 'last_assessed_at') > 0,
  "SELECT 1",
  "ALTER TABLE candidate_skills ADD COLUMN last_assessed_at TIMESTAMP NULL AFTER source;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'candidate_skills' AND COLUMN_NAME = 'updated_at') > 0,
  "SELECT 1",
  "ALTER TABLE candidate_skills ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER last_assessed_at;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add Unique constraint to candidate_skills to support clean atomic UPSERT
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'candidate_skills' AND INDEX_NAME = 'uk_candidate_skill') > 0,
  "SELECT 1",
  "ALTER TABLE candidate_skills ADD UNIQUE KEY uk_candidate_skill (candidate_id, skill_id);"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Upgrade faculty_applications table to support notes and tracking
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'faculty_applications' AND COLUMN_NAME = 'notes') > 0,
  "SELECT 1",
  "ALTER TABLE faculty_applications ADD COLUMN notes TEXT NULL AFTER status;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'faculty_applications' AND COLUMN_NAME = 'institute_id') > 0,
  "SELECT 1",
  "ALTER TABLE faculty_applications ADD COLUMN institute_id INT UNSIGNED NULL AFTER opportunity_id;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Ensure student profile for Rahul Patel (candidate_id=1, user_id=5) exists and is rich
INSERT INTO student_profiles (candidate_id, user_id, degree, branch, semester, cgpa, graduation_year, career_interest, preferred_role, preferred_industry, bio, linkedin_url, github_url, portfolio_url)
SELECT 1, 5, 'B.Tech IT', 'Information Technology', 7, 8.70, 2026, 'Cloud Architecture & Full-Stack Development', 'Python Developer / Cloud Architect', 'Enterprise Tech & SaaS', 'Passionate computer science student specializing in backend architecture, distributed systems, and modern web application development.', 'https://linkedin.com/in/rahul-patel-demo', 'https://github.com/rahul-patel-demo', 'https://rahulpatel.dev'
WHERE NOT EXISTS (SELECT 1 FROM student_profiles WHERE candidate_id = 1);

-- 4. Seed initial faculty profile for Institute 1 if none exists
INSERT INTO faculty_profiles (id, institute_id, name, email, department, designation, specialization, experience_years, bio)
SELECT 1, 1, 'Dr. Rajesh Sharma', 'rajesh.sharma@vgec.example', 'Computer Engineering', 'Associate Professor', 'Cloud Computing & Artificial Intelligence', 12.5, 'Head of Cloud Computing Lab and Faculty Coordinator for Industry-Academia Collaborative Projects.'
WHERE NOT EXISTS (SELECT 1 FROM faculty_profiles WHERE institute_id = 1);

-- 5. Backfill candidate_skills scores for candidate 1 based on existing proficiency
UPDATE candidate_skills 
SET score = CASE 
    WHEN proficiency = 'Expert' THEN 95.00
    WHEN proficiency = 'Advanced' THEN 85.00
    WHEN proficiency = 'Intermediate' THEN 70.00
    WHEN proficiency = 'Beginner' THEN 50.00
    ELSE 65.00
END,
verified = 1,
source = 'Assessment',
last_assessed_at = CURRENT_TIMESTAMP
WHERE candidate_id = 1 AND (score IS NULL OR score = 0.00);

