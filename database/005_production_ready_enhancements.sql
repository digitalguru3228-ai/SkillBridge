-- SKILLBRIDGE Migration 005: Production-Ready Enhancements (SIH 2026 PS-44)
USE industry_hub;

-- 1. Upgrade student_profiles table with missing columns
SET @dbname = DATABASE();

-- Function-like dynamic column adder helper
-- Column: degree
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'degree') > 0,
  "SELECT 1",
  "ALTER TABLE student_profiles ADD COLUMN degree VARCHAR(100) NULL AFTER user_id;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Column: branch
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'branch') > 0,
  "SELECT 1",
  "ALTER TABLE student_profiles ADD COLUMN branch VARCHAR(100) NULL AFTER degree;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Column: semester
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'semester') > 0,
  "SELECT 1",
  "ALTER TABLE student_profiles ADD COLUMN semester INT UNSIGNED DEFAULT 7 AFTER branch;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Column: cgpa
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'cgpa') > 0,
  "SELECT 1",
  "ALTER TABLE student_profiles ADD COLUMN cgpa DECIMAL(4,2) DEFAULT 8.00 AFTER semester;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Column: graduation_year
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'graduation_year') > 0,
  "SELECT 1",
  "ALTER TABLE student_profiles ADD COLUMN graduation_year INT UNSIGNED DEFAULT 2026 AFTER cgpa;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Column: preferred_role
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'preferred_role') > 0,
  "SELECT 1",
  "ALTER TABLE student_profiles ADD COLUMN preferred_role VARCHAR(150) NULL AFTER career_interest;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Column: preferred_industry
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'preferred_industry') > 0,
  "SELECT 1",
  "ALTER TABLE student_profiles ADD COLUMN preferred_industry VARCHAR(150) NULL AFTER preferred_role;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Column: linkedin_url
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'linkedin_url') > 0,
  "SELECT 1",
  "ALTER TABLE student_profiles ADD COLUMN linkedin_url VARCHAR(255) NULL;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Column: github_url
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'github_url') > 0,
  "SELECT 1",
  "ALTER TABLE student_profiles ADD COLUMN github_url VARCHAR(255) NULL;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Column: portfolio_url
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'portfolio_url') > 0,
  "SELECT 1",
  "ALTER TABLE student_profiles ADD COLUMN portfolio_url VARCHAR(255) NULL;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Column: website_url
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'website_url') > 0,
  "SELECT 1",
  "ALTER TABLE student_profiles ADD COLUMN website_url VARCHAR(255) NULL;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- 2. Upgrade opportunity_skills table with min_score_required
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'opportunity_skills' AND COLUMN_NAME = 'min_score_required') > 0,
  "SELECT 1",
  "ALTER TABLE opportunity_skills ADD COLUMN min_score_required INT UNSIGNED DEFAULT 70 AFTER importance;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- 3. Upgrade opportunities table with structured hiring criteria & assessment link
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'opportunities' AND COLUMN_NAME = 'min_cgpa') > 0,
  "SELECT 1",
  "ALTER TABLE opportunities ADD COLUMN min_cgpa DECIMAL(4,2) DEFAULT 0.00 AFTER qualification;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'opportunities' AND COLUMN_NAME = 'allowed_branches') > 0,
  "SELECT 1",
  "ALTER TABLE opportunities ADD COLUMN allowed_branches TEXT NULL AFTER min_cgpa;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'opportunities' AND COLUMN_NAME = 'work_mode') > 0,
  "SELECT 1",
  "ALTER TABLE opportunities ADD COLUMN work_mode ENUM('In-office','Remote','Hybrid') DEFAULT 'In-office' AFTER location;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'opportunities' AND COLUMN_NAME = 'learning_outcomes') > 0,
  "SELECT 1",
  "ALTER TABLE opportunities ADD COLUMN learning_outcomes TEXT NULL AFTER description;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'opportunities' AND COLUMN_NAME = 'assessment_id') > 0,
  "SELECT 1",
  "ALTER TABLE opportunities ADD COLUMN assessment_id INT UNSIGNED NULL AFTER learning_outcomes;"
));
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- 4. Create Industry Pre-Placement Assessments Tables
CREATE TABLE IF NOT EXISTS industry_assessments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  opportunity_id INT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  duration_mins INT UNSIGNED DEFAULT 30,
  passing_score_pct DECIMAL(5,2) DEFAULT 60.00,
  description TEXT NULL,
  status ENUM('Draft','Active','Closed') NOT NULL DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ia_company (company_id),
  INDEX idx_ia_opp (opportunity_id),
  CONSTRAINT fk_ia_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_ia_opp FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS industry_assessment_questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assessment_id INT UNSIGNED NOT NULL,
  question TEXT NOT NULL,
  option_a TEXT NOT NULL,
  option_b TEXT NOT NULL,
  option_c TEXT NOT NULL,
  option_d TEXT NOT NULL,
  correct_option ENUM('A','B','C','D') NOT NULL,
  marks INT UNSIGNED DEFAULT 1,
  difficulty ENUM('Easy','Medium','Hard') NOT NULL DEFAULT 'Medium',
  skill_topic VARCHAR(100) NULL,
  order_num INT UNSIGNED DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_iaq_assessment (assessment_id),
  CONSTRAINT fk_iaq_assessment FOREIGN KEY (assessment_id) REFERENCES industry_assessments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS industry_assessment_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assessment_id INT UNSIGNED NOT NULL,
  candidate_id INT UNSIGNED NOT NULL,
  application_id INT UNSIGNED NULL,
  total_score DECIMAL(5,2) DEFAULT 0.00,
  max_score DECIMAL(5,2) DEFAULT 100.00,
  percentage DECIMAL(5,2) DEFAULT 0.00,
  is_passed TINYINT(1) DEFAULT 0,
  status ENUM('In Progress','Completed') NOT NULL DEFAULT 'In Progress',
  started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL,
  INDEX idx_iaa_assessment (assessment_id),
  INDEX idx_iaa_candidate (candidate_id),
  INDEX idx_iaa_app (application_id),
  CONSTRAINT fk_iaa_assessment FOREIGN KEY (assessment_id) REFERENCES industry_assessments(id) ON DELETE CASCADE,
  CONSTRAINT fk_iaa_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
  CONSTRAINT fk_iaa_app FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 5. Create Campus Workshops & Webinars Tables
