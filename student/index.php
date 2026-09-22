<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/skill_mapping.php';
require_once __DIR__ . '/../includes/notifications.php';
requireRole('student');

function e(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}

function uiIcon(string $name): string {
    $icons = [
        'home'=>'<path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9.5V21h13V9.5"/><path d="M9.5 21v-6h5v6"/>',
        'assessment'=>'<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8M8 11h5M8 15h8"/>',
        'skills'=>'<path d="M4 19V5"/><path d="M4 19h17"/><path d="m7 15 3-4 3 2 5-7"/>',
        'book'=>'<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 5.5v16M8 7h8M8 11h7"/>',
        'briefcase'=>'<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18"/>',
        'file'=>'<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
        'portfolio'=>'<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h5"/>',
        'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'bell'=>'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'search'=>'<circle cx="11" cy="11" r="7"/><path d="m16.5 16.5 4 4"/>',
        'arrow'=>'<path d="M5 12h14M13 6l6 6-6 6"/>',
        'target'=>'<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2"/>',
        'cap'=>'<path d="m3 9 9-5 9 5-9 5z"/><path d="M7 12v5c3 2 8 2 10 0v-5M21 10v6"/>',
        'menu'=>'<path d="M4 7h16M4 12h16M4 17h16"/>',
        'logout'=>'<path d="M10 17l5-5-5-5M15 12H3M21 19V5a2 2 0 0 0-2-2h-5"/>',
        'spark'=>'<path d="m12 3 1.4 5.6L19 10l-5.6 1.4L12 17l-1.4-5.6L5 10l5.6-1.4z"/>',
        'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'check'=>'<path d="m5 12 4 4L19 6"/>',
        'trend'=>'<path d="M4 17 10 11l4 4 6-8"/><path d="M16 7h4v4"/>',
    ];
    return '<svg viewBox="0 0 24 24" aria-hidden="true" class="ui-icon">'.($icons[$name] ?? $icons['spark']).'</svg>';
}

$pdo = db();
$candidateId = getStudentCandidateId($pdo);
$notificationCount = sbUnreadNotificationCount($pdo, (int)($_SESSION['user_id'] ?? 0));

if ($candidateId <= 0) {
    renderUnlinkedCandidateError();
}

// Handle POST actions (Apply, Withdraw Application)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'apply_opportunity') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['student_flash'] = ['type' => 'error', 'message' => 'Invalid security token. Please try again.'];
        } else {
            $oppId = (int)($_POST['opportunity_id'] ?? 0);
            
            // Server-side match score calculation (eliminates client tampering)
            $matchDetails = calculateOpportunityMatchDetails($pdo, $candidateId, $oppId);
            $matchScore = (float)$matchDetails['total_score'];
            
            $check = $pdo->prepare("SELECT id FROM applications WHERE candidate_id = ? AND opportunity_id = ? LIMIT 1");
            $check->execute([$candidateId, $oppId]);
            if (!$check->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO applications (candidate_id, opportunity_id, match_score, status) VALUES (?, ?, ?, 'Applied')");
                $stmt->execute([$candidateId, $oppId, $matchScore]);
                $applicationId = (int)$pdo->lastInsertId();
                $oppInfo = $pdo->prepare('SELECT title, company_id FROM opportunities WHERE id=? LIMIT 1');
                $oppInfo->execute([$oppId]);
                $oppRow = $oppInfo->fetch() ?: ['title'=>'Opportunity','company_id'=>0];
                sbNotifyUser($pdo, (int)($_SESSION['user_id'] ?? 0), 'Application submitted',
                    'Your application for “' . $oppRow['title'] . '” was submitted successfully. Match score: ' . round($matchScore) . '%.',
                    'application', 'application', $applicationId, true);
                $companyUser = sbUserByCompany($pdo, (int)$oppRow['company_id']);
                if ($companyUser) {
                    sbNotifyUser($pdo, (int)$companyUser['id'], 'New student application',
                        ($_SESSION['user_name'] ?? 'A student') . ' applied for “' . $oppRow['title'] . '” with a match score of ' . round($matchScore) . '%.',
                        'application', 'application', $applicationId, false);
                }
                $_SESSION['student_flash'] = ['type' => 'success', 'message' => 'Application submitted successfully! Match score: ' . round($matchScore) . '%'];
            } else {
                $_SESSION['student_flash'] = ['type' => 'error', 'message' => 'You have already applied for this opportunity.'];
            }
        }
        header('Location: index.php#applications');
        exit;
    }

    if ($action === 'withdraw_application') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['student_flash'] = ['type' => 'error', 'message' => 'Invalid security token. Please try again.'];
        } else {
            $appId = (int)($_POST['application_id'] ?? 0);
            
            $chk = $pdo->prepare("SELECT status FROM applications WHERE id = ? AND candidate_id = ? LIMIT 1");
            $chk->execute([$appId, $candidateId]);
            $currStatus = $chk->fetchColumn();

            if ($currStatus && in_array($currStatus, ['Applied', 'Under Review'], true)) {
                $upStmt = $pdo->prepare("UPDATE applications SET status = 'Withdrawn', withdrawn_at = CURRENT_TIMESTAMP WHERE id = ? AND candidate_id = ?");
                $upStmt->execute([$appId, $candidateId]);
                $appInfo = $pdo->prepare('SELECT o.title,o.company_id FROM applications a JOIN opportunities o ON o.id=a.opportunity_id WHERE a.id=? LIMIT 1');
                $appInfo->execute([$appId]);
                $appRow = $appInfo->fetch() ?: ['title'=>'Opportunity','company_id'=>0];
                sbNotifyUser($pdo, (int)($_SESSION['user_id'] ?? 0), 'Application withdrawn',
                    'Your application for “' . $appRow['title'] . '” has been withdrawn.', 'application', 'application', $appId, true);
                $companyUser = sbUserByCompany($pdo, (int)$appRow['company_id']);
                if ($companyUser) {
                    sbNotifyUser($pdo, (int)$companyUser['id'], 'Application withdrawn',
                        ($_SESSION['user_name'] ?? 'A student') . ' withdrew the application for “' . $appRow['title'] . '”.',
                        'application', 'application', $appId, false);
                }
                $_SESSION['student_flash'] = ['type' => 'success', 'message' => 'Application withdrawn successfully.'];
            } else {
                $_SESSION['student_flash'] = ['type' => 'error', 'message' => 'This application cannot be withdrawn at its current stage.'];
            }
        }
        header('Location: index.php#applications');
        exit;
    }
}

