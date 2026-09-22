<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/notifications.php';
requireRole('student');

function e(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}

$pdo = db();
$candidateId = getStudentCandidateId($pdo);

if ($candidateId <= 0) {
    renderUnlinkedCandidateError();
}

$msg = ''; $msgType = 'success';

// Handle POST actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'join_program') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $msg = "Invalid security token.";
            $msgType = 'error';
        } else {
            $progId = (int)($_POST['program_id'] ?? 0);
            
            $chk = $pdo->prepare("SELECT id, status FROM learning_enrollments WHERE program_id = ? AND candidate_id = ? LIMIT 1");
            $chk->execute([$progId, $candidateId]);
            $existing = $chk->fetch();

            if (!$existing) {
                $eStmt = $pdo->prepare("INSERT INTO learning_enrollments (program_id, candidate_id, status) VALUES (?, ?, 'In Progress')");
                $eStmt->execute([$progId, $candidateId]);
                $enrollId = (int)$pdo->lastInsertId();

                // Populate initial learning_progress rows for all program modules
                $modStmt = $pdo->prepare("SELECT id FROM learning_program_modules WHERE program_id = ?");
                $modStmt->execute([$progId]);
                $mods = $modStmt->fetchAll(PDO::FETCH_COLUMN);

                $prStmt = $pdo->prepare("INSERT INTO learning_progress (enrollment_id, module_id, status) VALUES (?, ?, 'Pending')");
                foreach ($mods as $mId) {
                    $prStmt->execute([$enrollId, $mId]);
                }
                $progInfo = $pdo->prepare('SELECT title FROM learning_programs WHERE id=? LIMIT 1'); $progInfo->execute([$progId]); $progTitle=(string)($progInfo->fetchColumn() ?: 'Learning Program');
                sbNotifyUser($pdo,(int)($_SESSION['user_id'] ?? 0),'Learning program joined','You joined “'.$progTitle.'”. Your learning progress is now being tracked.','learning','learning_program',$progId,true);
                $msg = "You have successfully joined the learning program!";
            } elseif ($existing['status'] === 'Withdrawn') {
                // Re-enroll if previously withdrawn
                $pdo->prepare("UPDATE learning_enrollments SET status = 'In Progress', enrolled_at = CURRENT_TIMESTAMP, withdrawn_at = NULL WHERE id = ?")->execute([(int)$existing['id']]);
                $progInfo = $pdo->prepare('SELECT title FROM learning_programs WHERE id=? LIMIT 1'); $progInfo->execute([$progId]); $progTitle=(string)($progInfo->fetchColumn() ?: 'Learning Program');
                sbNotifyUser($pdo,(int)($_SESSION['user_id'] ?? 0),'Learning program re-joined','You re-joined “'.$progTitle.'”.','learning','learning_program',$progId,true);
                $msg = "Re-enrolled in the learning program successfully!";
            } else {
                $msg = "You are already enrolled in this program.";
                $msgType = 'error';
            }
        }
    }

    if ($action === 'withdraw_program') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $msg = "Invalid security token.";
            $msgType = 'error';
        } else {
            $enrollId = (int)($_POST['enrollment_id'] ?? 0);
            $eChk = $pdo->prepare("SELECT candidate_id FROM learning_enrollments WHERE id = ? LIMIT 1");
            $eChk->execute([$enrollId]);
            
            if ((int)$eChk->fetchColumn() === $candidateId) {
                $upStmt = $pdo->prepare("UPDATE learning_enrollments SET status = 'Withdrawn', withdrawn_at = CURRENT_TIMESTAMP WHERE id = ? AND candidate_id = ?");
                $upStmt->execute([$enrollId, $candidateId]);
                $progInfo = $pdo->prepare('SELECT lp.title FROM learning_enrollments le JOIN learning_programs lp ON lp.id=le.program_id WHERE le.id=? LIMIT 1'); $progInfo->execute([$enrollId]); $progTitle=(string)($progInfo->fetchColumn() ?: 'Learning Program');
                sbNotifyUser($pdo,(int)($_SESSION['user_id'] ?? 0),'Learning program withdrawn','You withdrew from “'.$progTitle.'”.','learning','learning_enrollment',$enrollId,true);
                $msg = "You have withdrawn from the learning program.";
            } else {
                $msg = "Unauthorized operation.";
                $msgType = 'error';
            }
        }
    }

    if ($action === 'toggle_module') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $msg = "Invalid security token.";
            $msgType = 'error';
        } else {
            $enrollId = (int)($_POST['enrollment_id'] ?? 0);
            $moduleId = (int)($_POST['module_id'] ?? 0);
            $newStatus = ($_POST['status'] ?? '') === 'Completed' ? 'Completed' : 'Pending';
            $compDate = $newStatus === 'Completed' ? date('Y-m-d H:i:s') : null;

            // Security check enrollment candidate_id
            $eChk = $pdo->prepare("SELECT candidate_id, program_id FROM learning_enrollments WHERE id = ? LIMIT 1");
            $eChk->execute([$enrollId]);
            $eRow = $eChk->fetch();

            if ($eRow && (int)$eRow['candidate_id'] === $candidateId) {
                $upStmt = $pdo->prepare("UPDATE learning_progress SET status = ?, completed_at = ? WHERE enrollment_id = ? AND module_id = ?");
                $upStmt->execute([$newStatus, $compDate, $enrollId, $moduleId]);

                // Recalculate enrollment completion status
                $totMods = (int)$pdo->query("SELECT COUNT(*) FROM learning_program_modules WHERE program_id = {$eRow['program_id']}")->fetchColumn();
                $compMods = (int)$pdo->query("SELECT COUNT(*) FROM learning_progress WHERE enrollment_id = {$enrollId} AND status = 'Completed'")->fetchColumn();

                if ($totMods > 0 && $compMods >= $totMods) {
                    $pdo->prepare("UPDATE learning_enrollments SET status = 'Completed' WHERE id = ?")->execute([$enrollId]);
                } else {
                    $pdo->prepare("UPDATE learning_enrollments SET status = 'In Progress' WHERE id = ?")->execute([$enrollId]);
                }
                $pctNow = $totMods > 0 ? (int)round(($compMods / $totMods) * 100) : 0;
                $milestones = [25,50,75,100];
                $milestoneKey = 'learning_milestone_' . $enrollId . '_' . $pctNow;
                if (in_array($pctNow,$milestones,true) && empty($_SESSION[$milestoneKey])) {
                    $_SESSION[$milestoneKey]=1;
                    $progInfo = $pdo->prepare('SELECT title FROM learning_programs WHERE id=? LIMIT 1'); $progInfo->execute([(int)$eRow['program_id']]); $progTitle=(string)($progInfo->fetchColumn() ?: 'Learning Program');
                    sbNotifyUser($pdo,(int)($_SESSION['user_id'] ?? 0),'Learning progress: '.$pctNow.'%','Your progress in “'.$progTitle.'” reached '.$pctNow.'%.','progress','learning_enrollment',$enrollId,true);
                }
                $msg = "Module progress updated!";
            }
        }
    }
}

