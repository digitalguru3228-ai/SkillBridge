-- SKILLBRIDGE Migration 003: Production-Ready Platform Upgrade (SIH 2026 PS-44)

USE industry_hub;

-- 1. Practice Assessments tables
CREATE TABLE IF NOT EXISTS practice_assessments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  topic VARCHAR(100) NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  time_limit_mins INT UNSIGNED DEFAULT 15,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS practice_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT UNSIGNED NOT NULL,
  topic VARCHAR(100) NOT NULL,
  total_questions INT UNSIGNED DEFAULT 5,
  correct_answers INT UNSIGNED DEFAULT 0,
  score_pct DECIMAL(5,2) DEFAULT 0.00,
  status ENUM('In Progress','Completed') NOT NULL DEFAULT 'In Progress',
  started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL,
  INDEX idx_pa_candidate (candidate_id),
  CONSTRAINT fk_pa_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS practice_responses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  selected_option ENUM('A','B','C','D') NULL,
  is_correct TINYINT(1) DEFAULT 0,
  answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pr_attempt (attempt_id),
  CONSTRAINT fk_pr_attempt FOREIGN KEY (attempt_id) REFERENCES practice_attempts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Student Projects, Certificates, Achievements
CREATE TABLE IF NOT EXISTS student_projects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT UNSIGNED NOT NULL,
  project_name VARCHAR(180) NOT NULL,
  description TEXT NULL,
  tech_stack VARCHAR(255) NULL,
  github_url VARCHAR(255) NULL,
  live_url VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sproj_candidate (candidate_id),
  CONSTRAINT fk_sproj_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_certificates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT UNSIGNED NOT NULL,
  certificate_name VARCHAR(180) NOT NULL,
  issuing_org VARCHAR(180) NULL,
  issue_date DATE NULL,
  credential_id VARCHAR(100) NULL,
  certificate_url VARCHAR(255) NULL,
  file_path VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_scert_candidate (candidate_id),
  CONSTRAINT fk_scert_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_achievements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  category VARCHAR(100) NULL,
  description TEXT NULL,
  achievement_date DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sach_candidate (candidate_id),
  CONSTRAINT fk_sach_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Learning Modules and Progress Tracking
