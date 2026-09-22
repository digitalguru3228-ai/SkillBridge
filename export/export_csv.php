<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

requireLogin();

$user = currentUser();
$role = $user['role'] ?? '';
$pdo = db();

$type = trim($_GET['type'] ?? 'applications');
$filename = $type . '_export_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

if ($type === 'applications') {
    fputcsv($output, ['Application ID', 'Candidate Name', 'Institute', 'Opportunity Title', 'Opportunity Type', 'Match Score', 'Status', 'Applied Date']);
    
    if ($role === 'industry') {
        $company_id = (int)($_SESSION['company_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT a.id, c.name candidate_name, inst.name institute_name, o.title opportunity_title, o.type opportunity_type, a.match_score, a.status, a.applied_at FROM applications a JOIN candidates c ON c.id=a.candidate_id LEFT JOIN institutes inst ON inst.id=c.institute_id JOIN opportunities o ON o.id=a.opportunity_id WHERE o.company_id = ? ORDER BY a.id DESC");
        $stmt->execute([$company_id]);
    } elseif ($role === 'institute') {
        $institute_id = (int)($_SESSION['institute_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT a.id, c.name candidate_name, inst.name institute_name, o.title opportunity_title, o.type opportunity_type, a.match_score, a.status, a.applied_at FROM applications a JOIN candidates c ON c.id=a.candidate_id LEFT JOIN institutes inst ON inst.id=c.institute_id JOIN opportunities o ON o.id=a.opportunity_id WHERE c.institute_id = ? ORDER BY a.id DESC");
        $stmt->execute([$institute_id]);
    } elseif ($role === 'student') {
        $candidate_id = (int)($_SESSION['candidate_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT a.id, c.name candidate_name, inst.name institute_name, o.title opportunity_title, o.type opportunity_type, a.match_score, a.status, a.applied_at FROM applications a JOIN candidates c ON c.id=a.candidate_id LEFT JOIN institutes inst ON inst.id=c.institute_id JOIN opportunities o ON o.id=a.opportunity_id WHERE a.candidate_id = ? ORDER BY a.id DESC");
        $stmt->execute([$candidate_id]);
    } else {
        $stmt = $pdo->prepare("SELECT a.id, c.name candidate_name, inst.name institute_name, o.title opportunity_title, o.type opportunity_type, a.match_score, a.status, a.applied_at FROM applications a JOIN candidates c ON c.id=a.candidate_id LEFT JOIN institutes inst ON inst.id=c.institute_id JOIN opportunities o ON o.id=a.opportunity_id ORDER BY a.id DESC");
        $stmt->execute();
    }
    
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['id'],
            $row['candidate_name'],
            $row['institute_name'] ?: 'N/A',
            $row['opportunity_title'],
            $row['opportunity_type'],
            round((float)$row['match_score']) . '%',
            $row['status'],
            $row['applied_at']
        ]);
    }
} elseif ($type === 'students' || $type === 'candidates') {
    fputcsv($output, ['Candidate ID', 'Name', 'Email', 'Institute', 'Qualification', 'Experience (Years)', 'Match Score', 'Skills']);
    
    if ($role === 'institute') {
        $institute_id = (int)($_SESSION['institute_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT c.id, c.name, c.email, inst.name institute_name, c.qualification, c.experience_years, c.match_score, c.skills FROM candidates c LEFT JOIN institutes inst ON inst.id=c.institute_id WHERE c.institute_id = ? ORDER BY c.id DESC");
        $stmt->execute([$institute_id]);
    } else {
        $stmt = $pdo->query("SELECT c.id, c.name, c.email, inst.name institute_name, c.qualification, c.experience_years, c.match_score, c.skills FROM candidates c LEFT JOIN institutes inst ON inst.id=c.institute_id ORDER BY c.id DESC");
    }
    
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['id'],
            $row['name'],
            $row['email'],
            $row['institute_name'] ?: 'N/A',
            $row['qualification'] ?: 'N/A',
            $row['experience_years'] ?? 0,
            round((float)($row['match_score'] ?? 0)) . '%',
            $row['skills'] ?: 'N/A'
        ]);
    }
} elseif ($type === 'learning_progress') {
    fputcsv($output, ['Enrollment ID', 'Program Title', 'Student Name', 'Total Modules', 'Completed Modules', 'Progress %', 'Status', 'Enrollment Date']);
    
    if ($role === 'industry') {
        $company_id = (int)($_SESSION['company_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT le.id, lp.title program_title, c.name student_name, COUNT(lpm.id) tot_mods, SUM(CASE WHEN lpr.status='Completed' THEN 1 ELSE 0 END) comp_mods, le.status, le.enrolled_at FROM learning_enrollments le JOIN learning_programs lp ON lp.id=le.program_id JOIN candidates c ON c.id=le.candidate_id LEFT JOIN learning_program_modules lpm ON lpm.program_id=lp.id LEFT JOIN learning_progress lpr ON lpr.enrollment_id=le.id AND lpr.module_id=lpm.id WHERE lp.company_id = ? GROUP BY le.id ORDER BY le.id DESC");
        $stmt->execute([$company_id]);
    } else {
        $stmt = $pdo->query("SELECT le.id, lp.title program_title, c.name student_name, COUNT(lpm.id) tot_mods, SUM(CASE WHEN lpr.status='Completed' THEN 1 ELSE 0 END) comp_mods, le.status, le.enrolled_at FROM learning_enrollments le JOIN learning_programs lp ON lp.id=le.program_id JOIN candidates c ON c.id=le.candidate_id LEFT JOIN learning_program_modules lpm ON lpm.program_id=lp.id LEFT JOIN learning_progress lpr ON lpr.enrollment_id=le.id AND lpr.module_id=lpm.id GROUP BY le.id ORDER BY le.id DESC");
    }
    
    while ($row = $stmt->fetch()) {
        $tot = (int)$row['tot_mods'];
        $comp = (int)$row['comp_mods'];
        $pct = $tot > 0 ? round(($comp / $tot) * 100) : 0;
        fputcsv($output, [
            $row['id'],
            $row['program_title'],
            $row['student_name'],
            $tot,
            $comp,
            $pct . '%',
            $row['status'],
            $row['enrolled_at']
        ]);
    }
} else {
    fputcsv($output, ['Export Error', 'Invalid export type requested.']);
}

fclose($output);
exit;