$flash = $_SESSION['student_flash'] ?? null;
unset($_SESSION['student_flash']);

// Fetch candidate details
$candStmt = $pdo->prepare("SELECT c.*, i.name institute_name FROM candidates c LEFT JOIN institutes i ON i.id = c.institute_id WHERE c.id = ? LIMIT 1");
$candStmt->execute([$candidateId]);
$candidate = $candStmt->fetch() ?: [];

// Get skill profile, strengths & gaps
$profileData = getStudentSkillProfile($pdo, $candidateId);
$skills = $profileData['skills'];
$strengths = $profileData['strengths'];
$gaps = $profileData['gaps'];

// Get recommended opportunities & learning programs
$recommendedOpps = getRecommendedOpportunities($pdo, $candidateId);
$recommendedPrograms = getRecommendedLearningPrograms($pdo, $gaps);

// Fetch student applications
$appStmt = $pdo->prepare("
    SELECT a.*, o.title opportunity_title, o.type opportunity_type, c.name company_name,
           ia.id AS industry_assessment_id, ia.title AS industry_assessment_title,
           ia.status AS industry_assessment_status
    FROM applications a
    JOIN opportunities o ON o.id = a.opportunity_id
    JOIN companies c ON c.id = o.company_id
    LEFT JOIN industry_assessments ia ON ia.opportunity_id = o.id AND ia.status = 'Active'
    WHERE a.candidate_id = ?
    ORDER BY a.applied_at DESC
");
$appStmt->execute([$candidateId]);
$applications = $appStmt->fetchAll();

// Fetch assessment attempts (Completed & In Progress)
$attStmt = $pdo->prepare("SELECT * FROM assessment_attempts WHERE candidate_id = ? ORDER BY started_at DESC");
$attStmt->execute([$candidateId]);
$allAttempts = $attStmt->fetchAll();

$completedAttempts = array_filter($allAttempts, fn($a) => $a['status'] === 'Completed');
$inProgressAttempts = array_filter($allAttempts, fn($a) => $a['status'] === 'In Progress');

if (count($completedAttempts) > 0) {
    $assessmentStatusLabel = count($completedAttempts) . ' Verified Attempt' . (count($completedAttempts) > 1 ? 's' : '');
    $assessmentStatusDesc = 'Verified skill profile active';
    $assessmentIcon = '✓';
    $assessmentTone = 'green';
} elseif (count($inProgressAttempts) > 0) {
    $assessmentStatusLabel = 'In Progress';
    $assessmentStatusDesc = 'Assessment attempt saved';
    $assessmentIcon = '⚡';
    $assessmentTone = 'orange';
} else {
    $assessmentStatusLabel = 'Not Started';
    $assessmentStatusDesc = 'No attempts recorded yet';
    $assessmentIcon = '⏳';
    $assessmentTone = 'blue';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SkillBridge | Student Portal</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-logo"><img src="../assets/skillbridge-logo.jpeg" alt="SkillBridge"></div>
      <div><strong>SKILL<span>BRIDGE</span></strong><small>Student Intelligence Hub</small></div>
    </div>
    <nav class="nav">
      <a class="nav-item active" href="#dashboard" data-section="dashboard"><span class="nav-icon"><?= uiIcon('home') ?></span><span>Dashboard</span></a>
      <a class="nav-item" href="assessment.php"><span class="nav-icon"><?= uiIcon('assessment') ?></span><span>Skill Assessment</span><b class="ai-pill">AI</b></a>
      <a class="nav-item" href="skill_profile.php"><span class="nav-icon"><?= uiIcon('skills') ?></span><span>Skill Profile & Gaps</span></a>
      <a class="nav-item" href="#opportunities" data-section="opportunities"><span class="nav-icon"><?= uiIcon('briefcase') ?></span><span>Jobs & Internships</span></a>
      <a class="nav-item" href="learning.php"><span class="nav-icon"><?= uiIcon('book') ?></span><span>Learning Programs</span></a>
      <a class="nav-item" href="#applications" data-section="applications"><span class="nav-icon"><?= uiIcon('file') ?></span><span>My Applications</span></a>
      <a class="nav-item" href="portfolio.php"><span class="nav-icon"><?= uiIcon('portfolio') ?></span><span>Digital Portfolio</span></a>
      <a class="nav-item" href="profile_edit.php"><span class="nav-icon"><?= uiIcon('user') ?></span><span>My Profile</span></a>
    </nav>
    <div class="company-mini">
      <div class="avatar"><?= e(strtoupper(substr($candidate['name'] ?? 'S', 0, 1))) ?></div>
      <div><strong><?= e($candidate['name'] ?? 'Student') ?></strong><small><?= e($candidate['institute_name'] ?? 'Institute') ?></small></div>
    </div>
  </aside>

  <main class="main">
    <header class="topbar">
      <button class="menu-btn" id="menuBtn" aria-label="Open navigation"><?= uiIcon('menu') ?></button>
      <div class="top-search"><span><?= uiIcon('search') ?></span><input placeholder="Search jobs, internships, skills, learning programs..."></div>
      <div class="top-actions">
        <a class="icon-btn" aria-label="Notifications" href="../notifications.php" style="text-decoration:none;position:relative"><?= uiIcon('bell') ?><?php if($notificationCount>0): ?><b><?= $notificationCount > 99 ? '99+' : $notificationCount ?></b><?php endif; ?></a>
        <div class="top-profile"><span class="avatar"><?= e(strtoupper(substr($candidate['name'] ?? 'S', 0, 1))) ?></span><div><strong><?= e($candidate['name'] ?? 'Student') ?></strong><small><?= e($candidate['qualification'] ?? 'Student') ?></small></div></div>
        <a href="../logout.php" class="logout-btn">Logout</a>
      </div>
    </header>

    <?php if($flash): ?><div class="flash <?= e($flash['type']) ?>" id="flash"><?= e($flash['message']) ?></div><?php endif; ?>

    <div class="content">
      <!-- DASHBOARD SECTION -->
      <section class="page-section active" id="dashboard">
        <div class="hero-card">
          <div class="hero-copy">
            <span class="eyebrow">STUDENT CAREER WORKSPACE</span>
            <h1>Good Morning, <?= e($candidate['name'] ?? 'Student') ?>! <span>👋</span></h1>
            <p>Build verified skills, discover opportunities and stay on track for your career.</p>
            <div class="hero-actions">
              <a href="assessment.php" class="btn primary"><?= uiIcon('assessment') ?> Take Assessment <span><?= uiIcon('arrow') ?></span></a>
              <a href="#opportunities" data-section="opportunities" class="btn ghost"><?= uiIcon('briefcase') ?> Explore Opportunities</a>
            </div>
          </div>
          <div class="hero-side">
            <div class="date-card"><?= uiIcon('clock') ?><div><small>Student Workspace</small><strong><?= e($candidate['institute_name'] ?? 'Institute') ?></strong></div></div>
            <div class="profile-progress"><div><span>Profile completeness</span><strong>Keep your profile updated</strong></div><div class="progress-track"><i style="width:78%"></i></div></div>
          </div>
        </div>

        <div class="stats-grid" style="margin-top: 24px;">
          <div class="stat-card"><span class="stat-icon blue"><?= uiIcon('skills') ?></span><div><small>Skill Readiness</small><strong><?= count($strengths) ?> Strengths</strong><em><?= count($gaps) ?> skill gaps</em></div></div>
          <div class="stat-card"><span class="stat-icon <?= $assessmentTone ?>"><?= uiIcon('assessment') ?></span><div><small>Assessment Status</small><strong><?= e($assessmentStatusLabel) ?></strong><em><?= e($assessmentStatusDesc) ?></em></div></div>
          <div class="stat-card"><span class="stat-icon violet"><?= uiIcon('target') ?></span><div><small>Recommended Opportunities</small><strong><?= count($recommendedOpps) ?></strong><em>Matching target skills</em></div></div>
          <div class="stat-card"><span class="stat-icon orange"><?= uiIcon('file') ?></span><div><small>Active Applications</small><strong><?= count(array_filter($applications, fn($a) => $a['status'] !== 'Withdrawn')) ?></strong><em>Submitted applications</em></div></div>
        </div>

        <!-- SKILL PROFILE & RECOMMENDATION GRID -->
        <div class="dashboard-grid two-one" style="margin-top:24px;">
          <article class="card">
            <div class="card-head"><div><h3><span class="section-icon blue"><?= uiIcon('skills') ?></span>Verified Strengths & Skill Gaps</h3><p>Evaluated through AI adaptive testing</p></div></div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding: 12px 0;">
              <div>
                <h4 style="font-size: 14px; color: #047857; margin-bottom: 12px;">✓ Identified Strengths (≥70%)</h4>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                  <?php foreach($strengths as $s): ?>
                    <span class="strength-tag"><?= e($s['name']) ?> (<?= (int)$s['score'] ?>%)</span>
                  <?php endforeach; ?>
                  <?php if(!$strengths): ?><span style="color:#64748b; font-size: 13px;">No verified strengths yet. Take an assessment!</span><?php endif; ?>
                </div>
              </div>
              <div>
                <h4 style="font-size: 14px; color: #be123c; margin-bottom: 12px;">⚠ Skill Gaps (&lt;70%)</h4>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                  <?php foreach($gaps as $g): ?>
                    <span class="gap-tag"><?= e($g['name']) ?> (<?= (int)$g['score'] ?>%)</span>
                  <?php endforeach; ?>
                  <?php if(!$gaps): ?><span style="color:#047857; font-size: 13px;">Great job! No major skill gaps detected.</span><?php endif; ?>
                </div>
              </div>
            </div>
          </article>

          <article class="card">
            <div class="card-head"><div><h3><span class="section-icon violet"><?= uiIcon('spark') ?></span>Quick Actions</h3></div></div>
            <div style="display:flex; flex-direction:column; gap:12px; padding: 10px 0;">
              <a href="assessment.php" class="btn primary" style="text-decoration:none; text-align:center;">Take Adaptive Assessment</a>
              <a href="skill_profile.php" class="btn secondary" style="text-decoration:none; text-align:center;">View Skill Profile & Gaps</a>
              <a href="learning.php" class="btn secondary" style="text-decoration:none; text-align:center;">Join Learning Programs</a>
              <a href="portfolio.php" class="btn secondary" style="text-decoration:none; text-align:center;">View Digital Portfolio</a>
            </div>
          </article>
        </div>
      </section>

      <!-- OPPORTUNITIES SECTION -->
      <section class="page-section" id="opportunities">
        <div class="page-title"><div><span class="eyebrow">TALENT MATCHING</span><h2>Recommended Opportunities</h2><p>Calculated using your verified skill profile.</p></div></div>
        <div class="card table-card">
          <div class="table-scroll">
            <table>
              <thead><tr><th>Opportunity</th><th>Company</th><th>Match Score</th><th>Missing Skills</th><th>Action</th></tr></thead>
              <tbody>
                <?php foreach($recommendedOpps as $o): ?>
                  <tr>
                    <td><strong><?= e($o['title']) ?></strong><small><?= e($o['type']) ?> • <?= e($o['location'] ?: 'Remote') ?></small></td>
                    <td><?= e($o['company_name']) ?></td>
                    <td><span class="match-badge <?= $o['match_pct']>=75?'high':($o['match_pct']>=50?'mid':'low') ?>"><?= $o['match_pct'] ?>% Match</span></td>
                    <td><?= e(implode(', ', $o['missing_skills']) ?: 'None (Full Match!)') ?></td>
                    <td>
                      <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="apply_opportunity">
                        <input type="hidden" name="opportunity_id" value="<?= (int)$o['id'] ?>">
                        <button type="submit" class="btn primary" style="padding: 6px 14px; font-size: 12px;">Apply Now</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- LEARNING PROGRAMS SECTION -->
      <section class="page-section" id="learning">
        <div class="page-title"><div><span class="eyebrow">SKILL CLOSURE</span><h2>Recommended Learning Programs</h2><p>Company-created training & workshops targeting your identified skill gaps.</p></div></div>
        <div class="collab-grid">
          <?php foreach($recommendedPrograms as $lp): ?>
            <article class="card collab-item">
              <h3><?= e($lp['title']) ?></h3>
              <p><strong><?= e($lp['type']) ?></strong> • <?= e($lp['company_name']) ?> • <?= e($lp['duration']) ?></p>
              <small>Addresses gaps: <b><?= e(implode(', ', $lp['address_gaps']) ?: 'General skills') ?></b></small>
              <p style="margin-top: 8px; font-size: 13px; color: #475569;"><?= e($lp['description']) ?></p>
              <div style="margin-top:14px;">
                <a href="learning.php" class="btn primary" style="text-decoration:none; padding: 6px 16px; font-size: 12px;">Browse & Join Program</a>
              </div>
            </article>
          <?php endforeach; ?>
          <?php if(!$recommendedPrograms): ?><div class="card empty-state">No learning programs currently active.</div><?php endif; ?>
        </div>
      </section>

      <!-- APPLICATIONS TRACKER -->
      <section class="page-section" id="applications">
        <div class="page-title"><div><span class="eyebrow">APPLICATION STATUS</span><h2>My Applications</h2></div></div>
        <div class="card table-card">
          <div class="table-scroll">
            <table>
              <thead><tr><th>Opportunity</th><th>Company</th><th>Match Score</th><th>Status</th><th>Applied Date</th><th>Assessment</th><th>Action</th></tr></thead>
              <tbody>
                <?php foreach($applications as $a): 
                  $canWithdraw = in_array((string)$a['status'], ['Applied', 'Under Review'], true);
                ?>
                  <tr>
                    <td><strong><?= e($a['opportunity_title']) ?></strong><small><?= e($a['opportunity_type']) ?></small></td>
                    <td><?= e($a['company_name']) ?></td>
                    <td><span class="match-badge high"><?= round((float)$a['match_score']) ?>%</span></td>
                    <td><span class="status <?= strtolower((string)$a['status']) ?>"><?= e($a['status']) ?></span></td>
                    <td><?= e(date('d M Y', strtotime((string)$a['applied_at']))) ?></td>
                    <td>
                      <?php if(!empty($a['industry_assessment_id'])): ?>
                        <a href="assessment.php?assessment_id=<?= (int)$a['industry_assessment_id'] ?>" class="btn primary" style="padding:4px 10px; font-size:11px; text-decoration:none;">Take Test</a>
                        <small style="display:block; margin-top:4px; color:#64748b;"><?= e($a['industry_assessment_title']) ?></small>
                      <?php else: ?>
                        <small style="color:#64748b;">No industry test</small>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if($canWithdraw): ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to withdraw this application? This action will notify the company.');">
                          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                          <input type="hidden" name="action" value="withdraw_application">
                          <input type="hidden" name="application_id" value="<?= (int)$a['id'] ?>">
                          <button type="submit" class="btn secondary" style="padding: 4px 10px; font-size: 11px; color: #ef4444; border-color: #fca5a5;">Withdraw</button>
                        </form>
                      <?php else: ?>
                        <small style="color:#64748b;">—</small>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if(!$applications): ?><tr><td colspan="7" style="text-align:center; padding: 24px; color: #64748b;">No applications submitted yet.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </main>
</div>

<script>
const sidebar = document.getElementById('sidebar');
document.getElementById('menuBtn')?.addEventListener('click', () => sidebar.classList.toggle('open'));

function activateSection(id) {
  const target = document.getElementById(id) || document.getElementById('dashboard');
  document.querySelectorAll('.page-section').forEach(s => s.classList.toggle('active', s === target));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.toggle('active', n.dataset.section === target.id));
}
document.querySelectorAll('[data-section]').forEach(a => a.addEventListener('click', e => {
  e.preventDefault();
  activateSection(a.dataset.section);
  sidebar.classList.remove('open');
}));
if (location.hash) activateSection(location.hash.substring(1));
</script>
</body>
</html>