CREATE TABLE IF NOT EXISTS learning_program_modules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  program_id INT UNSIGNED NOT NULL,
  module_title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  order_num INT UNSIGNED DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lpm_program (program_id),
  CONSTRAINT fk_lpm_program FOREIGN KEY (program_id) REFERENCES learning_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS learning_progress (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  enrollment_id INT UNSIGNED NOT NULL,
  module_id INT UNSIGNED NOT NULL,
  status ENUM('Pending','Completed') NOT NULL DEFAULT 'Pending',
  completed_at TIMESTAMP NULL,
  INDEX idx_lpr_enrollment (enrollment_id),
  CONSTRAINT fk_lpr_enrollment FOREIGN KEY (enrollment_id) REFERENCES learning_enrollments(id) ON DELETE CASCADE,
  CONSTRAINT fk_lpr_module FOREIGN KEY (module_id) REFERENCES learning_program_modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Faculty Profiles and Opportunities
CREATE TABLE IF NOT EXISTS faculty_profiles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institute_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  department VARCHAR(150) NULL,
  designation VARCHAR(120) NULL,
  specialization VARCHAR(255) NULL,
  experience_years DECIMAL(3,1) DEFAULT 0,
  bio TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fp_institute (institute_id),
  CONSTRAINT fk_fp_institute FOREIGN KEY (institute_id) REFERENCES institutes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS faculty_opportunities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  type ENUM('Faculty Internship','FDP','Workshop','Mentorship','Guest Lecture','Consultancy','Research Collaboration') NOT NULL DEFAULT 'Faculty Internship',
  required_skills TEXT NULL,
  location VARCHAR(150) NULL,
  duration VARCHAR(100) NULL,
  description TEXT NULL,
  status ENUM('Draft','Active','Closed') NOT NULL DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fo_company (company_id),
  CONSTRAINT fk_fo_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS faculty_applications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  faculty_id INT UNSIGNED NOT NULL,
  opportunity_id INT UNSIGNED NOT NULL,
  status ENUM('Applied','Shortlisted','Selected','Rejected') NOT NULL DEFAULT 'Applied',
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fa_faculty (faculty_id),
  INDEX idx_fa_opp (opportunity_id),
  CONSTRAINT fk_fa_faculty FOREIGN KEY (faculty_id) REFERENCES faculty_profiles(id) ON DELETE CASCADE,
  CONSTRAINT fk_fa_opp FOREIGN KEY (opportunity_id) REFERENCES faculty_opportunities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Industry Resources & Acknowledgements
CREATE TABLE IF NOT EXISTS industry_resources (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  topic VARCHAR(100) NOT NULL,
  audience ENUM('Faculty','Institute','Both') NOT NULL DEFAULT 'Both',
  description TEXT NULL,
  resource_url VARCHAR(255) NULL,
  file_path VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ir_company (company_id),
  CONSTRAINT fk_ir_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS resource_acknowledgements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resource_id INT UNSIGNED NOT NULL,
  institute_id INT UNSIGNED NOT NULL,
  acknowledged_by_user_id INT UNSIGNED NULL,
  acknowledged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ra_resource (resource_id),
  INDEX idx_ra_institute (institute_id),
  CONSTRAINT fk_ra_resource FOREIGN KEY (resource_id) REFERENCES industry_resources(id) ON DELETE CASCADE,
  CONSTRAINT fk_ra_institute FOREIGN KEY (institute_id) REFERENCES institutes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Student Suggestions & Improvement Plans
CREATE TABLE IF NOT EXISTS student_suggestions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT UNSIGNED NOT NULL,
  institute_id INT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  suggestion_text TEXT NOT NULL,
  priority ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
  recommended_action TEXT NULL,
  target_score DECIMAL(5,2) DEFAULT 80.00,
  current_score DECIMAL(5,2) DEFAULT 50.00,
  status ENUM('Suggested','Acknowledged','In Progress','Completed','Dismissed') NOT NULL DEFAULT 'Suggested',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ss_candidate (candidate_id),
  INDEX idx_ss_institute (institute_id),
  CONSTRAINT fk_ss_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
  CONSTRAINT fk_ss_institute FOREIGN KEY (institute_id) REFERENCES institutes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Collaboration Feedback
CREATE TABLE IF NOT EXISTS collaboration_feedback (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  collaboration_id INT UNSIGNED NOT NULL,
  submitter_type ENUM('Industry','Institute') NOT NULL,
  rating TINYINT UNSIGNED DEFAULT 5,
  communication_score TINYINT UNSIGNED DEFAULT 5,
  technical_quality TINYINT UNSIGNED DEFAULT 5,
  outcome_notes TEXT NULL,
  feedback_text TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cf_collab (collaboration_id),
  CONSTRAINT fk_cf_collab FOREIGN KEY (collaboration_id) REFERENCES collaboration_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Seed Default Learning Program Modules for existing programs
INSERT INTO learning_program_modules (program_id, module_title, description, order_num)
SELECT id, 'Module 1: Fundamentals & Core Architecture', 'Introduction to core concepts and foundational tools.', 1 FROM learning_programs WHERE title = 'Full Stack Web Development Intensive' AND NOT EXISTS (SELECT 1 FROM learning_program_modules WHERE program_id = learning_programs.id);

INSERT INTO learning_program_modules (program_id, module_title, description, order_num)
SELECT id, 'Module 2: Advanced Development & REST APIs', 'Building robust services and integrating database APIs.', 2 FROM learning_programs WHERE title = 'Full Stack Web Development Intensive' AND NOT EXISTS (SELECT 1 FROM learning_program_modules WHERE program_id = learning_programs.id AND order_num = 2);

INSERT INTO learning_program_modules (program_id, module_title, description, order_num)
SELECT id, 'Module 3: Project Deployment & Performance', 'Deploying scalable services and code optimization.', 3 FROM learning_programs WHERE title = 'Full Stack Web Development Intensive' AND NOT EXISTS (SELECT 1 FROM learning_program_modules WHERE program_id = learning_programs.id AND order_num = 3);

-- 9. Seed Sample Industry Resources
INSERT INTO industry_resources (company_id, title, topic, audience, description)
SELECT 1, 'Industry AI & Machine Learning Curriculum Guide 2026', 'AI/ML', 'Both', 'Recommended syllabus topics and practical projects faculty should integrate into computer science curriculum for industry alignment.'
WHERE NOT EXISTS (SELECT 1 FROM industry_resources WHERE title = 'Industry AI & Machine Learning Curriculum Guide 2026');

INSERT INTO industry_resources (company_id, title, topic, audience, description)
SELECT 1, 'Top 50 Technical Interview Questions: Full Stack & Systems', 'Interview Prep', 'Both', 'Curated question bank used by recruiters during technical rounds for entry-level engineering roles.'
WHERE NOT EXISTS (SELECT 1 FROM industry_resources WHERE title = 'Top 50 Technical Interview Questions: Full Stack & Systems');

-- 10. Seed Sample Faculty Opportunities
INSERT INTO faculty_opportunities (company_id, title, type, required_skills, location, duration, description, status)
SELECT 1, 'Summer Industrial Training & Faculty Fellowship', 'Faculty Internship', 'Python, Cloud Architecture, AI', 'Ahmedabad / Hybrid', '4 Weeks', 'Hands-on industrial fellowship for engineering faculty to work alongside lead architects on enterprise cloud solutions.', 'Active'
WHERE NOT EXISTS (SELECT 1 FROM faculty_opportunities WHERE title = 'Summer Industrial Training & Faculty Fellowship');

