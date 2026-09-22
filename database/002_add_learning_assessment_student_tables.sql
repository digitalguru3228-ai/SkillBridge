-- SKILLBRIDGE Migration 002: Learning Programs, Adaptive Assessments, and Student Extensions

USE industry_hub;

-- 1. Modify users role enum to support student role
ALTER TABLE users MODIFY COLUMN role ENUM('industry','institute','student','admin') NOT NULL;

-- 2. Learning Programs table
CREATE TABLE IF NOT EXISTS learning_programs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  type ENUM('Training','Certification','Workshop','Mentorship') NOT NULL DEFAULT 'Training',
  target_skills TEXT NULL,
  duration VARCHAR(100) NULL,
  stipend_or_fee VARCHAR(100) NULL,
  location VARCHAR(150) NULL,
  description TEXT NULL,
  status ENUM('Draft','Active','Closed') NOT NULL DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lp_company (company_id),
  CONSTRAINT fk_lp_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Learning Enrollments table
CREATE TABLE IF NOT EXISTS learning_enrollments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  program_id INT UNSIGNED NOT NULL,
  candidate_id INT UNSIGNED NOT NULL,
  status ENUM('Enrolled','In Progress','Completed') NOT NULL DEFAULT 'Enrolled',
  enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_le_program (program_id),
  INDEX idx_le_candidate (candidate_id),
  CONSTRAINT fk_le_program FOREIGN KEY (program_id) REFERENCES learning_programs(id) ON DELETE CASCADE,
  CONSTRAINT fk_le_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Assessment Questions table
CREATE TABLE IF NOT EXISTS assessment_questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  skill_id INT UNSIGNED NULL,
  topic VARCHAR(100) NOT NULL,
  question TEXT NOT NULL,
  option_a TEXT NOT NULL,
  option_b TEXT NOT NULL,
  option_c TEXT NOT NULL,
  option_d TEXT NOT NULL,
  correct_option ENUM('A','B','C','D') NOT NULL,
  difficulty ENUM('Easy','Medium','Hard') NOT NULL DEFAULT 'Medium',
  explanation TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_aq_skill (skill_id),
  INDEX idx_aq_difficulty (difficulty),
  CONSTRAINT fk_aq_skill FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Assessment Attempts table
CREATE TABLE IF NOT EXISTS assessment_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT UNSIGNED NOT NULL,
  target_role VARCHAR(150) NOT NULL,
  total_score DECIMAL(5,2) DEFAULT 0.00,
  max_score DECIMAL(5,2) DEFAULT 100.00,
  difficulty_reached ENUM('Easy','Medium','Hard') DEFAULT 'Easy',
  status ENUM('In Progress','Completed') NOT NULL DEFAULT 'In Progress',
  started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL,
  INDEX idx_aa_candidate (candidate_id),
  CONSTRAINT fk_aa_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Assessment Responses table
CREATE TABLE IF NOT EXISTS assessment_responses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  selected_option ENUM('A','B','C','D') NULL,
  is_correct TINYINT(1) DEFAULT 0,
  difficulty ENUM('Easy','Medium','Hard') NOT NULL,
  score_earned DECIMAL(5,2) DEFAULT 0.00,
  answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ar_attempt (attempt_id),
  CONSTRAINT fk_ar_attempt FOREIGN KEY (attempt_id) REFERENCES assessment_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_ar_question FOREIGN KEY (question_id) REFERENCES assessment_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Seed Sample Learning Programs
INSERT INTO learning_programs (company_id, title, type, target_skills, duration, stipend_or_fee, location, description, status)
SELECT 1, 'Full Stack Web Development Intensive', 'Training', 'JavaScript, React, Node.js, SQL', '8 Weeks', 'Free for Shortlisted', 'Remote / Hybrid', 'A comprehensive industry boot camp focusing on modern JavaScript, database architecture, and REST API development.', 'Active'
WHERE NOT EXISTS (SELECT 1 FROM learning_programs WHERE title = 'Full Stack Web Development Intensive');

INSERT INTO learning_programs (company_id, title, type, target_skills, duration, stipend_or_fee, location, description, status)
SELECT 1, 'Python Data Engineering Workshop', 'Workshop', 'Python, SQL, Git, Data Analytics', '4 Weeks', 'Sponsored', 'Online Live', 'Hands-on practical training on ETL pipelines, data structures, and database optimization.', 'Active'
WHERE NOT EXISTS (SELECT 1 FROM learning_programs WHERE title = 'Python Data Engineering Workshop');

-- 8. Seed Assessment Questions
INSERT INTO assessment_questions (topic, question, option_a, option_b, option_c, option_d, correct_option, difficulty, explanation) VALUES
('Python', 'What is the output of type([]) in Python?', 'list', 'dict', 'tuple', 'set', 'A', 'Easy', '[] denotes a literal list object in Python.'),
('Python', 'Which built-in function returns an iterator that yields tuples containing indices and values?', 'range()', 'enumerate()', 'zip()', 'map()', 'B', 'Medium', 'enumerate(iterable) yields (index, item) pairs.'),
('Python', 'What is the time complexity of looking up a key in a Python dictionary on average?', 'O(n)', 'O(log n)', 'O(1)', 'O(n^2)', 'C', 'Hard', 'Python dictionaries use hash tables which provide average O(1) time complexity for key lookups.'),

('SQL', 'Which SQL clause is used to filter records after aggregation?', 'WHERE', 'HAVING', 'GROUP BY', 'ORDER BY', 'B', 'Easy', 'HAVING filters groups created by GROUP BY after aggregations.'),
('SQL', 'What type of join returns all rows from the left table and matched rows from the right table?', 'INNER JOIN', 'RIGHT JOIN', 'LEFT JOIN', 'FULL JOIN', 'C', 'Easy', 'LEFT JOIN includes all rows from the left table regardless of matches in the right table.'),
('SQL', 'What is the primary benefit of adding a composite index on (column_a, column_b)?', 'Reduces table size', 'Optimizes queries filtering on column_a or (column_a AND column_b)', 'Ensures uniqueness across all columns', 'Speeds up full table scans', 'B', 'Hard', 'B-tree index prefix rule applies: composite index (a,b) accelerates queries on (a) or (a,b).'),

('JavaScript', 'Which keyword creates a block-scoped variable in modern JavaScript?', 'var', 'let', 'global', 'define', 'B', 'Easy', 'let and const declare block-scoped variables in ES6+.'),
('JavaScript', 'What will `console.log(typeof NaN)` display?', 'number', 'nan', 'undefined', 'object', 'A', 'Medium', 'In JavaScript, IEEE 754 NaN is technically of type "number".'),
('JavaScript', 'What is the event loop mechanism in Node.js/Browser JS?', 'Executes synchronous code concurrently on 4 CPU threads', 'Single-threaded non-blocking loop handling async call stack & task queues', 'Compiles code to native assembly during execution', 'Handles network packet routing', 'B', 'Hard', 'JS engine uses a single-threaded event loop to process task queues asynchronously.'),

('Git', 'Which command creates and switches to a new Git branch named "feature"?', 'git branch feature', 'git checkout -b feature', 'git switch --all feature', 'git merge feature', 'B', 'Easy', 'git checkout -b feature creates and checks out a new branch.'),
('Git', 'What is the purpose of `git rebase`?', 'Deletes remote commit history', 'Reapplies commits on top of another base tip', 'Creates a zip archive of repository', 'Reverts working directory changes', 'B', 'Medium', 'git rebase moves or reapplies a sequence of commits to a new base commit.');

