<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/skill_mapping.php';
requireRole('student');

function e(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}

$pdo = db();
$candidateId = getStudentCandidateId($pdo);
if ($candidateId <= 0) {
    renderUnlinkedCandidateError();
}


$candStmt = $pdo->prepare("SELECT c.*, i.name institute_name FROM candidates c LEFT JOIN institutes i ON i.id = c.institute_id WHERE c.id = ? LIMIT 1");
$candStmt->execute([$candidateId]);
$candidate = $candStmt->fetch() ?: [];

$profileData = getStudentSkillProfile($pdo, $candidateId);
$strengths = $profileData['strengths'];

$attStmt = $pdo->prepare("SELECT * FROM assessment_attempts WHERE candidate_id = ? AND status = 'Completed' ORDER BY completed_at DESC");
$attStmt->execute([$candidateId]);
$assessments = $attStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SkillBridge | Student Digital Portfolio</title>
<link rel="stylesheet" href="../Industry%20dashboard/style.css">
<style>
body { background: #f8fafc; }
.portfolio-shell { max-width: 900px; margin: 40px auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 24px; padding: 48px; box-shadow: 0 15px 35px rgba(0,0,0,0.04); }
.portfolio-header { display: flex; gap: 24px; align-items: center; border-bottom: 1px solid #edf2f7; padding-bottom: 32px; margin-bottom: 32px; }
.avatar-large { width: 90px; height: 90px; border-radius: 50%; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; font-size: 32px; font-weight: 800; display: flex; align-items: center; justify-content: center; }
.verified-chip { background: #dcfce7; color: #15803d; border: 1px solid #86efac; padding: 4px 12px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block; margin-bottom: 8px; }
.section-heading { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.badge-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; margin-bottom: 40px; }
.badge-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px; text-align: center; }
.badge-card strong { display: block; font-size: 16px; color: #0f172a; }
.badge-card small { color: #16a34a; font-weight: 700; font-size: 12px; }
@media print {
  body { background: #fff; }
  .portfolio-shell { border: none; box-shadow: none; padding: 0; }
  .no-print { display: none; }
}
</style>
</head>
<body>

<div class="portfolio-shell">
  <div class="no-print" style="margin-bottom: 24px; display: flex; justify-content: space-between;">
    <a href="index.php" style="color: #2563eb; font-weight: 600; text-decoration: none;">← Back to Dashboard</a>
    <button onclick="window.print()" class="btn primary" style="padding: 8px 18px;">🖨 Print Portfolio</button>
  </div>

  <div class="portfolio-header">
    <div class="avatar-large"><?= e(strtoupper(substr($candidate['name'] ?? 'S', 0, 1))) ?></div>
    <div>
      <span class="verified-chip">✓ Verified SkillBridge Portfolio</span>
      <h1 style="margin: 0; font-size: 28px; color: #0f172a;"><?= e($candidate['name'] ?? 'Student Name') ?></h1>
      <p style="margin: 6px 0 0; color: #64748b; font-size: 15px;">
        <?= e($candidate['qualification'] ?? 'Student') ?> • <?= e($candidate['institute_name'] ?? 'Academic Institute') ?>
      </p>
      <p style="margin: 4px 0 0; color: #94a3b8; font-size: 13px;">
        Email: <?= e($candidate['email'] ?? 'N/A') ?> | Location: <?= e($candidate['location'] ?? 'N/A') ?>
      </p>
    </div>
  </div>

  <!-- VERIFIED SKILLS SECTION -->
  <div class="section-heading">✦ Verified Competency Badges</div>
  <div class="badge-grid">
    <?php foreach($strengths as $s): ?>
      <div class="badge-card">
        <strong><?= e($s['name']) ?></strong>
        <small>✓ Verified Score: <?= (int)$s['score'] ?>%</small>
      </div>
    <?php endforeach; ?>
    <?php if(!$strengths): ?>
      <p style="color: #64748b;">No verified skill badges yet. Complete adaptive assessments to earn badges.</p>
    <?php endif; ?>
  </div>

  <!-- COMPLETED ADAPTIVE ASSESSMENTS -->
  <div class="section-heading">📋 Adaptive Assessment Record</div>
  <div class="card table-card" style="margin-bottom: 40px;">
    <div class="table-scroll">
      <table>
        <thead><tr><th>Target Role</th><th>Difficulty Level Reached</th><th>Score Earned</th><th>Verification Date</th></tr></thead>
        <tbody>
          <?php foreach($assessments as $att): ?>
            <tr>
              <td><strong><?= e($att['target_role']) ?></strong></td>
              <td><span class="status active"><?= e($att['difficulty_reached']) ?></span></td>
              <td><b><?= round((float)$att['total_score']) ?> / 100</b></td>
              <td><?= e(date('d M Y', strtotime((string)$att['completed_at']))) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if(!$assessments): ?><tr><td colspan="4" style="text-align:center; padding: 20px; color: #64748b;">No assessment attempts recorded.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- BIOGRAPHY & PROFILE -->
  <div class="section-heading">👤 Professional Summary</div>
  <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; font-size: 14px; color: #334155; line-height: 1.6;">
    <?= e($candidate['bio'] ?: 'Demonstrated proficiency in core software engineering, data structures, and database principles through verified SkillBridge adaptive assessments.') ?>
  </div>
</div>

</body>
</html>
