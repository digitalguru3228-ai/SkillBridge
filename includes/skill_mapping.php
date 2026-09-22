<?php
declare(strict_types=1);
require_once __DIR__ . '/notifications.php';

/**
 * SKILLBRIDGE — Skill Mapping & Recommendation Engine
 * Connects student assessment results, profile skills, industry opportunity requirements,
 * and company learning programs to generate actionable intelligence.
 */

function getStudentSkillProfile(PDO $pdo, int $candidateId): array {
    $skillScores = [];
    $rawSkills = [];

    // 1. Fetch skills from candidate_skills table
    $stmt = $pdo->prepare("SELECT s.name, cs.proficiency FROM candidate_skills cs JOIN skills s ON s.id = cs.skill_id WHERE cs.candidate_id = ?");
    $stmt->execute([$candidateId]);
    foreach ($stmt->fetchAll() as $row) {
        $name = trim($row['name']);
        $score = match ($row['proficiency']) {
            'Expert' => 95,
            'Advanced' => 85,
            'Intermediate' => 70,
            'Beginner' => 50,
            default => 60
        };
        $skillScores[mb_strtolower($name)] = ['name' => $name, 'score' => $score];
    }

    // 2. Adjust scores using recent completed assessment responses
    $stmt = $pdo->prepare("
        SELECT q.topic, ar.is_correct, ar.difficulty, ar.score_earned
        FROM assessment_responses ar
        JOIN assessment_attempts aa ON aa.id = ar.attempt_id
        JOIN assessment_questions q ON q.id = ar.question_id
        WHERE aa.candidate_id = ? AND aa.status = 'Completed'
    ");
    $stmt->execute([$candidateId]);
    $topicStats = [];
    foreach ($stmt->fetchAll() as $resp) {
        $t = mb_strtolower(trim($resp['topic']));
        if (!isset($topicStats[$t])) {
            $topicStats[$t] = ['total' => 0, 'correct' => 0, 'score' => 0];
        }
        $topicStats[$t]['total']++;
        if ($resp['is_correct']) {
            $topicStats[$t]['correct']++;
        }
        $topicStats[$t]['score'] += (float)$resp['score_earned'];
    }

    foreach ($topicStats as $t => $stat) {
        $calculatedPct = $stat['total'] > 0 ? round(($stat['correct'] / $stat['total']) * 100) : 0;
        $displayName = ucfirst($t);
        if (isset($skillScores[$t])) {
            $displayName = $skillScores[$t]['name'];
        }
        // Blended score (70% assessment + 30% self-assessment)
        $blendedScore = isset($skillScores[$t]) 
            ? round(($calculatedPct * 0.7) + ($skillScores[$t]['score'] * 0.3))
            : $calculatedPct;
        $skillScores[$t] = ['name' => $displayName, 'score' => $blendedScore];
    }

    // Categorize Strengths and Gaps
    $strengths = [];
    $gaps = [];
    foreach ($skillScores as $item) {
        if ($item['score'] >= 70) {
            $strengths[] = $item;
        } else {
            $gaps[] = $item;
        }
    }

    usort($strengths, fn($a, $b) => $b['score'] <=> $a['score']);
    usort($gaps, fn($a, $b) => $a['score'] <=> $b['score']);

    return [
        'skills' => array_values($skillScores),
        'strengths' => $strengths,
        'gaps' => $gaps
    ];
}

function calculateOpportunityMatch(array $studentSkillNames, string $requiredSkillsText, ?string $candQual = null, ?float $candExp = null, ?string $oppQual = null, ?string $oppExp = null): array {
    $req = array_filter(array_map('trim', preg_split('/[,\n]+/', $requiredSkillsText) ?: []));
    
    $studentMap = array_map('mb_strtolower', array_map('trim', $studentSkillNames));
    $matched = [];
    $missing = [];

    foreach ($req as $r) {
        if (in_array(mb_strtolower($r), $studentMap, true)) {
            $matched[] = $r;
        } else {
            $missing[] = $r;
        }
    }

    $pct = $req ? round((count($matched) / count($req)) * 100) : 100;

    $qualMatch = true;
    if ($candQual && $oppQual) {
        $qualMatch = (stripos($candQual, $oppQual) !== false || stripos($oppQual, $candQual) !== false);
    }

    $expMatch = true;
    if ($candExp !== null && $oppExp !== null && preg_match('/(\d+)/', $oppExp, $m)) {
        $reqYears = (float)$m[1];
        $expMatch = ($candExp >= $reqYears);
    }

    return [
        'match_pct' => $pct,
        'matched' => $matched,
        'missing' => $missing,
        'qual_match' => $qualMatch,
        'exp_match' => $expMatch
    ];
}

function getRecommendedOpportunities(PDO $pdo, int $candidateId): array {
    // Use the same authoritative, opportunity-specific matching engine used by
    // Industry Smart Matching and Student application submission.
    $stmt = $pdo->query("
        SELECT o.*, c.name AS company_name
        FROM opportunities o
        JOIN companies c ON c.id = o.company_id
        WHERE LOWER(COALESCE(o.status,'')) IN ('active','open','published')
        ORDER BY o.created_at DESC, o.id DESC
    ");
    $opportunities = $stmt->fetchAll();

    $results = [];
    foreach ($opportunities as $opp) {
        $details = calculateOpportunityMatchDetails($pdo, $candidateId, (int)$opp['id']);
        $results[] = array_merge($opp, [
            'match_pct' => (float)($details['total_score'] ?? 0),
            'matched_skills' => array_values(array_map(
                static fn(array $s): string => (string)$s['name'],
                array_filter($details['matched_skills'] ?? [], static fn(array $s): bool => !empty($s['meets_threshold']))
            )),
            'missing_skills' => $details['missing_skills'] ?? [],
            'skill_gaps' => $details['skill_gaps'] ?? [],
            'match_breakdown' => $details['breakdown'] ?? [],
            'eligible' => (bool)($details['eligible'] ?? false),
            'match_reasons' => $details['reasons'] ?? [],
        ]);
    }

    usort($results, static function (array $a, array $b): int {
        return ((float)$b['match_pct'] <=> (float)$a['match_pct'])
            ?: strcmp((string)$a['title'], (string)$b['title']);
    });

    return $results;
}

function getRecommendedLearningPrograms(PDO $pdo, array $skillGaps): array {
    $programs = $pdo->query("SELECT lp.*, c.name company_name FROM learning_programs lp JOIN companies c ON c.id=lp.company_id WHERE lp.status = 'Active' ORDER BY lp.created_at DESC")->fetchAll();
    
    $gapNames = array_map('mb_strtolower', array_column($skillGaps, 'name'));
    
    $recommended = [];
    foreach ($programs as $prog) {
        $targetSkills = array_map('mb_strtolower', array_filter(array_map('trim', preg_split('/[,\n]+/', (string)$prog['target_skills']) ?: [])));
        $matchedGaps = array_intersect($gapNames, $targetSkills);
        $relevanceScore = count($matchedGaps);
        $recommended[] = array_merge($prog, [
            'relevance_score' => $relevanceScore,
            'address_gaps' => array_values($matchedGaps)
        ]);
    }

    usort($recommended, fn($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

    return $recommended;
}

/**
 * Persist assessment topic scores into candidate_skills with verified flag.
 * Uses atomic UPSERT (INSERT ... ON DUPLICATE KEY UPDATE) via the uk_candidate_skill unique key.
 * Also refreshes student_profiles.strengths and student_profiles.skill_gaps.
 *
 * @param PDO   $pdo         Database connection
 * @param int   $candidateId Candidate ID
 * @param int   $attemptId   Assessment attempt ID to aggregate scores from
 * @return array{strengths: array, gaps: array} Updated strengths and gaps
 */
function persistAssessmentSkillScores(PDO $pdo, int $candidateId, int $attemptId): array {
    // 1. Aggregate topic performance from this attempt's responses
    $stmt = $pdo->prepare("
        SELECT q.topic, 
               COUNT(*) total_questions, 
               SUM(ar.is_correct) correct_count,
               SUM(ar.score_earned) total_score_earned
        FROM assessment_responses ar
        JOIN assessment_questions q ON q.id = ar.question_id
        WHERE ar.attempt_id = ?
        GROUP BY q.topic
    ");
    $stmt->execute([$attemptId]);
    $topicStats = $stmt->fetchAll();

    if (empty($topicStats)) {
        return ['strengths' => [], 'gaps' => []];
    }

    // 2. For each topic, resolve to a skill_id and UPSERT into candidate_skills
    $upsertStmt = $pdo->prepare("
        INSERT INTO candidate_skills (candidate_id, skill_id, proficiency, score, verified, source, last_assessed_at)
        VALUES (?, ?, ?, ?, 1, 'Assessment', CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE
            score = GREATEST(score, VALUES(score)),
            proficiency = VALUES(proficiency),
            verified = 1,
            source = 'Assessment',
            last_assessed_at = CURRENT_TIMESTAMP
    ");

    $findSkillStmt = $pdo->prepare("SELECT id FROM skills WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $createSkillStmt = $pdo->prepare("INSERT INTO skills (name, category, demand_level) VALUES (?, 'Technical', 'Medium')");

    $strengths = [];
    $gaps = [];

    foreach ($topicStats as $topic) {
        $topicName = trim($topic['topic']);
        if ($topicName === '') continue;

        $total = (int)$topic['total_questions'];
        $correct = (int)$topic['correct_count'];
        $pct = $total > 0 ? round(($correct / $total) * 100, 2) : 0.0;

        // Determine proficiency label
        $proficiency = match (true) {
            $pct >= 90 => 'Expert',
            $pct >= 75 => 'Advanced',
            $pct >= 60 => 'Intermediate',
            default => 'Beginner'
        };

        // Resolve skill_id
        $findSkillStmt->execute([$topicName]);
        $skillId = $findSkillStmt->fetchColumn();
        if (!$skillId) {
            $createSkillStmt->execute([ucfirst($topicName)]);
            $skillId = (int)$pdo->lastInsertId();
        } else {
            $skillId = (int)$skillId;
        }

        // UPSERT
        $upsertStmt->execute([$candidateId, $skillId, $proficiency, $pct]);

        // Categorize
        $entry = ['name' => ucfirst($topicName), 'score' => $pct];
        if ($pct >= 70) {
            $strengths[] = $entry;
        } else {
            $gaps[] = $entry;
        }
    }

    // 3. Update student_profiles strengths and skill_gaps
    usort($strengths, fn($a, $b) => $b['score'] <=> $a['score']);
    usort($gaps, fn($a, $b) => $a['score'] <=> $b['score']);

    $strengthNames = implode(', ', array_column($strengths, 'name'));
    $gapNames = implode(', ', array_column($gaps, 'name'));

    $updateProfile = $pdo->prepare("
        UPDATE student_profiles 
        SET strengths = ?, skill_gaps = ?
        WHERE candidate_id = ?
    ");
    $updateProfile->execute([$strengthNames, $gapNames, $candidateId]);

    return ['strengths' => $strengths, 'gaps' => $gaps];
}

/**
 * Authoritative Explainable Matching Engine.
 * Returns a 100-point breakdown for a candidate vs an opportunity:
 *   - Skill Compatibility: up to 50 points
 *   - Qualification & Degree: up to 15 points
 *   - CGPA Match: up to 10 points
 *   - Assessment Verification: up to 15 points
 *   - Branch Match: up to 10 points
 *
 * @param PDO $pdo
 * @param int $candidateId
 * @param int $opportunityId
 * @return array Detailed match breakdown
 */
/**
 * Resolve required skills for an opportunity from opportunity_skills or fallback text field.
 * @return array<int, array{name: string, min_score: int, importance: string}>
 */
function getOpportunityRequiredSkills(PDO $pdo, int $opportunityId, ?array $opp = null): array {
    $skills = [];
    if (tableExists($pdo, 'opportunity_skills') && tableExists($pdo, 'skills')) {
        $stmt = $pdo->prepare("
            SELECT s.name, os.importance,
                   COALESCE(os.min_score_required, CASE WHEN os.importance = 'Required' THEN 70 ELSE 60 END) AS min_score
            FROM opportunity_skills os
            JOIN skills s ON s.id = os.skill_id
            WHERE os.opportunity_id = ?
            ORDER BY s.name
        ");
        $stmt->execute([$opportunityId]);
        foreach ($stmt->fetchAll() as $row) {
            $skills[] = [
                'name' => trim((string)$row['name']),
                'min_score' => (int)$row['min_score'],
                'importance' => (string)($row['importance'] ?? 'Required'),
            ];
        }
    }
    if ($skills) {
        return $skills;
    }
    if (is_array($opp)) {
        $text = (string)($opp['required_skills'] ?? '');
    } else {
        $stmt = $pdo->prepare("SELECT required_skills FROM opportunities WHERE id = ? LIMIT 1");
        $stmt->execute([$opportunityId]);
        $row = $stmt->fetch() ?: [];
        $text = (string)($row['required_skills'] ?? '');
    }
    foreach (array_filter(array_map('trim', preg_split('/[,\n]+/', $text) ?: [])) as $name) {
        $skills[] = ['name' => $name, 'min_score' => 70, 'importance' => 'Required'];
    }
    return $skills;
}

function calculateOpportunityMatchDetails(PDO $pdo, int $candidateId, int $opportunityId): array {
    // Fetch candidate data
    $candStmt = $pdo->prepare("
        SELECT c.*, sp.degree, sp.branch, sp.cgpa, sp.career_interest, sp.preferred_role
        FROM candidates c
        LEFT JOIN student_profiles sp ON sp.candidate_id = c.id
        WHERE c.id = ? LIMIT 1
    ");
    $candStmt->execute([$candidateId]);
    $cand = $candStmt->fetch();
    if (!$cand) {
        return ['total_score' => 0, 'eligible' => false, 'reasons' => ['Candidate not found'], 'reason' => 'Candidate not found'];
    }

    // Fetch opportunity data
    $oppStmt = $pdo->prepare("SELECT * FROM opportunities WHERE id = ? LIMIT 1");
    $oppStmt->execute([$opportunityId]);
    $opp = $oppStmt->fetch();
    if (!$opp) {
        return ['total_score' => 0, 'eligible' => false, 'reasons' => ['Opportunity not found'], 'reason' => 'Opportunity not found'];
    }

    // ── 1. SKILL COMPATIBILITY (50 pts) ──
    $requiredSkillRows = getOpportunityRequiredSkills($pdo, $opportunityId, $opp);

    // Fetch candidate's verified skills with scores
    $skillStmt = $pdo->prepare("
        SELECT s.name, cs.score, cs.verified, cs.proficiency 
        FROM candidate_skills cs 
        JOIN skills s ON s.id = cs.skill_id 
        WHERE cs.candidate_id = ?
    ");
    $skillStmt->execute([$candidateId]);
    $candidateSkills = [];
    foreach ($skillStmt->fetchAll() as $row) {
        $candidateSkills[mb_strtolower(trim($row['name']))] = $row;
    }

    $matchedSkills = [];
    $missingSkills = [];
    $skillGaps = [];
    $skillScoreSum = 0;

    if (!empty($requiredSkillRows)) {
        foreach ($requiredSkillRows as $reqRow) {
            $req = $reqRow['name'];
            $minScoreRequired = (int)$reqRow['min_score'];
            $key = mb_strtolower($req);
            if (isset($candidateSkills[$key])) {
                $score = (float)($candidateSkills[$key]['score'] ?? 0);
                $meets = $score >= $minScoreRequired;
                $entry = [
                    'name' => $req,
                    'score' => $score,
                    'min_required' => $minScoreRequired,
                    'verified' => (bool)($candidateSkills[$key]['verified'] ?? false),
                    'meets_threshold' => $meets,
                ];
                $matchedSkills[] = $entry;
                if (!$meets) {
                    $skillGaps[] = $entry;
                }
                $skillScoreSum += min(100, $score);
            } else {
                $missingSkills[] = $req;
                $skillGaps[] = ['name' => $req, 'score' => 0, 'min_required' => $minScoreRequired, 'verified' => false, 'meets_threshold' => false];
            }
        }
        $maxPossible = count($requiredSkillRows) * 100;
        $skillPct = $maxPossible > 0 ? ($skillScoreSum / $maxPossible) : 0;
        $skillPoints = round($skillPct * 50, 1);
    } else {
        $skillPoints = 50;
        $skillPct = 1.0;
    }

    // ── 2. QUALIFICATION & DEGREE (15 pts) ──
    $qualPoints = 0;
    $candQual = mb_strtolower(trim($cand['qualification'] ?? $cand['degree'] ?? ''));
    $oppQual = mb_strtolower(trim($opp['qualification'] ?? ''));
    if ($oppQual === '' || $candQual === '') {
        $qualPoints = 10; // Neutral - not specified
    } elseif (str_contains($candQual, $oppQual) || str_contains($oppQual, $candQual)) {
        $qualPoints = 15;
    } elseif (str_contains($candQual, 'b.tech') || str_contains($candQual, 'bachelor')) {
        $qualPoints = 10; // Partial match for general degree
    } else {
        $qualPoints = 5;
    }

    // ── 3. CGPA (10 pts) ──
    $cgpaPoints = 0;
    $cgpa = (float)($cand['cgpa'] ?? 0);
    $eligText = mb_strtolower(trim($opp['eligibility'] ?? ''));
    $requiredCgpa = 0;
    if (preg_match('/(\d+\.?\d*)\s*(?:cgpa|gpa)/i', $eligText, $m)) {
        $requiredCgpa = (float)$m[1];
    }
    if ($requiredCgpa > 0) {
        if ($cgpa >= $requiredCgpa) {
            $cgpaPoints = 10;
        } elseif ($cgpa >= $requiredCgpa * 0.9) {
            $cgpaPoints = 7;
        } else {
            $cgpaPoints = 3;
        }
    } else {
        // No CGPA requirement
        $cgpaPoints = ($cgpa >= 7.0) ? 10 : (($cgpa >= 5.0) ? 7 : 4);
    }

    // ── 4. ASSESSMENT VERIFICATION (15 pts) ──
    $assessPoints = 0;
    $verifiedCount = 0;
    $totalReqSkills = count($requiredSkillRows);
    foreach ($matchedSkills as $ms) {
        if ($ms['verified'] && $ms['meets_threshold']) {
            $verifiedCount++;
        }
    }
    if ($totalReqSkills > 0) {
        $assessPct = $verifiedCount / $totalReqSkills;
        $assessPoints = round($assessPct * 15, 1);
    } else {
        $assessPoints = 8;
    }

    // ── 5. BRANCH MATCH (10 pts) ──
    $branchPoints = 0;
    $candBranch = mb_strtolower(trim($cand['branch'] ?? ''));
    $oppDept = mb_strtolower(trim($opp['department'] ?? ''));
    $allowedBranches = mb_strtolower(trim((string)($opp['allowed_branches'] ?? '')));
    if ($allowedBranches !== '') {
        $allowed = array_filter(array_map('trim', preg_split('/[,\n|\/]+/', $allowedBranches) ?: []));
        $branchMatch = false;
        foreach ($allowed as $ab) {
            if ($ab !== '' && (str_contains($candBranch, $ab) || str_contains($ab, $candBranch))) {
                $branchMatch = true;
                break;
            }
        }
        $branchPoints = $branchMatch ? 10 : 3;
    } elseif ($oppDept === '' || $candBranch === '') {
        $branchPoints = 7;
    } elseif (str_contains($candBranch, $oppDept) || str_contains($oppDept, $candBranch)) {
        $branchPoints = 10;
    } elseif (str_contains($candBranch, 'computer') || str_contains($candBranch, 'information') || str_contains($candBranch, 'software')) {
        $branchPoints = 7;
    } else {
        $branchPoints = 3;
    }

    // CGPA from dedicated column when present
    if (!empty($opp['min_cgpa']) && (float)$opp['min_cgpa'] > 0) {
        $requiredCgpa = (float)$opp['min_cgpa'];
        if ($cgpa >= $requiredCgpa) {
            $cgpaPoints = 10;
        } elseif ($cgpa >= $requiredCgpa * 0.9) {
            $cgpaPoints = 7;
        } else {
            $cgpaPoints = 3;
        }
    }

    $reasons = [];
    if ($missingSkills) {
        $reasons[] = 'Missing skills: ' . implode(', ', $missingSkills);
    }
    if ($skillGaps) {
        $gapNames = array_map(fn($g) => $g['name'] . ' (' . round((float)$g['score']) . '% < ' . (int)$g['min_required'] . '%)', $skillGaps);
        $reasons[] = 'Below threshold: ' . implode(', ', $gapNames);
    }

    // ── TOTAL ──
    $totalScore = round($skillPoints + $qualPoints + $cgpaPoints + $assessPoints + $branchPoints, 1);
    $eligible = $totalScore >= 40 && count($missingSkills) <= max(1, (int)floor($totalReqSkills * 0.5));

    return [
        'total_score' => $totalScore,
        'eligible' => $eligible,
        'skill_score' => $skillPoints,
        'qualification_score' => $qualPoints,
        'cgpa_score' => $cgpaPoints,
        'assessment_score' => $assessPoints,
        'branch_score' => $branchPoints,
        'breakdown' => [
            'skill_compatibility' => ['points' => $skillPoints, 'max' => 50, 'matched' => count($matchedSkills), 'required' => $totalReqSkills],
            'qualification' => ['points' => $qualPoints, 'max' => 15],
            'cgpa' => ['points' => $cgpaPoints, 'max' => 10, 'value' => $cgpa],
            'assessment' => ['points' => $assessPoints, 'max' => 15, 'verified_count' => $verifiedCount],
            'branch' => ['points' => $branchPoints, 'max' => 10]
        ],
        'matched_skills' => $matchedSkills,
        'missing_skills' => $missingSkills,
        'skill_gaps' => $skillGaps,
        'reasons' => $reasons,
        'eligibility_flags' => [
            'has_missing_skills' => count($missingSkills) > 0,
            'has_score_gaps' => count($skillGaps) > 0,
            'meets_min_total' => $totalScore >= 40,
        ],
        'candidate_name' => $cand['name'] ?? '',
        'opportunity_title' => $opp['title'] ?? ''
    ];
}

/**
 * Mark an assessment attempt completed and persist verified skills when enough answers exist.
 */
function completeAssessmentAttempt(PDO $pdo, int $candidateId, int $attemptId): array {
    $totalRequired = defined('ASSESSMENT_QUESTIONS_PER_ATTEMPT') ? (int)ASSESSMENT_QUESTIONS_PER_ATTEMPT : 5;
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM assessment_responses WHERE attempt_id = ?");
    $countStmt->execute([$attemptId]);
    $answered = (int)$countStmt->fetchColumn();
    if ($answered < $totalRequired) {
        return ['completed' => false, 'answered' => $answered, 'required' => $totalRequired];
    }

    $pdo->prepare("UPDATE assessment_attempts SET status = 'Completed', completed_at = CURRENT_TIMESTAMP WHERE id = ? AND candidate_id = ?")
        ->execute([$attemptId, $candidateId]);

    $totStmt = $pdo->prepare("SELECT SUM(score_earned) FROM assessment_responses WHERE attempt_id = ?");
    $totStmt->execute([$attemptId]);
    $totalEarned = (float)($totStmt->fetchColumn() ?: 0.0);
    $maxPossible = $answered * 20.0;
    $finalScore = $maxPossible > 0 ? min(100.0, round(($totalEarned / $maxPossible) * 100, 1)) : 0.0;

    $persistedData = persistAssessmentSkillScores($pdo, $candidateId, $attemptId);
    $profileData = getStudentSkillProfile($pdo, $candidateId);

    try {
        sbNotifyCandidate($pdo, $candidateId, 'Assessment completed',
            'Your assessment is complete. Final score: ' . $finalScore . '%. Your verified Skill Profile and skill-gap recommendations have been updated.',
            'assessment', 'assessment_attempt', $attemptId);
    } catch (Throwable $ignored) {
        // Activity delivery must not interrupt assessment completion.
    }

    return [
        'completed' => true,
        'final_score' => $finalScore,
        'strengths' => $profileData['strengths'],
        'gaps' => $profileData['gaps'],
        'persisted_strengths' => $persistedData['strengths'],
        'persisted_gaps' => $persistedData['gaps'],
    ];
}
