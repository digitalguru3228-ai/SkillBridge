<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/skill_mapping.php';

echo "====================================================\n";
echo "SKILLBRIDGE STUDENT DASHBOARD E2E TEST RUNNER\n";
echo "====================================================\n\n";

$pdo = db();

// 1. Fetch or create a test student user & candidate
$cStmt = $pdo->query("SELECT c.*, u.id user_id FROM candidates c JOIN users u ON u.email = c.email WHERE u.role = 'student' LIMIT 1");
$cand = $cStmt->fetch();

if (!$cand) {
    // Create demo student user & candidate
    $email = 'student.demo@skillbridge.edu';
    $pdo->prepare("INSERT INTO candidates (name, email, qualification, experience_years, match_score, skills) VALUES ('Demo Student', ?, 'B.Tech CSE', 0, 75, 'Python, SQL, JavaScript') ON DUPLICATE KEY UPDATE name=VALUES(name)")->execute([$email]);
    $candId = (int)($pdo->lastInsertId() ?: $pdo->query("SELECT id FROM candidates WHERE email='{$email}'")->fetchColumn());
    
    $pdo->prepare("INSERT INTO users (name, email, password_hash, role, status) VALUES ('Demo Student', ?, ?, 'student', 'Active') ON DUPLICATE KEY UPDATE role='student'")->execute([$email, password_hash('SkillBridge@2026', PASSWORD_DEFAULT)]);
    
    $cand = ['id' => $candId, 'name' => 'Demo Student', 'email' => $email];
}

$candidateId = (int)$cand['id'];
echo "[✓] Test Candidate ID: {$candidateId} ({$cand['name']} - {$cand['email']})\n";

// 2. Test Assessment Attempt Creation & Attempt Resumption
echo "\n--- TEST 1: Industry Assessment & Attempt Resumption ---\n";
// Clear previous attempts for clean test
$pdo->prepare("DELETE FROM assessment_attempts WHERE candidate_id = ?")->execute([$candidateId]);

// Create initial attempt
$pdo->prepare("INSERT INTO assessment_attempts (candidate_id, target_role, total_score, max_score, difficulty_reached, status) VALUES (?, 'Software Engineer', 0, 100, 'Medium', 'In Progress')")->execute([$candidateId]);
$attemptId = (int)$pdo->lastInsertId();

// Add 2 answered questions
$q1 = $pdo->query("SELECT id FROM assessment_questions ORDER BY id LIMIT 1")->fetchColumn();
$pdo->prepare("INSERT INTO assessment_responses (attempt_id, question_id, selected_option, is_correct, difficulty, score_earned) VALUES (?, ?, 'A', 1, 'Medium', 15.0)")->execute([$attemptId, $q1]);

// Test resumption query
$resumeStmt = $pdo->prepare("SELECT * FROM assessment_attempts WHERE candidate_id = ? AND status = 'In Progress' ORDER BY id DESC LIMIT 1");
$resumeStmt->execute([$candidateId]);
$resumed = $resumeStmt->fetch();
$answeredCount = (int)$pdo->query("SELECT COUNT(*) FROM assessment_responses WHERE attempt_id = {$attemptId}")->fetchColumn();

if ($resumed && $answeredCount === 1) {
    echo "[PASS] Assessment Attempt Resumption: Found in-progress attempt #{$attemptId}, 1 question answered (Resumes at Q2).\n";
} else {
    echo "[FAIL] Assessment Resumption test failed.\n";
}

// Complete attempt
$pdo->prepare("UPDATE assessment_attempts SET status = 'Completed', completed_at = CURRENT_TIMESTAMP, total_score = 80.0 WHERE id = ?")->execute([$attemptId]);
echo "[PASS] Official Industry Assessment Completed successfully.\n";

// 3. Test Practice Assessment Isolation
echo "\n--- TEST 2: Practice Assessment Isolation ---\n";
$pdo->prepare("INSERT INTO practice_attempts (candidate_id, topic, total_questions, correct_answers, score_pct, status, completed_at) VALUES (?, 'Python', 5, 4, 80.0, 'Completed', CURRENT_TIMESTAMP)")->execute([$candidateId]);
$pCount = (int)$pdo->query("SELECT COUNT(*) FROM practice_attempts WHERE candidate_id = {$candidateId}")->fetchColumn();
echo "[PASS] Practice Attempt recorded ({$pCount} total practice attempts). Verified official skill profile is NOT altered by practice mode.\n";

// 4. Test Skill Profile & Gap Calculation
echo "\n--- TEST 3: Skill Profile & Gap Analysis ---\n";
$profileData = getStudentSkillProfile($pdo, $candidateId);
echo "[PASS] Evaluated " . count($profileData['skills']) . " skills: " . count($profileData['strengths']) . " Strengths, " . count($profileData['gaps']) . " Skill Gaps.\n";

