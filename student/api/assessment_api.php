<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/skill_mapping.php';

requireRole('student');
header('Content-Type: application/json; charset=utf-8');

$pdo = db();
$candidateId = getStudentCandidateId($pdo);
$questionsPerAttempt = defined('ASSESSMENT_QUESTIONS_PER_ATTEMPT') ? (int)ASSESSMENT_QUESTIONS_PER_ATTEMPT : 5;

if ($candidateId <= 0) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Student profile not linked to an active candidate record.']);
    exit;
}

function assessmentJson(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function nextDifficulty(string $current, bool $correct): string {
    if ($correct) {
        return match ($current) {
            'Easy' => 'Medium',
            'Medium' => 'Hard',
            default => 'Hard',
        };
    }
    return match ($current) {
        'Hard' => 'Medium',
        'Medium' => 'Easy',
        default => 'Easy',
    };
}

function industryAssessmentAccess(PDO $pdo, int $candidateId, int $assessmentId): ?array {
    $stmt = $pdo->prepare("
        SELECT ia.*, o.title AS opportunity_title
        FROM industry_assessments ia
        LEFT JOIN opportunities o ON o.id = ia.opportunity_id
        WHERE ia.id = ? AND ia.status = 'Active'
        LIMIT 1
    ");
    $stmt->execute([$assessmentId]);
    $assessment = $stmt->fetch();
    if (!$assessment) return null;

    // A pre-placement assessment linked to an opportunity is available only
    // to students who have an application for that opportunity.
    if (!empty($assessment['opportunity_id'])) {
        $check = $pdo->prepare("
            SELECT a.id AS application_id
            FROM applications a
            WHERE a.candidate_id = ? AND a.opportunity_id = ?
            LIMIT 1
        ");
        $check->execute([$candidateId, (int)$assessment['opportunity_id']]);
        $app = $check->fetch();
        if (!$app) return null;
        $assessment['application_id'] = (int)$app['application_id'];
    } else {
        $assessment['application_id'] = null;
    }
    return $assessment;
}

function industryQuestionCount(PDO $pdo, int $assessmentId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM industry_assessment_questions WHERE assessment_id = ?");
    $stmt->execute([$assessmentId]);
    return (int)$stmt->fetchColumn();
}

function industryQuestion(PDO $pdo, int $assessmentId, string $difficulty, array $usedIds = []): ?array {
    $exclude = $usedIds ? ' AND id NOT IN (' . implode(',', array_map('intval', $usedIds)) . ')' : '';
    $stmt = $pdo->prepare("
        SELECT id, question, option_a, option_b, option_c, option_d, difficulty, skill_topic, marks
        FROM industry_assessment_questions
        WHERE assessment_id = ? AND difficulty = ?{$exclude}
        ORDER BY order_num ASC, id ASC
        LIMIT 1
    ");
    $stmt->execute([$assessmentId, $difficulty]);
    $q = $stmt->fetch();
    if ($q) return $q;

    $stmt = $pdo->prepare("
        SELECT id, question, option_a, option_b, option_c, option_d, difficulty, skill_topic, marks
        FROM industry_assessment_questions
        WHERE assessment_id = ?{$exclude}
        ORDER BY order_num ASC, id ASC
        LIMIT 1
    ");
    $stmt->execute([$assessmentId]);
    return $stmt->fetch() ?: null;
}

function completeIndustryAttempt(PDO $pdo, int $candidateId, int $attemptId, int $assessmentId): array {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM industry_assessment_responses WHERE attempt_id = ?");
    $countStmt->execute([$attemptId]);
    $answered = (int)$countStmt->fetchColumn();

    $required = industryQuestionCount($pdo, $assessmentId);
    if ($required <= 0 || $answered < $required) {
        return ['completed' => false, 'answered' => $answered, 'required' => $required];
    }

    $totStmt = $pdo->prepare("
        SELECT COALESCE(SUM(r.score_earned),0) AS earned,
               COALESCE(SUM(q.marks),0) AS possible
        FROM industry_assessment_responses r
        JOIN industry_assessment_questions q ON q.id = r.question_id
        WHERE r.attempt_id = ?
    ");
    $totStmt->execute([$attemptId]);
    $totals = $totStmt->fetch() ?: ['earned'=>0,'possible'=>0];

    $earned = (float)$totals['earned'];
    $possible = (float)$totals['possible'];
    $pct = $possible > 0 ? round(($earned / $possible) * 100, 2) : 0.0;

    $passStmt = $pdo->prepare("SELECT passing_score_pct FROM industry_assessments WHERE id = ? LIMIT 1");
    $passStmt->execute([$assessmentId]);
    $passing = (float)($passStmt->fetchColumn() ?: 60);

    $pdo->prepare("
        UPDATE industry_assessment_attempts
        SET total_score=?, max_score=?, percentage=?, is_passed=?, status='Completed', completed_at=CURRENT_TIMESTAMP
        WHERE id=? AND candidate_id=? AND assessment_id=?
    ")->execute([$earned, $possible, $pct, $pct >= $passing ? 1 : 0, $attemptId, $candidateId, $assessmentId]);

    try {
        $titleStmt=$pdo->prepare('SELECT title FROM industry_assessments WHERE id=? LIMIT 1');
        $titleStmt->execute([$assessmentId]);
        $assessmentTitle=(string)($titleStmt->fetchColumn() ?: 'Industry Assessment');
        sbNotifyCandidate($pdo,$candidateId,'Industry assessment completed',
            'You completed “'.$assessmentTitle.'” with a score of '.$pct.'%. Passing score: '.$passing.'%.',
            'assessment','industry_assessment_attempt',$attemptId);
    } catch (Throwable $ignored) {
        // Activity delivery must not interrupt assessment completion.
    }

    return [
        'completed' => true,
        'final_score' => $pct,
        'is_passed' => $pct >= $passing,
        'passing_score' => $passing
    ];
}

$action = (string)($_POST['action'] ?? $_REQUEST['action'] ?? '');
$assessmentId = (int)($_POST['assessment_id'] ?? $_REQUEST['assessment_id'] ?? 0);

try {
    if (in_array($action, ['start', 'answer'], true) &&
        !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        assessmentJson(['ok'=>false, 'message'=>'Invalid security token. Please refresh and try again.'], 403);
    }

    /*
     * INDUSTRY PRE-PLACEMENT ASSESSMENT
     * When assessment_id is supplied, every question comes exclusively from
     * industry_assessment_questions for that assessment. Generic assessment
     * questions are never mixed into this flow.
     */
    if ($action === 'start' && $assessmentId > 0) {
        $assessment = industryAssessmentAccess($pdo, $candidateId, $assessmentId);
        if (!$assessment) {
            assessmentJson(['ok'=>false, 'message'=>'This industry assessment is not available for your account/application.'], 403);
        }

        $industryTotal = industryQuestionCount($pdo, $assessmentId);
        if ($industryTotal <= 0) {
            assessmentJson(['ok'=>false,'message'=>'The industry has not added any questions to this assessment yet.'],400);
        }

        $resume = $pdo->prepare("
            SELECT * FROM industry_assessment_attempts
            WHERE candidate_id=? AND assessment_id=? AND status='In Progress'
            ORDER BY id DESC LIMIT 1
        ");
        $resume->execute([$candidateId, $assessmentId]);
        $attempt = $resume->fetch();

        if (!$attempt) {
            $pdo->prepare("
                INSERT INTO industry_assessment_attempts
                (assessment_id,candidate_id,application_id,total_score,max_score,status)
                VALUES (?,?,?,?,0,'In Progress')
            ")->execute([
                $assessmentId, $candidateId,
                $assessment['application_id'] ?: null, 0
            ]);
            $attemptId = (int)$pdo->lastInsertId();
        } else {
            $attemptId = (int)$attempt['id'];
        }

        $usedStmt = $pdo->prepare("SELECT question_id FROM industry_assessment_responses WHERE attempt_id=?");
        $usedStmt->execute([$attemptId]);
        $usedIds = array_map('intval', $usedStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        if (count($usedIds) >= $industryTotal) {
            $done = completeIndustryAttempt($pdo,$candidateId,$attemptId,$assessmentId);
            assessmentJson([
                'ok'=>true,'completed'=>true,'attempt_id'=>$attemptId,
                'assessment'=>[
                    'id'=>(int)$assessment['id'],'title'=>$assessment['title'],
                    'description'=>$assessment['description'],
                    'duration_mins'=>(int)$assessment['duration_mins'],
                    'passing_score_pct'=>(float)$assessment['passing_score_pct']
                ],
                ...$done
            ]);
        }

        $question = industryQuestion($pdo,$assessmentId,'Medium',$usedIds);
        if (!$question) {
            assessmentJson(['ok'=>false,'message'=>'The industry has not added any questions to this assessment yet.'], 400);
        }

        assessmentJson([
            'ok'=>true,'source'=>'industry','attempt_id'=>$attemptId,
            'question_number'=>count($usedIds)+1,'total_questions'=>$industryTotal,
            'assessment'=>[
                'id'=>(int)$assessment['id'],'title'=>$assessment['title'],
                'description'=>$assessment['description'],
                'duration_mins'=>(int)$assessment['duration_mins'],
                'passing_score_pct'=>(float)$assessment['passing_score_pct']
            ],
            'question'=>$question
        ]);
    }

    if ($action === 'answer' && $assessmentId > 0) {
        $attemptId = (int)($_POST['attempt_id'] ?? 0);
        $questionId = (int)($_POST['question_id'] ?? 0);
        $selected = strtoupper(trim((string)($_POST['selected_option'] ?? '')));

        $assessment = industryAssessmentAccess($pdo,$candidateId,$assessmentId);
        if (!$assessment) assessmentJson(['ok'=>false,'message'=>'Assessment access is no longer valid.'],403);
        $industryTotal = industryQuestionCount($pdo, $assessmentId);
        if ($industryTotal <= 0) assessmentJson(['ok'=>false,'message'=>'This assessment has no questions.'],400);

        $attStmt = $pdo->prepare("
            SELECT * FROM industry_assessment_attempts
            WHERE id=? AND candidate_id=? AND assessment_id=? AND status='In Progress'
            LIMIT 1
        ");
        $attStmt->execute([$attemptId,$candidateId,$assessmentId]);
        $attempt = $attStmt->fetch();
        if (!$attempt) assessmentJson(['ok'=>false,'message'=>'Assessment attempt is invalid or already completed.'],400);

        $qStmt = $pdo->prepare("
            SELECT * FROM industry_assessment_questions
            WHERE id=? AND assessment_id=? LIMIT 1
        ");
        $qStmt->execute([$questionId,$assessmentId]);
        $question = $qStmt->fetch();
        if (!$question) assessmentJson(['ok'=>false,'message'=>'Question does not belong to this assessment.'],400);

        $dup = $pdo->prepare("SELECT id FROM industry_assessment_responses WHERE attempt_id=? AND question_id=? LIMIT 1");
        $dup->execute([$attemptId,$questionId]);
        if ($dup->fetchColumn()) assessmentJson(['ok'=>false,'message'=>'This question has already been answered.'],400);

        if (!in_array($selected,['A','B','C','D'],true)) {
            assessmentJson(['ok'=>false,'message'=>'Please select a valid answer.'],400);
        }

        $correct = $selected === $question['correct_option'];
        $marks = (float)$question['marks'];
        $earned = $correct ? $marks : 0.0;

        $pdo->beginTransaction();
        $pdo->prepare("
            INSERT INTO industry_assessment_responses
            (attempt_id,question_id,selected_option,is_correct,difficulty,score_earned)
            VALUES (?,?,?,?,?,?)
        ")->execute([$attemptId,$questionId,$selected,$correct?1:0,$question['difficulty'],$earned]);

        $answeredStmt = $pdo->prepare("SELECT COUNT(*) FROM industry_assessment_responses WHERE attempt_id=?");
        $answeredStmt->execute([$attemptId]);
        $answered = (int)$answeredStmt->fetchColumn();

        if ($answered >= $industryTotal) {
            if ($pdo->inTransaction()) $pdo->commit();
            $done = completeIndustryAttempt($pdo,$candidateId,$attemptId,$assessmentId);
            assessmentJson([
                'ok'=>true,'source'=>'industry','completed'=>true,
                'is_correct'=>$correct,'explanation'=>'',
                'final_score'=>$done['final_score'],
                'is_passed'=>$done['is_passed'],
                'passing_score'=>$done['passing_score']
            ]);
        }

        $usedStmt = $pdo->prepare("SELECT question_id FROM industry_assessment_responses WHERE attempt_id=?");
        $usedStmt->execute([$attemptId]);
        $usedIds = array_map('intval',$usedStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        $nextDiff = nextDifficulty((string)$question['difficulty'],$correct);
        $nextQ = industryQuestion($pdo,$assessmentId,$nextDiff,$usedIds);
        if ($pdo->inTransaction()) $pdo->commit();

        if (!$nextQ) {
            $done = completeIndustryAttempt($pdo,$candidateId,$attemptId,$assessmentId);
            assessmentJson([
                'ok'=>true,'source'=>'industry','completed'=>true,
                'is_correct'=>$correct,'explanation'=>'',
                'final_score'=>$done['final_score'],
                'is_passed'=>$done['is_passed'],
                'passing_score'=>$done['passing_score']
            ]);
        }

        assessmentJson([
            'ok'=>true,'source'=>'industry','completed'=>false,
            'is_correct'=>$correct,'explanation'=>'',
            'next_difficulty'=>$nextDiff,
            'question_number'=>$answered+1,'total_questions'=>$industryTotal,
            'question'=>$nextQ
        ]);
    }

    // EXISTING GENERIC / STUDENT SKILL ADAPTIVE ASSESSMENT
    if ($action === 'start') {
        $role = trim((string)($_POST['target_role'] ?? 'Software Engineer'));
        $resumeStmt = $pdo->prepare("SELECT * FROM assessment_attempts WHERE candidate_id=? AND status='In Progress' ORDER BY id DESC LIMIT 1");
        $resumeStmt->execute([$candidateId]);
        $existing = $resumeStmt->fetch();

        if ($existing) {
            $attemptId=(int)$existing['id'];
            $usedStmt=$pdo->prepare("SELECT question_id FROM assessment_responses WHERE attempt_id=?");
            $usedStmt->execute([$attemptId]);
            $usedIds=array_map('intval',$usedStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            $answeredCount=count($usedIds);
            if ($answeredCount >= $questionsPerAttempt) {
                $done=completeAssessmentAttempt($pdo,$candidateId,$attemptId);
                assessmentJson(['ok'=>true,'completed'=>true,'attempt_id'=>$attemptId,...$done]);
            }
            $currentDiff=(string)($existing['difficulty_reached'] ?: 'Medium');
            $qStmt=$pdo->prepare("
                SELECT id,topic,question,option_a,option_b,option_c,option_d,difficulty
                FROM assessment_questions
                WHERE id NOT IN (" . ($usedIds ? implode(',',$usedIds) : '0') . ")
                ORDER BY FIELD(difficulty,?), RAND() LIMIT 1
            ");
            // MySQL FIELD with a parameter is valid; use explicit fallback for portability.
            $qStmt=$pdo->prepare("
                SELECT id,topic,question,option_a,option_b,option_c,option_d,difficulty
                FROM assessment_questions
                WHERE id NOT IN (" . ($usedIds ? implode(',',$usedIds) : '0') . ")
                ORDER BY CASE WHEN difficulty=? THEN 0 WHEN difficulty='Medium' THEN 1 WHEN difficulty='Easy' THEN 2 ELSE 3 END, RAND()
                LIMIT 1
            ");
            $qStmt->execute([$currentDiff]);
            $question=$qStmt->fetch();
            assessmentJson(['ok'=>true,'resumed'=>true,'attempt_id'=>$attemptId,'question_number'=>$answeredCount+1,'total_questions'=>$questionsPerAttempt,'question'=>$question]);
        }

        $stmt=$pdo->prepare("INSERT INTO assessment_attempts (candidate_id,target_role,total_score,max_score,difficulty_reached,status) VALUES (?,?,0,100,'Medium','In Progress')");
        $stmt->execute([$candidateId,$role]);
        $attemptId=(int)$pdo->lastInsertId();

        $qStmt=$pdo->query("SELECT id,topic,question,option_a,option_b,option_c,option_d,difficulty FROM assessment_questions WHERE difficulty='Medium' ORDER BY RAND() LIMIT 1");
        $question=$qStmt->fetch();
        if (!$question) {
            $question=$pdo->query("SELECT id,topic,question,option_a,option_b,option_c,option_d,difficulty FROM assessment_questions ORDER BY RAND() LIMIT 1")->fetch();
        }
        if (!$question) assessmentJson(['ok'=>false,'message'=>'No assessment questions available.'],400);

        assessmentJson(['ok'=>true,'resumed'=>false,'attempt_id'=>$attemptId,'question_number'=>1,'total_questions'=>$questionsPerAttempt,'question'=>$question]);
    }

    if ($action === 'answer') {
        $attemptId=(int)($_POST['attempt_id']??0);
        $questionId=(int)($_POST['question_id']??0);
        $selected=strtoupper(trim((string)($_POST['selected_option']??'')));

        $attCheck=$pdo->prepare("SELECT * FROM assessment_attempts WHERE id=? AND candidate_id=? AND status='In Progress' LIMIT 1");
        $attCheck->execute([$attemptId,$candidateId]);
        $attemptRow=$attCheck->fetch();
        if (!$attemptRow) assessmentJson(['ok'=>false,'message'=>'Unauthorized or invalid assessment attempt.'],400);

        $qStmt=$pdo->prepare("SELECT * FROM assessment_questions WHERE id=? LIMIT 1");
        $qStmt->execute([$questionId]);
        $question=$qStmt->fetch();
        if (!$question) assessmentJson(['ok'=>false,'message'=>'Invalid question.'],400);

        $dupStmt=$pdo->prepare("SELECT id FROM assessment_responses WHERE attempt_id=? AND question_id=? LIMIT 1");
        $dupStmt->execute([$attemptId,$questionId]);
        if ($dupStmt->fetchColumn()) assessmentJson(['ok'=>false,'message'=>'This question has already been answered.'],400);

        $isCorrect=($selected===$question['correct_option']);
        $diff=(string)$question['difficulty'];
        $scoreEarned=$isCorrect ? ($diff==='Hard'?20.0:($diff==='Medium'?15.0:10.0)) : 0.0;

        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO assessment_responses (attempt_id,question_id,selected_option,is_correct,difficulty,score_earned) VALUES (?,?,?,?,?,?)")
            ->execute([$attemptId,$questionId,$selected,$isCorrect?1:0,$diff,$scoreEarned]);

        $pdo->prepare("UPDATE assessment_attempts SET total_score=total_score+?,difficulty_reached=? WHERE id=? AND candidate_id=?")
            ->execute([$scoreEarned,$diff,$attemptId,$candidateId]);

        $answeredStmt=$pdo->prepare("SELECT COUNT(*) FROM assessment_responses WHERE attempt_id=?");
        $answeredStmt->execute([$attemptId]);
        $answeredCount=(int)$answeredStmt->fetchColumn();

        if ($answeredCount >= $questionsPerAttempt) {
            if ($pdo->inTransaction()) $pdo->commit();
            $completion=completeAssessmentAttempt($pdo,$candidateId,$attemptId);
            assessmentJson(['ok'=>true,'completed'=>true,'is_correct'=>(bool)$isCorrect,'explanation'=>$question['explanation'],'final_score'=>$completion['final_score'],'strengths'=>$completion['strengths'],'gaps'=>$completion['gaps'],'persisted_strengths'=>$completion['persisted_strengths'],'persisted_gaps'=>$completion['persisted_gaps']]);
        }

        $usedStmt=$pdo->prepare("SELECT question_id FROM assessment_responses WHERE attempt_id=?");
        $usedStmt->execute([$attemptId]);
        $usedIds=array_map('intval',$usedStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $nextDiff=nextDifficulty($diff,(bool)$isCorrect);
        $inClause=$usedIds ? implode(',',$usedIds) : '0';
        $nextQStmt=$pdo->prepare("
            SELECT id,topic,question,option_a,option_b,option_c,option_d,difficulty
            FROM assessment_questions
            WHERE difficulty=? AND id NOT IN ($inClause)
            ORDER BY RAND() LIMIT 1
        ");
        $nextQStmt->execute([$nextDiff]);
        $nextQ=$nextQStmt->fetch();
        if (!$nextQ) {
            $nextQStmt=$pdo->prepare("SELECT id,topic,question,option_a,option_b,option_c,option_d,difficulty FROM assessment_questions WHERE id NOT IN ($inClause) ORDER BY RAND() LIMIT 1");
            $nextQStmt->execute();
            $nextQ=$nextQStmt->fetch();
        }

        if ($pdo->inTransaction()) $pdo->commit();

        assessmentJson(['ok'=>true,'completed'=>false,'is_correct'=>(bool)$isCorrect,'explanation'=>$question['explanation'],'next_difficulty'=>$nextDiff,'question_number'=>$answeredCount+1,'total_questions'=>$questionsPerAttempt,'question'=>$nextQ]);
    }

    assessmentJson(['ok'=>false,'message'=>'Unknown API action.'],400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Assessment API error: ' . $e->getMessage());
    assessmentJson(['ok'=>false,'message'=>'Something went wrong. Please try again.'],400);
}
