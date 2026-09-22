-- SKILLBRIDGE Migration 004: Student Portal Production Fix & Lifecycle Upgrades
USE industry_hub;

-- 1. Upgrade learning_enrollments status enum and add withdrawn_at
ALTER TABLE learning_enrollments 
  MODIFY COLUMN status ENUM('Enrolled','In Progress','Completed','Withdrawn') NOT NULL DEFAULT 'Enrolled';

SET @dbname = DATABASE();
SET @tablename = "learning_enrollments";
SET @columnname = "withdrawn_at";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE learning_enrollments ADD COLUMN withdrawn_at TIMESTAMP NULL AFTER enrolled_at;"
));
PREPARE addColumnIfNotExists FROM @preparedStatement;
EXECUTE addColumnIfNotExists;
DEALLOCATE PREPARE addColumnIfNotExists;

-- 2. Upgrade applications status enum and add withdrawn_at
ALTER TABLE applications 
  MODIFY COLUMN status ENUM('Applied','Under Review','Shortlisted','Interview','Selected','Rejected','Withdrawn') NOT NULL DEFAULT 'Applied';

SET @tablename = "applications";
SET @columnname = "withdrawn_at";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE applications ADD COLUMN withdrawn_at TIMESTAMP NULL AFTER applied_at;"
));
PREPARE addColumnIfNotExists FROM @preparedStatement;
EXECUTE addColumnIfNotExists;
DEALLOCATE PREPARE addColumnIfNotExists;

-- 3. Seed comprehensive assessment questions for core topics & difficulty levels
INSERT INTO assessment_questions (topic, question, option_a, option_b, option_c, option_d, correct_option, difficulty, explanation) VALUES
-- Data Structures
('Data Structures', 'Which data structure follows the LIFO (Last In First Out) principle?', 'Queue', 'Stack', 'Linked List', 'Tree', 'B', 'Easy', 'Stack follows Last In First Out (LIFO).'),
('Data Structures', 'What is the average time complexity for searching an element in a Balanced Binary Search Tree (AVL)?', 'O(1)', 'O(n)', 'O(log n)', 'O(n^2)', 'C', 'Medium', 'Balanced BSTs maintain O(log n) height, making search operation O(log n).'),
('Data Structures', 'In Graph Theory, which algorithm finds the shortest path from a single source vertex to all other vertices in a weighted graph with non-negative weights?', 'Kruskal Algorithm', 'Dijkstra Algorithm', 'Prim Algorithm', 'Floyd-Warshall Algorithm', 'B', 'Hard', 'Dijkstra algorithm is designed for single-source shortest paths on non-negatively weighted graphs.'),

-- Java
('Java', 'Which keyword is used to prevent method overriding in Java?', 'static', 'abstract', 'final', 'synchronized', 'C', 'Easy', 'The final keyword on a method prevents subclasses from overriding it.'),
('Java', 'What is the garbage collection algorithm basis in modern JVMs for heap memory management?', 'Reference Counting', 'Mark-Sweep-Compact / Generational GC', 'Manual malloc/free', 'Stack allocation only', 'B', 'Medium', 'JVMs use generational garbage collection (Young/Old gen) with Mark-Sweep-Compact phases.'),
('Java', 'What occurs when calling `wait()` inside a non-synchronized block in Java?', 'Deadlock occurs immediately', 'Throws IllegalMonitorStateException', 'Thread yields CPU execution to next process', 'Compiles with warning only', 'B', 'Hard', 'Calling wait() without holding the monitor lock throws IllegalMonitorStateException.'),

-- Python (Additional)
('Python', 'What does the `*args` syntax in a Python function parameter list represent?', 'Variable number of keyword arguments', 'Variable number of non-keyword positional arguments', 'A pointer to a memory address', 'A mandatory list parameter', 'B', 'Easy', '*args collects excess positional arguments into a tuple.'),
('Python', 'Which decorator is used in Python to define a method that belongs to the class rather than instances?', '@staticmethod', '@classmethod', '@property', '@abstractmethod', 'B', 'Medium', '@classmethod passes the class (cls) as the first argument.'),

-- SQL (Additional)
('SQL', 'Which constraint guarantees that all values in a column are distinct?', 'FOREIGN KEY', 'CHECK', 'UNIQUE', 'DEFAULT', 'C', 'Easy', 'UNIQUE constraint ensures all values in a column are unique across table rows.'),
('SQL', 'What is the main function of an ACID-compliant database transaction?', 'To compress table data storage', 'To guarantee Atomicity, Consistency, Isolation, and Durability', 'To encrypt SQL query strings', 'To format output HTML tables', 'B', 'Medium', 'ACID guarantees reliable execution of database transactions.')
ON DUPLICATE KEY UPDATE question=VALUES(question);