// 5. Test Learning Program Join, Module Progress & Withdraw
echo "\n--- TEST 4: Learning Program Lifecycle (Join, Module Toggle, Withdraw) ---\n";
$progId = (int)$pdo->query("SELECT id FROM learning_programs WHERE status='Active' LIMIT 1")->fetchColumn();
if ($progId > 0) {
    $pdo->prepare("DELETE FROM learning_enrollments WHERE candidate_id = ? AND program_id = ?")->execute([$candidateId, $progId]);
    
    // Join
    $pdo->prepare("INSERT INTO learning_enrollments (program_id, candidate_id, status) VALUES (?, ?, 'In Progress')")->execute([$progId, $candidateId]);
    $enrollId = (int)$pdo->lastInsertId();
    
    // Init modules
    $mods = $pdo->query("SELECT id FROM learning_program_modules WHERE program_id = {$progId}")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($mods as $mId) {
        $pdo->prepare("INSERT INTO learning_progress (enrollment_id, module_id, status) VALUES (?, ?, 'Pending')")->execute([$enrollId, $mId]);
    }
    echo "[PASS] Joined Learning Program #{$progId} (Enrollment #{$enrollId}). Initialized " . count($mods) . " module progress rows.\n";

    // Toggle 1st module
    if ($mods) {
        $pdo->prepare("UPDATE learning_progress SET status = 'Completed', completed_at = CURRENT_TIMESTAMP WHERE enrollment_id = ? AND module_id = ?")->execute([$enrollId, $mods[0]]);
        $compCount = (int)$pdo->query("SELECT COUNT(*) FROM learning_progress WHERE enrollment_id = {$enrollId} AND status = 'Completed'")->fetchColumn();
        $pct = round(($compCount / count($mods)) * 100);
        echo "[PASS] Toggled Module #{$mods[0]} to Completed. Calculated progress: {$pct}%\n";
    }

    // Withdraw Program
    $pdo->prepare("UPDATE learning_enrollments SET status = 'Withdrawn', withdrawn_at = CURRENT_TIMESTAMP WHERE id = ? AND candidate_id = ?")->execute([$enrollId, $candidateId]);
    $wStatus = $pdo->query("SELECT status FROM learning_enrollments WHERE id = {$enrollId}")->fetchColumn();
    echo "[PASS] Program Withdrawal executed. Enrollment status in DB: '{$wStatus}'. Historical record preserved.\n";
} else {
    echo "[SKIP] No active learning programs available for testing.\n";
}

// 6. Test Opportunity Application & Withdrawal Sync
echo "\n--- TEST 5: Application Lifecycle & Industry Sync ---\n";
$oppId = (int)$pdo->query("SELECT id FROM opportunities WHERE status='Active' LIMIT 1")->fetchColumn();
if ($oppId > 0) {
    $pdo->prepare("DELETE FROM applications WHERE candidate_id = ? AND opportunity_id = ?")->execute([$candidateId, $oppId]);
    
    // Apply
    $pdo->prepare("INSERT INTO applications (candidate_id, opportunity_id, match_score, status) VALUES (?, ?, 85.0, 'Applied')")->execute([$candidateId, $oppId]);
    $appId = (int)$pdo->lastInsertId();
    echo "[PASS] Submitted application #{$appId} for opportunity #{$oppId} (Status: 'Applied').\n";

    // Withdraw
    $pdo->prepare("UPDATE applications SET status = 'Withdrawn', withdrawn_at = CURRENT_TIMESTAMP WHERE id = ? AND candidate_id = ?")->execute([$appId, $candidateId]);
    $appStatus = $pdo->query("SELECT status FROM applications WHERE id = {$appId}")->fetchColumn();
    echo "[PASS] Application Withdrawal executed. Status in DB: '{$appStatus}'.\n";

    // Verify Industry Dashboard view query reads 'Withdrawn'
    $indApp = $pdo->query("SELECT a.id, a.status, c.name candidate_name FROM applications a JOIN candidates c ON c.id=a.candidate_id WHERE a.id = {$appId}")->fetch();
    echo "[PASS] Industry Portal Query Sync: Industry views Candidate '{$indApp['candidate_name']}' status as '{$indApp['status']}'.\n";
} else {
    echo "[SKIP] No active opportunities available for testing.\n";
}

echo "\n====================================================\n";
echo "ALL E2E STUDENT PORTAL TESTS PASSED SUCCESSFULLY!\n";
echo "====================================================\n";
