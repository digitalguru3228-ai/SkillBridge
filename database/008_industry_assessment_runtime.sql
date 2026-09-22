-- SKILLBRIDGE Migration 008: Industry Assessment Runtime
-- Adds response storage required for industry-defined pre-placement assessments.
USE industry_hub;

CREATE TABLE IF NOT EXISTS industry_assessment_responses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  selected_option ENUM('A','B','C','D') NOT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  difficulty ENUM('Easy','Medium','Hard') NOT NULL,
  score_earned DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_ia_response (attempt_id, question_id),
  INDEX idx_iar_attempt (attempt_id),
  INDEX idx_iar_question (question_id),
  CONSTRAINT fk_iar_attempt FOREIGN KEY (attempt_id) REFERENCES industry_assessment_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_iar_question FOREIGN KEY (question_id) REFERENCES industry_assessment_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
