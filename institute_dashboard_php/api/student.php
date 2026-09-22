<?php
declare(strict_types=1);
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../db.php';
requireRole('institute');
header('Content-Type: application/json; charset=utf-8');
try {
    $id=(int)($_GET['id']??0); $iid=(int)($_SESSION['institute_id']??0);
    if($id<=0||$iid<=0){http_response_code(400);echo json_encode(['ok'=>false,'message'=>'Invalid student request.']);exit;}
    $pdo=db();
    $q=$pdo->prepare('SELECT c.*, COALESCE(i.name,c.institute) institute_name, sp.degree, sp.branch, sp.semester, sp.cgpa, sp.career_interest, sp.preferred_role, sp.preferred_industry, sp.linkedin_url, sp.github_url, sp.portfolio_url FROM candidates c LEFT JOIN institutes i ON i.id=c.institute_id LEFT JOIN student_profiles sp ON sp.candidate_id=c.id WHERE c.id=? AND c.institute_id=? LIMIT 1');
    $q->execute([$id,$iid]); $student=$q->fetch();
    if(!$student){http_response_code(404);echo json_encode(['ok'=>false,'message'=>'Student not found in this institute.']);exit;}
    $skills=[];
    if(tableExists($pdo,'candidate_skills')&&tableExists($pdo,'skills')){
        $q=$pdo->prepare('SELECT s.name,s.category,cs.proficiency,cs.score,cs.verified,cs.source FROM candidate_skills cs JOIN skills s ON s.id=cs.skill_id WHERE cs.candidate_id=? ORDER BY s.name');
        $q->execute([$id]);$skills=$q->fetchAll();
    }
    if(!$skills&&!empty($student['skills'])){ $raw=json_decode((string)$student['skills'],true); if(is_array($raw))foreach($raw as $v)if(is_string($v)&&trim($v)!=='')$skills[]=['name'=>trim($v),'category'=>null,'proficiency'=>null,'score'=>null,'verified'=>0,'source'=>'Self']; }
    $applications=[];
    if(tableExists($pdo,'applications')){ $q=$pdo->prepare('SELECT a.match_score,a.status,a.applied_at,o.title FROM applications a LEFT JOIN opportunities o ON o.id=a.opportunity_id WHERE a.candidate_id=? ORDER BY a.applied_at DESC,a.id DESC');$q->execute([$id]);$applications=$q->fetchAll(); }
    echo json_encode(['ok'=>true,'student'=>$student,'skills'=>$skills,'applications'=>$applications],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'message'=>'Unable to load student profile.']);}
