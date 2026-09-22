<?php
declare(strict_types=1);
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../db.php';
requireRole('institute');
header('Content-Type: application/json; charset=utf-8');

function norm_text(string $v): string {
    $v = strtolower(trim($v));
    $v = preg_replace('/[^a-z0-9+#.\- ]+/i', ' ', $v) ?? $v;
    return trim(preg_replace('/\s+/', ' ', $v) ?? $v);
}
function list_values(?string $v): array {
    if (!$v) return [];
    $out=[];
    foreach (preg_split('/[,;|\n]+/', $v) ?: [] as $x) {
        $x=trim($x); if($x!=='') $out[]=norm_text($x);
    }
    return array_values(array_unique($out));
}
function matches_any(string $value, array $required): bool {
    $value=norm_text($value);
    if($value==='') return false;
    $cv=canonical_branch($value);
    foreach($required as $r){ $cr=canonical_branch($r); if($cv===$cr || str_contains($cv,$cr) || str_contains($cr,$cv)) return true; }
    return false;
}

// Qualification is an education-level requirement. Branch/specialization is
// evaluated separately through allowed_branches. This prevents a requirement
// such as "B.E. / B.Tech" from incorrectly rejecting "B.E. IT".
function qualification_families(string $value): array {
    $v=norm_text($value);
    $v=str_replace(['b e','b e.','b. e.','b tech','btech','m e','m e.','m tech','mtech'],['b.e','b.e','b.e','b.tech','b.tech','m.e','m.e','m.tech','m.tech'],$v);
    $families=[];
    if(preg_match('/\bb\.?e\.?\b/i',$v) || str_contains($v,'b.e')) $families[]='b.e';
    if(preg_match('/\bb\.?tech\b/i',$v) || str_contains($v,'b.tech')) $families[]='b.tech';
    if(preg_match('/\bm\.?e\.?\b/i',$v) || str_contains($v,'m.e')) $families[]='m.e';
    if(preg_match('/\bm\.?tech\b/i',$v) || str_contains($v,'m.tech')) $families[]='m.tech';
    if(str_contains($v,'mca')) $families[]='mca';
    if(str_contains($v,'bca')) $families[]='bca';
    if(str_contains($v,'b.sc') || str_contains($v,'bsc')) $families[]='b.sc';
    if(str_contains($v,'m.sc') || str_contains($v,'msc')) $families[]='m.sc';
    if(str_contains($v,'diploma')) $families[]='diploma';
    return array_values(array_unique($families));
}
function qualification_matches(string $studentDegree, array $required): bool {
    if(!$required) return true;
    $student=norm_text($studentDegree);
    if($student==='') return false;
    $sf=qualification_families($student);
    foreach($required as $r){
        $rf=qualification_families($r);
        if($rf && array_intersect($sf,$rf)) return true;
        if($student===$r || str_contains($student,$r) || str_contains($r,$student)) return true;
    }
    return false;
}
function branch_from_degree(string $degree): string {
    $v=trim($degree);
    if($v==='') return '';
    $parts=preg_split('/\s*\/\s*|\s*\|\s*|\s+-\s+/',$v) ?: [];
    if(count($parts)>1) return trim((string)end($parts));
    foreach(['computer engineering','information technology','computer science and engineering','computer science','artificial intelligence and machine learning','ai/ml','mechanical engineering','civil engineering','electrical engineering','electronics and communication engineering','electronics engineering'] as $b){
        if(str_contains(norm_text($v),norm_text($b))) return ucwords($b);
    }
    return '';
}
function canonical_branch(string $v): string {
    $v=norm_text($v);
    $aliases=[
        'it'=>'information technology','information tech'=>'information technology','information technology'=>'information technology',
        'cse'=>'computer science and engineering','computer science'=>'computer science and engineering','computer science engineering'=>'computer science and engineering',
        'ce'=>'computer engineering','computer engineering'=>'computer engineering',
        'aiml'=>'artificial intelligence and machine learning','ai ml'=>'artificial intelligence and machine learning','ai/ml'=>'artificial intelligence and machine learning',
        'ece'=>'electronics and communication engineering','electronics and communication'=>'electronics and communication engineering',
        'eee'=>'electrical and electronics engineering'
    ];
    return $aliases[$v] ?? $v;
}
function canonical_skill(string $v): string {
    $v=norm_text($v);
    $aliases=[
        'node js'=>'node.js','nodejs'=>'node.js','reactjs'=>'react','react js'=>'react',
        'javascript'=>'javascript','js'=>'javascript','typescript'=>'typescript',
        'python3'=>'python','c plus plus'=>'c++','cpp'=>'c++','sql'=>'sql',
        'mysql'=>'mysql','postgresql'=>'postgresql','postgre sql'=>'postgresql',
        'mongodb'=>'mongodb','mongo db'=>'mongodb','express js'=>'express','expressjs'=>'express'
    ];
    return $aliases[$v] ?? $v;
}
function skill_matches(string $studentSkill, string $requiredSkill): bool {
    $s=canonical_skill($studentSkill); $r=canonical_skill($requiredSkill);
    if($s==='' || $r==='') return false;
    return $s===$r || str_contains($s,$r) || str_contains($r,$s);
}
function is_verified_skill(array $skill): bool {
    return !array_key_exists('verified',$skill) || (int)$skill['verified']===1;
}
try {
    $pdo=db();
    $iid=(int)($_SESSION['institute_id']??0);
    $oppId=(int)($_GET['opportunity_id']??0);
    if($iid<=0 || $oppId<=0){ http_response_code(400); echo json_encode(['ok'=>false,'message'=>'Invalid institute or opportunity.']); exit; }

    $oppStmt=$pdo->prepare('SELECT * FROM opportunities WHERE id=? LIMIT 1');
    $oppStmt->execute([$oppId]);
    $opp=$oppStmt->fetch();
    if(!$opp){ http_response_code(404); echo json_encode(['ok'=>false,'message'=>'Opportunity not found.']); exit; }

    $minCgpa=(isset($opp['min_cgpa']) && is_numeric($opp['min_cgpa'])) ? (float)$opp['min_cgpa'] : 0.0;
    $requiredQual=list_values((string)($opp['qualification']??''));
    $requiredBranches=list_values((string)($opp['allowed_branches']??''));
    $requiredSkills=list_values((string)($opp['required_skills']??''));

    if(tableExists($pdo,'opportunity_skills') && tableExists($pdo,'skills')){
        $q=$pdo->prepare('SELECT s.name FROM opportunity_skills os JOIN skills s ON s.id=os.skill_id WHERE os.opportunity_id=?');
        $q->execute([$oppId]);
        $normalizedDbSkills=array_values(array_filter(array_map(fn($r)=>norm_text((string)$r['name']),$q->fetchAll())));
        if($normalizedDbSkills) $requiredSkills=$normalizedDbSkills;
    }

    $q=$pdo->prepare('SELECT c.*, sp.degree, sp.branch, sp.semester, sp.cgpa, sp.career_interest FROM candidates c LEFT JOIN student_profiles sp ON sp.candidate_id=c.id WHERE c.institute_id=? ORDER BY c.name');
    $q->execute([$iid]);
    $students=$q->fetchAll();

    $skillMap=[];
    if(tableExists($pdo,'candidate_skills') && tableExists($pdo,'skills')){
        $sq=$pdo->prepare('SELECT cs.candidate_id,s.name,cs.proficiency,cs.score,cs.verified FROM candidate_skills cs JOIN skills s ON s.id=cs.skill_id WHERE cs.candidate_id=?');
        foreach($students as $st){
            $sq->execute([(int)$st['id']]);
            $rows=$sq->fetchAll();
            $skillMap[(int)$st['id']]=array_map(fn($r)=>['name'=>(string)$r['name'],'proficiency'=>$r['proficiency']??null,'score'=>$r['score']??null,'verified'=>(int)($r['verified']??0)],$rows);
        }
    }

    $eligible=[]; $ineligible=[];
    foreach($students as $st){
        $reasons=[];
        $degree=trim((string)($st['degree']??$st['qualification']??''));
        $branch=trim((string)($st['branch']??''));
        if($branch==='') $branch=branch_from_degree($degree);
        $cgpa=($st['cgpa']!==null && $st['cgpa']!=='') ? (float)$st['cgpa'] : null;
        $skills=$skillMap[(int)$st['id']]??[];
        if(!$skills && !empty($st['skills'])){
            $raw=json_decode((string)$st['skills'],true);
            if(is_array($raw)) foreach($raw as $v) if(is_string($v)&&trim($v)!=='') $skills[]=['name'=>trim($v),'proficiency'=>null,'score'=>null,'verified'=>0];
        }
        $skillNames=array_map(fn($x)=>norm_text((string)$x['name']),$skills);
        if($minCgpa>0 && ($cgpa===null || $cgpa<$minCgpa)) $reasons[]=$cgpa===null ? 'CGPA not provided' : 'CGPA below requirement ('.$cgpa.' < '.$minCgpa.')';
        if($requiredBranches && !matches_any($branch,$requiredBranches)) $reasons[]=$branch==='' ? 'Branch not provided' : 'Branch mismatch ('.$branch.')';
        if($requiredQual && !qualification_matches($degree,$requiredQual)) $reasons[]=$degree==='' ? 'Degree / qualification not provided' : 'Qualification mismatch ('.$degree.')';
        $missing=[];
        foreach($requiredSkills as $rs){
            $ok=false;
            foreach($skills as $skill){
                if(!is_verified_skill($skill)) continue;
                if(skill_matches((string)$skill['name'], $rs)){ $ok=true; break; }
            }
            // Existing legacy skill records may not have a verification flag.
            if(!$ok){
                foreach($skills as $skill){ if(skill_matches((string)$skill['name'], $rs)){ $ok=true; break; } }
            }
            if(!$ok) $missing[]=$rs;
        }
        if($missing) $reasons[]='Missing required skills: '.implode(', ',$missing);
        $row=['id'=>(int)$st['id'],'name'=>(string)($st['name']??'Unnamed'),'email'=>(string)($st['email']??''),'degree'=>$degree?:null,'branch'=>$branch?:null,'semester'=>$st['semester']!==null?(int)$st['semester']:null,'cgpa'=>$cgpa,'skills'=>implode(', ',array_map(fn($x)=>(string)$x['name'],$skills)), 'verified_skills'=>implode(', ',array_map(fn($x)=>(string)$x['name'],array_values(array_filter($skills,'is_verified_skill'))))];
        if($reasons){$ineligible[]=['student'=>$row,'reasons'=>$reasons];}else{$eligible[]=$row;}
    }
    echo json_encode(['ok'=>true,'opportunity'=>['id'=>$oppId,'title'=>(string)($opp['title']??''),'min_cgpa'=>$minCgpa,'qualification'=>$opp['qualification']??null,'allowed_branches'=>$opp['allowed_branches']??null,'required_skills'=>$requiredSkills],'eligible'=>$eligible,'ineligible'=>$ineligible],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e){
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Unable to calculate live eligibility.']);
}