CREATE TABLE IF NOT EXISTS workshops (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  type ENUM('Technical Workshop','Webinar','Guest Lecture','Industry Talk','Masterclass','Faculty Development','Career Session') NOT NULL DEFAULT 'Technical Workshop',
  start_time DATETIME NOT NULL,
  end_time DATETIME NOT NULL,
  venue_or_link VARCHAR(255) NOT NULL,
  max_capacity INT UNSIGNED DEFAULT 100,
  description TEXT NULL,
  target_audience VARCHAR(150) NULL,
  required_skills TEXT NULL,
  target_institute_id INT UNSIGNED NULL,
  registration_deadline DATETIME NULL,
  speaker_name VARCHAR(150) NULL,
  speaker_designation VARCHAR(150) NULL,
  status ENUM('Draft','Published','Completed','Cancelled') NOT NULL DEFAULT 'Published',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ws_company (company_id),
  INDEX idx_ws_inst (target_institute_id),
  CONSTRAINT fk_ws_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_ws_inst FOREIGN KEY (target_institute_id) REFERENCES institutes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS workshop_registrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  workshop_id INT UNSIGNED NOT NULL,
  candidate_id INT UNSIGNED NULL,
  faculty_id INT UNSIGNED NULL,
  user_type ENUM('Student','Faculty') NOT NULL DEFAULT 'Student',
  status ENUM('Registered','Attended','Completed','No Show') NOT NULL DEFAULT 'Registered',
  feedback_rating TINYINT UNSIGNED NULL,
  feedback_text TEXT NULL,
  registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_wr_workshop (workshop_id),
  INDEX idx_wr_candidate (candidate_id),
  INDEX idx_wr_faculty (faculty_id),
  CONSTRAINT fk_wr_workshop FOREIGN KEY (workshop_id) REFERENCES workshops(id) ON DELETE CASCADE,
  CONSTRAINT fk_wr_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
  CONSTRAINT fk_wr_faculty FOREIGN KEY (faculty_id) REFERENCES faculty_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 6. Create Industry Talent Shortlists Table
CREATE TABLE IF NOT EXISTS industry_shortlists (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  candidate_id INT UNSIGNED NOT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_comp_cand (company_id, candidate_id),
  CONSTRAINT fk_ishort_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_ishort_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 7. Seed Initial Pre-Placement Assessment & Workshop Data
INSERT INTO industry_assessments (company_id, opportunity_id, title, duration_mins, passing_score_pct, description, status)
SELECT 1, 1, 'Full-Stack Technical Screening', 30, 70.00, 'Pre-placement assessment evaluating core JavaScript, SQL, and system design competencies.', 'Active'
WHERE NOT EXISTS (SELECT 1 FROM industry_assessments WHERE title = 'Full-Stack Technical Screening');

INSERT INTO industry_assessment_questions (assessment_id, question, option_a, option_b, option_c, option_d, correct_option, marks, difficulty, skill_topic, order_num)
SELECT id, 'What is the primary difference between `let` and `var` scope in JavaScript?', 'let is block scoped, var is function scoped', 'var is block scoped, let is global', 'They are identical', 'let cannot be reassigned', 'A', 2, 'Medium', 'JavaScript', 1
FROM industry_assessments WHERE title = 'Full-Stack Technical Screening' AND NOT EXISTS (SELECT 1 FROM industry_assessment_questions WHERE question LIKE 'What is the primary difference between `let`%');

INSERT INTO industry_assessment_questions (assessment_id, question, option_a, option_b, option_c, option_d, correct_option, marks, difficulty, skill_topic, order_num)
SELECT id, 'Which SQL JOIN returns all rows from table A and only matching rows from table B?', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'CROSS JOIN', 'B', 2, 'Easy', 'SQL', 2
FROM industry_assessments WHERE title = 'Full-Stack Technical Screening' AND NOT EXISTS (SELECT 1 FROM industry_assessment_questions WHERE question LIKE 'Which SQL JOIN returns all rows%');

INSERT INTO workshops (company_id, title, type, start_time, end_time, venue_or_link, max_capacity, description, target_audience, required_skills, speaker_name, speaker_designation, status)
SELECT 1, 'Building Scalable Cloud Native Services', 'Technical Workshop', DATE_ADD(NOW(), INTERVAL 7 DAY), DATE_ADD(NOW(), INTERVAL 170 HOUR), 'https://meet.google.com/sb-tech-ws', 200, 'Hands-on masterclass on containerization, microservices, and database optimization.', 'B.Tech IT/CSE Students & Faculty', 'Python, Docker, SQL', 'Vikram Malhotra', 'Principal Cloud Architect', 'Published'
WHERE NOT EXISTS (SELECT 1 FROM workshops WHERE title = 'Building Scalable Cloud Native Services');