// Fetch All Active Learning Programs
$programs = $pdo->query("SELECT lp.*, c.name company_name FROM learning_programs lp JOIN companies c ON c.id = lp.company_id WHERE lp.status = 'Active' ORDER BY lp.created_at DESC")->fetchAll();

// Fetch Enrolled Programs with Module Progress calculations
$enrolledStmt = $pdo->prepare("
    SELECT le.id enrollment_id, le.status enrollment_status, le.enrolled_at, le.withdrawn_at, lp.*, c.name company_name
    FROM learning_enrollments le
    JOIN learning_programs lp ON lp.id = le.program_id
    JOIN companies c ON c.id = lp.company_id
    WHERE le.candidate_id = ?
    ORDER BY le.enrolled_at DESC
");
$enrolledStmt->execute([$candidateId]);
$enrolledPrograms = $enrolledStmt->fetchAll();

// Map modules & progress for enrolled programs
foreach ($enrolledPrograms as &$ep) {
    $eId = (int)$ep['enrollment_id'];
    $pId = (int)$ep['id'];

    $mStmt = $pdo->prepare("
        SELECT lpm.id module_id, lpm.module_title, lpm.description, COALESCE(lp.status, 'Pending') status
        FROM learning_program_modules lpm
        LEFT JOIN learning_progress lp ON lp.module_id = lpm.id AND lp.enrollment_id = ?
        WHERE lpm.program_id = ?
        ORDER BY lpm.order_num ASC
    ");
    $mStmt->execute([$eId, $pId]);
    $ep['modules'] = $mStmt->fetchAll();

    $tot = count($ep['modules']);
    $comp = count(array_filter($ep['modules'], fn($m) => $m['status'] === 'Completed'));
    $ep['pct'] = $tot > 0 ? min(100, max(0, round(($comp / $tot) * 100))) : 0;
    $ep['comp_count'] = $comp;
    $ep['tot_count'] = $tot;
}
unset($ep);

// Filter active (Enrolled/In Progress/Completed) vs Withdrawn
$activeEnrollments = array_filter($enrolledPrograms, fn($ep) => $ep['enrollment_status'] !== 'Withdrawn');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SkillBridge | Industry Learning Programs & Progress</title>
<link rel="stylesheet" href="../Industry%20dashboard/style.css">
<style>
body { background: #f8fafc; }
.learning-shell { max-width: 960px; margin: 40px auto; padding: 0 20px; }
.tab-bar { display: flex; gap: 12px; border-bottom: 2px solid #e2e8f0; margin-bottom: 28px; }
.tab-item { padding: 10px 20px; border: none; background: none; font-weight: 600; color: #64748b; cursor: pointer; font-size: 15px; border-bottom: 2px solid transparent; margin-bottom: -2px; }
.tab-item.active { color: #2563eb; border-bottom-color: #2563eb; }
.prog-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 28px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
.module-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 8px; }
.module-row.completed { background: #ecfdf5; border-color: #a7f3d0; }
.status.withdrawn { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }
</style>
</head>
<body>

<div class="learning-shell">
  <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <a href="index.php" style="color: #2563eb; font-weight: 600; text-decoration: none;">← Back to Student Dashboard</a>
    <span class="eyebrow">SKILL CLOSURE HUB</span>
  </div>

  <?php if($msg): ?><div class="flash <?= e($msgType) ?>"><?= e($msg) ?></div><?php endif; ?>

  <div class="tab-bar">
    <button class="tab-item active" onclick="switchTab('available')">Available Programs (<?= count($programs) ?>)</button>
    <button class="tab-item" onclick="switchTab('my')">My Active Enrollments (<?= count($activeEnrollments) ?>)</button>
  </div>

  <!-- AVAILABLE PROGRAMS TAB -->
  <div id="tabAvailable">
    <?php foreach($programs as $prog): 
      $enrollRow = current(array_filter($enrolledPrograms, fn($x) => (int)$x['id'] === (int)$prog['id']));
      $eStatus = $enrollRow ? $enrollRow['enrollment_status'] : 'Not Enrolled';
    ?>
      <div class="prog-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
          <div>
            <span class="eyebrow"><?= e($prog['company_name']) ?> • <?= e($prog['type']) ?></span>
            <h2 style="margin: 4px 0; font-size: 20px; color: #0f172a;"><?= e($prog['title']) ?></h2>
            <p style="margin: 4px 0 14px; font-size: 14px; color: #64748b;">Duration: <b><?= e($prog['duration']) ?></b> | Location: <b><?= e($prog['location']) ?></b> | Stipend/Fee: <b><?= e($prog['stipend_or_fee']) ?></b></p>
          </div>
          <div>
            <?php if($eStatus === 'Enrolled' || $eStatus === 'In Progress'): ?>
              <span class="status active">✓ Joined (In Progress)</span>
            <?php elseif($eStatus === 'Completed'): ?>
              <span class="status active" style="background:#dcfce7; color:#15803d;">✓ Completed</span>
            <?php elseif($eStatus === 'Withdrawn'): ?>
              <form method="post" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="join_program">
                <input type="hidden" name="program_id" value="<?= (int)$prog['id'] ?>">
                <button class="btn secondary" type="submit">Re-Join Program</button>
              </form>
            <?php else: ?>
              <form method="post" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="join_program">
                <input type="hidden" name="program_id" value="<?= (int)$prog['id'] ?>">
                <button class="btn primary" type="submit">Join Program</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
        <p style="font-size: 14px; color: #334155; margin-bottom: 12px;"><?= e($prog['description']) ?></p>
        <div class="chips">Target Skills: <?= e($prog['target_skills']) ?></div>
      </div>
    <?php endforeach; ?>
    <?php if(!$programs): ?><div class="card empty-state">No active learning programs available.</div><?php endif; ?>
  </div>

  <!-- MY ENROLLED PROGRAMS TAB -->
  <div id="tabMy" style="display: none;">
    <?php foreach($enrolledPrograms as $ep): ?>
      <div class="prog-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
          <div>
            <span class="eyebrow"><?= e($ep['company_name']) ?></span>
            <h2 style="margin: 4px 0; font-size: 20px; color: #0f172a;"><?= e($ep['title']) ?></h2>
            <small style="color:#64748b;">Enrolled: <?= e(date('d M Y', strtotime((string)$ep['enrolled_at']))) ?> <?php if($ep['withdrawn_at']): ?>| Withdrawn: <?= e(date('d M Y', strtotime((string)$ep['withdrawn_at']))) ?><?php endif; ?></small>
          </div>
          
          <div style="display:flex; gap:8px; align-items:center;">
            <span class="status <?= $ep['enrollment_status']==='Completed'?'active':($ep['enrollment_status']==='Withdrawn'?'withdrawn':'draft') ?>">
              <?= e($ep['enrollment_status']) ?>
            </span>

            <?php if($ep['enrollment_status'] === 'Enrolled' || $ep['enrollment_status'] === 'In Progress'): ?>
              <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to withdraw from this program?');">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="withdraw_program">
                <input type="hidden" name="enrollment_id" value="<?= (int)$ep['enrollment_id'] ?>">
                <button class="btn secondary" type="submit" style="padding: 4px 10px; font-size: 11px; color:#ef4444; border-color:#fca5a5;">Withdraw</button>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <!-- PROGRESS BAR -->
        <div style="margin-bottom: 20px;">
          <div style="display:flex; justify-content:space-between; font-weight:700; font-size:13px; margin-bottom:6px;">
            <span>Module Progress (<?= $ep['comp_count'] ?> / <?= $ep['tot_count'] ?> Modules Completed)</span>
            <span><?= $ep['pct'] ?>%</span>
          </div>
          <div class="bar large"><i style="width: <?= $ep['pct'] ?>%;"></i></div>
        </div>

        <!-- MODULE CHECKLIST WORKSPACE -->
        <?php if($ep['enrollment_status'] !== 'Withdrawn'): ?>
          <h4 style="font-size: 14px; margin-bottom: 10px; color: #334155;">Course Modules & Interactive Checklist</h4>
          <?php foreach($ep['modules'] as $m): $isComp = $m['status'] === 'Completed'; ?>
            <div class="module-row <?= $isComp ? 'completed' : '' ?>">
              <div>
                <strong><?= e($m['module_title']) ?></strong>
                <small style="display:block; color:#64748b; font-size:12px;"><?= e($m['description']) ?></small>
              </div>
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="toggle_module">
                <input type="hidden" name="enrollment_id" value="<?= (int)$ep['enrollment_id'] ?>">
                <input type="hidden" name="module_id" value="<?= (int)$m['module_id'] ?>">
                <input type="hidden" name="status" value="<?= $isComp ? 'Pending' : 'Completed' ?>">
                <button class="btn <?= $isComp ? 'secondary' : 'primary' ?>" type="submit" style="padding: 6px 14px; font-size: 12px;">
                  <?= $isComp ? '✓ Completed (Click to Undo)' : 'Mark Module Complete' ?>
                </button>
              </form>
            </div>
          <?php endforeach; ?>
          <?php if(!$ep['modules']): ?><p style="color:#64748b; font-size: 13px;">No module curriculum defined for this program.</p><?php endif; ?>
        <?php else: ?>
          <p style="color:#64748b; font-size:13px; font-style:italic;">You have withdrawn from this learning program. Re-join from the Available Programs tab to resume module progress.</p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if(!$enrolledPrograms): ?><div class="card empty-state">You have not enrolled in any learning programs yet.</div><?php endif; ?>
  </div>
</div>

<script>
function switchTab(tab) {
  document.querySelectorAll('.tab-item').forEach(b => b.classList.remove('active'));
  if (tab === 'available') {
    document.getElementById('tabAvailable').style.display = 'block';
    document.getElementById('tabMy').style.display = 'none';
    document.querySelector('.tab-item:nth-child(1)').classList.add('active');
  } else {
    document.getElementById('tabAvailable').style.display = 'none';
    document.getElementById('tabMy').style.display = 'block';
    document.querySelector('.tab-item:nth-child(2)').classList.add('active');
  }
}
</script>
</body>
</html>
