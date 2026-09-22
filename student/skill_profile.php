<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/skill_mapping.php';
requireRole('student');

$pdo = db();
$candidateId = getStudentCandidateId($pdo);

if ($candidateId <= 0) {
    renderUnlinkedCandidateError();
}

// Fetch Candidate Info & Extended Profile
$candStmt = $pdo->prepare("
    SELECT c.*, i.name institute_name, sp.degree, sp.branch, sp.semester, sp.cgpa, sp.career_interest, sp.graduation_year sp_grad_year
    FROM candidates c 
    LEFT JOIN institutes i ON i.id = c.institute_id 
    LEFT JOIN student_profiles sp ON sp.candidate_id = c.id
    WHERE c.id = ? LIMIT 1
");
$candStmt->execute([$candidateId]);
$cand = $candStmt->fetch() ?: [];

// Get Skill Profile Data
$profileData = getStudentSkillProfile($pdo, $candidateId);
$skills = $profileData['skills'];
$strengths = $profileData['strengths'];
$gaps = $profileData['gaps'];

// Calculate Overall Skill Readiness %
$totalScoreSum = 0;
foreach ($skills as $sk) {
    $totalScoreSum += (int)$sk['score'];
}
$overallReadiness = count($skills) > 0 ? round($totalScoreSum / count($skills)) : 0;

// Fetch industry average requirements for comparison
$reqStmt = $pdo->query("SELECT s.name, AVG(COALESCE(os.min_score_required, CASE WHEN os.importance='Required' THEN 75 ELSE 60 END)) avg_req FROM skills s JOIN opportunity_skills os ON os.skill_id=s.id GROUP BY s.id, s.name");
$industryReqs = [];
foreach ($reqStmt->fetchAll() as $r) {
    $industryReqs[mb_strtolower($r['name'])] = round((float)$r['avg_req']);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SkillBridge | Skill Profile & Gap Analyzer</title>
<link rel="stylesheet" href="../Industry%20dashboard/style.css">
<style>
body { background: #f8fafc; }
.profile-shell { max-width: 980px; margin: 40px auto; padding: 0 20px; }
.info-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 28px; margin-bottom: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
.info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; margin-top: 16px; }
.info-item small { color: #64748b; font-size: 12px; display: block; font-weight: 600; text-transform: uppercase; }
.info-item strong { color: #0f172a; font-size: 15px; }
.level-badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
.level-Expert { background: #dbeafe; color: #1e40af; }
.level-Advanced { background: #dcfce7; color: #15803d; }
.level-Intermediate { background: #fef3c7; color: #b45309; }
.level-Beginner { background: #fee2e2; color: #b91c1c; }
.readiness-hero { background: linear-gradient(135deg, #1d4ed8, #2563eb); color: #fff; border-radius: 20px; padding: 28px 36px; margin-bottom: 28px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 25px rgba(37,99,235,0.2); }
.readiness-circle { width: 100px; height: 100px; border-radius: 50%; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); display: flex; flex-direction: column; align-items: center; justify-content: center; border: 3px solid rgba(255,255,255,0.3); }
.readiness-circle strong { font-size: 32px; font-weight: 800; color: #fff; }
.readiness-circle small { font-size: 10px; text-transform: uppercase; opacity: 0.9; }
.gap-card { background: #fff; border: 1px solid #fee2e2; border-left: 4px solid #ef4444; border-radius: 16px; padding: 20px; margin-bottom: 16px; }
.priority-tag { padding: 4px 10px; border-radius: 999px; font-weight: 700; font-size: 11px; text-transform: uppercase; }
.priority-High { background: #fee2e2; color: #b91c1c; }
.priority-Medium { background: #ffedd5; color: #c2410c; }
.priority-Low { background: #fef9c3; color: #a16207; }
</style>
</head>
<body>

<div class="profile-shell">
  <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <a href="index.php" style="color: #2563eb; font-weight: 600; text-decoration: none;">← Back to Student Dashboard</a>
    <a href="assessment.php" class="btn primary" style="text-decoration:none; padding: 8px 18px;">⚡ Take Verified Assessment</a>
  </div>

  <!-- OVERALL SKILL READINESS HERO -->
  <div class="readiness-hero">
    <div>
      <span style="font-size: 11px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; opacity: 0.9;">STUDENT COMPETENCY METRICS</span>
      <h1 style="margin: 4px 0 6px; font-size: 26px; color: #fff;">Overall Skill Readiness</h1>
      <p style="margin: 0; opacity: 0.9; font-size: 14px; max-width: 500px;">Evaluated dynamically from your verified adaptive assessments and technical background.</p>
    </div>
    <div class="readiness-circle">
      <strong><?= $overallReadiness ?>%</strong>
      <small>Readiness</small>
    </div>
  </div>

  <!-- STUDENT METADATA CARD -->
  <div class="info-card">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
      <div>
        <span class="eyebrow">VERIFIED CANDIDATE PROFILE</span>
        <h1 style="margin: 4px 0; font-size: 22px; color: #0f172a;"><?= e($cand['name'] ?? 'Student') ?></h1>
        <p style="margin: 0; color: #64748b; font-size: 14px;"><?= e($cand['institute_name'] ?? 'Institute') ?></p>
      </div>
      <a href="profile_edit.php" class="btn secondary" style="text-decoration:none;">Edit Profile</a>
    </div>

    <div class="info-grid">
      <div class="info-item"><small>Degree / Branch</small><strong><?= e(($cand['degree']?:'B.Tech').' '.($cand['branch']?:'CSE')) ?></strong></div>
      <div class="info-item"><small>Semester / Year</small><strong>Sem <?= e($cand['semester']?:'7') ?> (<?= e($cand['graduation_year']?:'2026') ?>)</strong></div>
      <div class="info-item"><small>CGPA</small><strong><?= e($cand['cgpa']?:'8.4') ?> / 10.0</strong></div>
      <div class="info-item"><small>Career Interest</small><strong><?= e($cand['career_interest']?:$cand['role_name']?:'Software Engineering') ?></strong></div>
      <div class="info-item"><small>Location</small><strong><?= e($cand['location']?:'Ahmedabad') ?></strong></div>
    </div>
  </div>

  <!-- SKILL OVERVIEW TABLE -->
  <div class="page-title">
    <div>
      <span class="eyebrow">SKILL BREAKDOWN</span>
      <h2>Verified Skill Scores vs Industry Requirements</h2>
    </div>
  </div>

  <div class="card table-card" style="margin-bottom: 32px;">
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Skill</th>
            <th>Your Score</th>
            <th>Level</th>
            <th>Industry Requirement</th>
            <th>Gap</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($skills as $sk): 
            $score = (int)$sk['score'];
            $level = $score >= 90 ? 'Expert' : ($score >= 75 ? 'Advanced' : ($score >= 60 ? 'Intermediate' : 'Beginner'));
            $indReq = $industryReqs[mb_strtolower($sk['name'])] ?? 70;
            $gap = max(0, $indReq - $score);
            $isGap = $score < $indReq;
          ?>
            <tr>
              <td><strong><?= e($sk['name']) ?></strong></td>
              <td><b><?= $score ?>%</b></td>
              <td><span class="level-badge level-<?= $level ?>"><?= $level ?></span></td>
              <td><?= $indReq ?>%</td>
              <td><?= $gap > 0 ? "<span style='color:#ef4444; font-weight:700;'>-{$gap}%</span>" : "<span style='color:#10b981; font-weight:700;'>0%</span>" ?></td>
              <td>
                <?php if($isGap): ?>
                  <span class="gap-tag">⚠ Skill Gap</span>
                <?php else: ?>
                  <span class="strength-tag">✓ Strong</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if(!$skills): ?>
            <tr><td colspan="6" style="text-align:center; padding: 24px; color: #64748b;">No skills recorded yet. Complete an assessment to build your skill profile.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- DETAILED SKILL GAP ANALYSIS SECTION -->
  <div class="page-title">
    <div>
      <span class="eyebrow">ACTIONABLE RECOMMENDATIONS</span>
      <h2>Skill Gap Analysis & Closure Roadmap</h2>
      <p>Targeted interventions to bridge identified competency deficits.</p>
    </div>
  </div>

  <div>
    <?php foreach($gaps as $g): 
      $score = (int)$g['score'];
      $indReq = $industryReqs[mb_strtolower($g['name'])] ?? 75;
      $gapPct = max(1, $indReq - $score);
      $priority = $gapPct >= 20 ? 'High' : ($gapPct >= 10 ? 'Medium' : 'Low');
    ?>
      <div class="gap-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
          <div>
            <span class="priority-tag priority-<?= $priority ?>"><?= $priority ?> Priority Gap</span>
            <h3 style="margin: 6px 0 0; font-size: 18px; color: #0f172a;"><?= e($g['name']) ?></h3>
          </div>
          <div style="text-align: right;">
            <span style="font-size: 13px; color: #64748b;">Net Gap</span>
            <strong style="display: block; font-size: 20px; color: #ef4444;">-<?= $gapPct ?>%</strong>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; background: #f8fafc; padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 13px;">
          <div>Current Score: <b><?= $score ?>%</b></div>
          <div>Industry Required Level: <b><?= $indReq ?>%</b></div>
        </div>

        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
          <a href="practice_assessment.php?topic=<?= urlencode($g['name']) ?>" class="btn secondary" style="padding: 6px 14px; font-size: 12px; text-decoration: none;">⚡ Practice <?= e($g['name']) ?> Assessment</a>
          <a href="learning.php" class="btn primary" style="padding: 6px 14px; font-size: 12px; text-decoration: none;">🎓 Join <?= e($g['name']) ?> Learning Program</a>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if(!$gaps): ?>
      <div class="card empty-state" style="padding: 32px; text-align: center;">
        <span style="font-size: 32px;">🎉</span>
        <h3 style="margin: 8px 0 4px; color: #047857;">Excellent Competency!</h3>
        <p style="margin: 0; color: #64748b;">No critical skill gaps detected. You meet or exceed industry baseline requirements across all evaluated domains.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
