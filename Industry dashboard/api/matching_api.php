<?php
declare(strict_types=1);
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/skill_mapping.php';
requireRole('industry');

header('Content-Type: application/json; charset=utf-8');

$pdo = db();
$companyId = (int)($_SESSION['company_id'] ?? 0);
$opportunityId = (int)($_GET['opportunity_id'] ?? $_POST['opportunity_id'] ?? 0);
$candidateId = (int)($_GET['candidate_id'] ?? $_POST['candidate_id'] ?? 0);

if ($companyId <= 0) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Industry account is not linked to a company.']);
    exit;
}

try {
    if ($opportunityId <= 0) {
        throw new InvalidArgumentException('Opportunity is required.');
    }

    $oppCheck = $pdo->prepare("SELECT id, title FROM opportunities WHERE id = ? AND company_id = ? LIMIT 1");
    $oppCheck->execute([$opportunityId, $companyId]);
    $opp = $oppCheck->fetch();
    if (!$opp) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Opportunity not found for your company.']);
        exit;
    }

    if ($candidateId > 0) {
        $match = calculateOpportunityMatchDetails($pdo, $candidateId, $opportunityId);
        echo json_encode(['ok' => true, 'opportunity' => $opp, 'match' => $match], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $candStmt = $pdo->query("
        SELECT c.id, c.name, COALESCE(i.name, c.institute) AS institute_name
        FROM candidates c
        LEFT JOIN institutes i ON i.id = c.institute_id
        ORDER BY c.name
    ");
    $results = [];
    foreach ($candStmt->fetchAll() as $cand) {
        $match = calculateOpportunityMatchDetails($pdo, (int)$cand['id'], $opportunityId);
        $results[] = [
            'candidate_id' => (int)$cand['id'],
            'name' => $cand['name'],
            'institute_name' => $cand['institute_name'],
            'total_score' => $match['total_score'],
            'eligible' => $match['eligible'],
            'matched_skills' => $match['matched_skills'],
            'missing_skills' => $match['missing_skills'],
            'skill_gaps' => $match['skill_gaps'] ?? [],
            'breakdown' => $match['breakdown'],
            'reasons' => $match['reasons'] ?? [],
        ];
    }

    usort($results, fn($a, $b) => ($b['total_score'] <=> $a['total_score']) ?: strcmp((string)$a['name'], (string)$b['name']));

    echo json_encode([
        'ok' => true,
        'opportunity' => $opp,
        'candidates' => $results,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Unable to calculate matches. Please try again.']);
}
