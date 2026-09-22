<?php
declare(strict_types=1);

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

function calculateOpportunityMatch(array $studentSkillNames, string $requiredSkillsText): array {
    $req = array_filter(array_map('trim', preg_split('/[,\n]+/', $requiredSkillsText) ?: []));
    if (!$req) {
        return ['match_pct' => 100, 'matched' => [], 'missing' => []];
    }
    
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

    $pct = round((count($matched) / count($req)) * 100);

    return [
        'match_pct' => $pct,
        'matched' => $matched,
        'missing' => $missing
    ];
}

function getRecommendedOpportunities(PDO $pdo, int $candidateId): array {
    $candidate = $pdo->prepare("SELECT c.*, COALESCE((SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') FROM candidate_skills cs JOIN skills s ON s.id=cs.skill_id WHERE cs.candidate_id=c.id), c.skills, '') skill_text FROM candidates c WHERE c.id = ? LIMIT 1");
    $candidate->execute([$candidateId]);
    $cand = $candidate->fetch() ?: [];

    $studentSkills = array_filter(array_map('trim', preg_split('/[,\n]+/', (string)($cand['skill_text'] ?? '')) ?: []));

    $opportunities = $pdo->query("SELECT o.*, c.name company_name FROM opportunities o JOIN companies c ON c.id=o.company_id WHERE o.status = 'Active' ORDER BY o.created_at DESC")->fetchAll();

    $results = [];
    foreach ($opportunities as $opp) {
        $skillsText = $opp['required_skills'] ?: '';
        $match = calculateOpportunityMatch($studentSkills, $skillsText);
        $results[] = array_merge($opp, [
            'match_pct' => $match['match_pct'],
            'matched_skills' => $match['matched'],
            'missing_skills' => $match['missing']
        ]);
    }

    usort($results, fn($a, $b) => $b['match_pct'] <=> $a['match_pct']);

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
