<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
requireRole('institute');

function uiIcon(string $name): string {
    $map = [
        'home'=>'<svg viewBox="0 0 24 24" fill="none"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1V10Z"/><path d="M9 21v-7h6v7"/></svg>',
        'users'=>'<svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="7" r="4"/><path d="M2 21a7 7 0 0 1 14 0M16 4.2a4 4 0 0 1 0 6.6M19 14a5 5 0 0 1 3 4.5"/></svg>',
        'user'=>'<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="7" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
        'clipboard'=>'<svg viewBox="0 0 24 24" fill="none"><rect x="4" y="4" width="16" height="17" rx="2"/><path d="M9 4V2h6v2M8 9h8M8 13h6M8 17h4"/></svg>',
        'badge'=>'<svg viewBox="0 0 24 24" fill="none"><path d="m12 3 8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4Z"/><path d="m9 12 2 2 4-4"/></svg>',
        'chart'=>'<svg viewBox="0 0 24 24" fill="none"><path d="M4 19V5M4 19h17"/><path d="m7 15 4-5 3 3 6-7"/></svg>',
        'book'=>'<svg viewBox="0 0 24 24" fill="none"><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v17H6.5A2.5 2.5 0 0 0 4 21.5v-17Z"/><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/></svg>',
        'briefcase'=>'<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18"/></svg>',
        'file'=>'<svg viewBox="0 0 24 24" fill="none"><path d="M6 2h9l5 5v15H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"/><path d="M14 2v6h6M8 13h8M8 17h6"/></svg>',
        'handshake'=>'<svg viewBox="0 0 24 24" fill="none"><path d="m3 11 4-4 4 1 2-2 4 1 4 4-3 3-3-2-3 3-3-2-3 2-4-4Z"/><path d="m7 7 3 3M17 8l-3 3"/></svg>',
        'bars'=>'<svg viewBox="0 0 24 24" fill="none"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>',
        'building'=>'<svg viewBox="0 0 24 24" fill="none"><path d="M4 21V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v16M2 21h20"/><path d="M8 7h2M14 7h2M8 11h2M14 11h2M8 15h2M14 15h2M11 21v-4h2v4"/></svg>',
        'bell'=>'<svg viewBox="0 0 24 24" fill="none"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>',
        'mail'=>'<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
        'report'=>'<svg viewBox="0 0 24 24" fill="none"><path d="M6 2h9l5 5v15H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg>',
        'search'=>'<svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>',
        'menu'=>'<svg viewBox="0 0 24 24" fill="none"><path d="M4 6h16M4 12h16M4 18h16"/></svg>',
        'cap'=>'<svg viewBox="0 0 24 24" fill="none"><path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11v5c3 2 9 2 12 0v-5M20 10v6"/></svg>',
        'filter'=>'<svg viewBox="0 0 24 24" fill="none"><path d="M4 5h16M7 12h10M10 19h4"/></svg>',
        'close'=>'<svg viewBox="0 0 24 24" fill="none"><path d="m6 6 12 12M18 6 6 18"/></svg>',
        'arrow'=>'<svg viewBox="0 0 24 24" fill="none"><path d="M5 12h13M13 6l6 6-6 6"/></svg>',
        'calendar'=>'<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>',
        'target'=>'<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/></svg>',
        'plus'=>'<svg viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14"/></svg>',
        'check'=>'<svg viewBox="0 0 24 24" fill="none"><path d="m5 13 4 4L19 7"/></svg>',
    ];
    return $map[$name] ?? $map['file'];
}

$pdo = db();
$selectedInstitute = (int)($_SESSION['institute_id'] ?? 0);
$csrfToken = csrfToken();
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid security token. Please refresh and try again.');
    }
    $action = trim($_POST['action'] ?? '');
    if ($action === 'save_suggestion' && $selectedInstitute > 0) {
        $candidate_id = (int)($_POST['candidate_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $suggestion_text = trim($_POST['suggestion_text'] ?? '');
        $priority = in_array($_POST['priority'] ?? '', ['Low','Medium','High'], true) ? $_POST['priority'] : 'Medium';
        $recommended_action = trim($_POST['recommended_action'] ?? '');
        $target_score = max(0, min(100, (int)($_POST['target_score'] ?? 80)));

        $own = $pdo->prepare('SELECT id FROM candidates WHERE id = ? AND institute_id = ? LIMIT 1');
        $own->execute([$candidate_id, $selectedInstitute]);
        if (!$own->fetchColumn()) {
            $notice = 'Selected student does not belong to this institute.';
        } elseif ($candidate_id > 0 && $title !== '' && $suggestion_text !== '') {
            $scoreStmt = $pdo->prepare('SELECT AVG(cs.score) FROM candidate_skills cs WHERE cs.candidate_id = ?');
            $scoreStmt->execute([$candidate_id]);
            $currentScore = (float)($scoreStmt->fetchColumn() ?: 0);

            $stmt = $pdo->prepare("INSERT INTO student_suggestions (institute_id, candidate_id, title, suggestion_text, priority, recommended_action, target_score, current_score, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Suggested')");
            $stmt->execute([$selectedInstitute, $candidate_id, $title, $suggestion_text, $priority, $recommended_action, $target_score, $currentScore]);
            $notice = "Faculty suggestion created successfully for the student.";
        }
    } elseif ($action === 'acknowledge_resource' && $selectedInstitute > 0) {
        $resource_id = (int)($_POST['resource_id'] ?? 0);
        if ($resource_id > 0) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO resource_acknowledgements (resource_id, institute_id, acknowledged_by_user_id) VALUES (?, ?, ?)");
            $stmt->execute([$resource_id, $selectedInstitute, (int)($_SESSION['user_id'] ?? 0)]);
            $notice = "Industry resource acknowledged successfully.";
        }
    } elseif ($action === 'apply_faculty_opportunity' && $selectedInstitute > 0) {
        $opportunity_id = (int)($_POST['opportunity_id'] ?? 0);
        if ($opportunity_id > 0 && tableExists($pdo, 'faculty_opportunities') && tableExists($pdo, 'faculty_applications')) {
            $own = $pdo->prepare('SELECT id FROM faculty_opportunities WHERE id = ? LIMIT 1');
            $own->execute([$opportunity_id]);
            if (!$own->fetchColumn()) {
                $notice = 'Faculty opportunity not found.';
            } else {
                $faculty = $pdo->prepare('SELECT id FROM faculty_profiles WHERE institute_id = ? AND email = ? ORDER BY id LIMIT 1');
                $faculty->execute([$selectedInstitute, (string)($_SESSION['user_email'] ?? '')]);
                $facultyId = (int)($faculty->fetchColumn() ?: 0);

                if ($facultyId <= 0) {
                    $userName = trim((string)($_SESSION['user_name'] ?? 'Institute Faculty'));
                    $userEmail = trim((string)($_SESSION['user_email'] ?? ''));
                    $ins = $pdo->prepare('SELECT name FROM institutes WHERE id = ? LIMIT 1');
                    $ins->execute([$selectedInstitute]);
                    $dept = (string)($ins->fetchColumn() ?: 'Academic Department');
                    $pdo->prepare("INSERT INTO faculty_profiles (institute_id, name, email, department, designation) VALUES (?, ?, ?, ?, 'Faculty')")
                        ->execute([$selectedInstitute, $userName, $userEmail ?: ('faculty-' . $selectedInstitute . '@skillbridge.local'), $dept]);
                    $facultyId = (int)$pdo->lastInsertId();
                }

                $exists = $pdo->prepare('SELECT id FROM faculty_applications WHERE faculty_id = ? AND opportunity_id = ? LIMIT 1');
                $exists->execute([$facultyId, $opportunity_id]);
                if ($exists->fetchColumn()) {
                    $notice = 'You have already applied for this faculty opportunity.';
                } else {
                    $pdo->prepare("INSERT INTO faculty_applications (faculty_id, opportunity_id, institute_id, status) VALUES (?, ?, ?, 'Applied')")
                        ->execute([$facultyId, $opportunity_id, $selectedInstitute]);
                    $notice = 'Faculty application submitted successfully.';
                }
            }
        }
    }
}

$institutes = [];
try {
    if (tableExists($pdo, 'institutes')) {
        $q = $pdo->prepare('SELECT id, name FROM institutes WHERE id = ?');
        $q->execute([$selectedInstitute]);
        $institutes = $q->fetchAll();
    }
} catch (Throwable $e) {
    $notice = 'Institute master data could not be loaded.';
}
$selectedInstituteName = 'Select institute';
foreach ($institutes as $i) {
    if ((int)$i['id'] === $selectedInstitute) {
        $selectedInstituteName = $i['name'];
    }
}

function dashboardData(PDO $pdo, int $iid): array {
    $d = [
        'institute' => ['id' => $iid, 'name' => 'Select institute'],
        'metrics' => ['students' => null, 'talent_profiles' => null, 'active_opportunities' => null, 'applications' => null, 'average_match' => null],
        'skills' => [],
        'demand' => [],
        'students' => [],
        'opportunities' => [],
        'applications' => [],
        'collaboration' => [],
        'progress' => [],
        'suggestions' => [],
        'resources' => [],
        'faculty_opportunities' => [],
        'faculty_applications' => [],
        'notifications' => [],
        'messages' => [], 'readiness' => ['high'=>0,'moderate'=>0,'developing'=>0,'assessed_students'=>0]
    ];
    if ($iid <= 0) return $d;
    try {
        if (tableExists($pdo, 'institutes')) {
            $q = $pdo->prepare('SELECT id, name FROM institutes WHERE id = ?');
            $q->execute([$iid]);
            if ($r = $q->fetch()) $d['institute'] = $r;
        }
        if (tableExists($pdo, 'candidates')) {
            $d['metrics']['students'] = safeCount($pdo, 'SELECT COUNT(*) FROM candidates WHERE institute_id = ?', [$iid]);
            $d['metrics']['talent_profiles'] = $d['metrics']['students'];
            if (tableExists($pdo, 'applications') && columnExists($pdo, 'applications', 'match_score')) {
                $q = $pdo->prepare('SELECT AVG(a.match_score) FROM applications a INNER JOIN candidates c ON c.id = a.candidate_id WHERE c.institute_id = ? AND a.match_score IS NOT NULL');
                $q->execute([$iid]);
                $v = $q->fetchColumn();
                $d['metrics']['average_match'] = ($v === false || $v === null) ? null : round((float)$v, 1);
            }
            $cols = [];
            foreach (['id', 'name', 'role_name', 'qualification', 'graduation_year', 'experience_years', 'email', 'phone', 'location', 'skills', 'created_at'] as $c) {
                if (columnExists($pdo, 'candidates', $c)) $cols[] = "c.$c";
            }
            if ($cols) {
                $q = $pdo->prepare('SELECT ' . implode(',', $cols) . ', sp.degree, sp.branch, sp.semester, sp.cgpa, sp.career_interest, sp.preferred_role, COALESCE((SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ", ") FROM candidate_skills cs INNER JOIN skills s ON s.id = cs.skill_id WHERE cs.candidate_id = c.id), NULL) AS mapped_skills, (SELECT AVG(cs.score) FROM candidate_skills cs WHERE cs.candidate_id = c.id AND cs.verified = 1 AND cs.score IS NOT NULL) AS verified_skill_score FROM candidates c LEFT JOIN student_profiles sp ON sp.candidate_id=c.id WHERE c.institute_id = ? ORDER BY c.id DESC');
                $q->execute([$iid]);
                $d['students'] = $q->fetchAll();
            }
        }
        if (tableExists($pdo, 'opportunities')) {
            $sf = columnExists($pdo, 'opportunities', 'status') ? " WHERE LOWER(status) IN ('active','open','published')" : '';
            $d['metrics']['active_opportunities'] = safeCount($pdo, "SELECT COUNT(*) FROM opportunities$sf");
            $cols = [];
            foreach (['id', 'title', 'type', 'department', 'location', 'required_skills', 'openings', 'qualification', 'min_cgpa', 'allowed_branches', 'deadline', 'status', 'description'] as $c) {
                if (columnExists($pdo, 'opportunities', $c)) $cols[] = $c;
            }
            if ($cols) { $q = $pdo->query('SELECT ' . implode(',', $cols) . ' FROM opportunities ORDER BY id DESC LIMIT 30'); $d['opportunities'] = $q->fetchAll(); }
        }
        if (tableExists($pdo, 'applications') && tableExists($pdo, 'candidates')) {
            $d['metrics']['applications'] = safeCount($pdo, 'SELECT COUNT(*) FROM applications a INNER JOIN candidates c ON c.id=a.candidate_id WHERE c.institute_id = ?', [$iid]);
            $q = $pdo->prepare('SELECT a.status, a.match_score, a.applied_at, o.title, c.name FROM applications a INNER JOIN candidates c ON c.id=a.candidate_id LEFT JOIN opportunities o ON o.id=a.opportunity_id WHERE c.institute_id = ? ORDER BY a.id DESC LIMIT 12');
            $q->execute([$iid]);
            $d['applications'] = $q->fetchAll();
        }
        if (tableExists($pdo, 'candidate_skills') && tableExists($pdo, 'skills') && tableExists($pdo, 'candidates')) {
            $q = $pdo->prepare('SELECT s.name, s.category, s.demand_level, COUNT(DISTINCT cs.candidate_id) student_count FROM skills s INNER JOIN candidate_skills cs ON cs.skill_id=s.id INNER JOIN candidates c ON c.id=cs.candidate_id WHERE c.institute_id = ? GROUP BY s.id, s.name, s.category, s.demand_level ORDER BY student_count DESC, s.name LIMIT 10');
            $q->execute([$iid]);
            $d['skills'] = $q->fetchAll();
            if (tableExists($pdo, 'opportunity_skills')) {
                $q = $pdo->prepare('SELECT s.name, s.demand_level, COUNT(DISTINCT os.opportunity_id) opportunity_count, COUNT(DISTINCT CASE WHEN c.id IS NOT NULL THEN cs.candidate_id END) student_count FROM skills s LEFT JOIN opportunity_skills os ON os.skill_id=s.id LEFT JOIN candidate_skills cs ON cs.skill_id=s.id LEFT JOIN candidates c ON c.id=cs.candidate_id AND c.institute_id = ? WHERE os.skill_id IS NOT NULL GROUP BY s.id, s.name, s.demand_level ORDER BY opportunity_count DESC, s.name LIMIT 10');
                $q->execute([$iid]);
                $d['demand'] = $q->fetchAll();
            }
        }
        if (tableExists($pdo, 'collaboration_requests')) {
            $q = $pdo->prepare('SELECT cr.id, cr.collaboration_type, cr.focus_skills, cr.event_date, cr.status, co.name company_name FROM collaboration_requests cr LEFT JOIN companies co ON co.id=cr.company_id WHERE cr.institute=? OR cr.institute IS NULL ORDER BY cr.id DESC LIMIT 8');
            $q->execute([$d['institute']['name']]);
            $d['collaboration'] = $q->fetchAll();
        }
        if (tableExists($pdo, 'assessment_attempts') && tableExists($pdo, 'candidates')) {
            $q = $pdo->prepare('SELECT aa.*, c.name student_name FROM assessment_attempts aa JOIN candidates c ON c.id=aa.candidate_id WHERE c.institute_id = ? ORDER BY aa.completed_at DESC LIMIT 10');
            $q->execute([$iid]);
            $d['progress'] = $q->fetchAll();
        }
        if (tableExists($pdo, 'student_suggestions') && tableExists($pdo, 'candidates')) {
            $q = $pdo->prepare('SELECT ss.*, c.name student_name FROM student_suggestions ss JOIN candidates c ON c.id=ss.candidate_id WHERE ss.institute_id = ? ORDER BY ss.id DESC');
            $q->execute([$iid]);
            $d['suggestions'] = $q->fetchAll();
        }
        if (tableExists($pdo, 'industry_resources')) {
            $q = $pdo->prepare('SELECT ir.*, (SELECT COUNT(*) FROM resource_acknowledgements ra WHERE ra.resource_id=ir.id AND ra.institute_id=?) AS is_acknowledged, (SELECT COUNT(*) FROM resource_acknowledgements ra WHERE ra.resource_id=ir.id) AS total_acks FROM industry_resources ir ORDER BY ir.id DESC');
            $q->execute([$iid]);
            $d['resources'] = $q->fetchAll();
        }
        if (tableExists($pdo, 'faculty_opportunities')) {
            $q = $pdo->query('SELECT fo.*, c.name company_name FROM faculty_opportunities fo LEFT JOIN companies c ON c.id=fo.company_id ORDER BY fo.id DESC');
            $d['faculty_opportunities'] = $q->fetchAll();
        }
        if (tableExists($pdo, 'faculty_applications')) {
            $q = $pdo->prepare('
                SELECT fa.opportunity_id, fa.status
                FROM faculty_applications fa
                JOIN faculty_profiles fp ON fp.id = fa.faculty_id
                WHERE fp.institute_id = ?
            ');
            $q->execute([$iid]);
            foreach ($q->fetchAll() as $fa) {
                $d['faculty_applications'][(int)$fa['opportunity_id']] = $fa['status'];
            }
        }
        if (tableExists($pdo, 'notifications')) {
            $d['notifications'] = $pdo->query('SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10')->fetchAll();
        }
        if (tableExists($pdo, 'messages')) {
            $d['messages'] = $pdo->query('SELECT * FROM messages ORDER BY created_at DESC LIMIT 10')->fetchAll();
        }
        if (tableExists($pdo, 'candidate_skills') && tableExists($pdo, 'candidates')) {
            $q = $pdo->prepare('SELECT SUM(CASE WHEN x.skill_score >= 80 THEN 1 ELSE 0 END) high_ready, SUM(CASE WHEN x.skill_score >= 60 AND x.skill_score < 80 THEN 1 ELSE 0 END) moderate_ready, SUM(CASE WHEN x.skill_score < 60 THEN 1 ELSE 0 END) developing, SUM(CASE WHEN x.skill_score IS NOT NULL THEN 1 ELSE 0 END) assessed_students FROM (SELECT c.id, AVG(cs.score) skill_score FROM candidates c LEFT JOIN candidate_skills cs ON cs.candidate_id=c.id AND cs.verified=1 AND cs.score IS NOT NULL WHERE c.institute_id=? GROUP BY c.id) x');
            $q->execute([$iid]);
            $r=$q->fetch() ?: [];
            $d['readiness']=['high'=>(int)($r['high_ready']??0),'moderate'=>(int)($r['moderate_ready']??0),'developing'=>(int)($r['developing']??0),'assessed_students'=>(int)($r['assessed_students']??0)];
        } else { $d['readiness']=['high'=>0,'moderate'=>0,'developing'=>0,'assessed_students'=>0]; }
    } catch (Throwable $e) {
        $d['error'] = 'Some data could not be loaded.';
    }
    return $d;
}

$data = dashboardData($pdo, $selectedInstitute);
$qualificationDemand=[]; $cgpaValues=[]; $domainCounts=[];
foreach($data['opportunities'] as $opp){
  $qv=trim((string)($opp['qualification']??''));
  foreach(preg_split('/[,;|]+/',$qv) as $part){$part=trim($part); if($part!=='')$qualificationDemand[$part]=($qualificationDemand[$part]??0)+1;}
  if(($opp['min_cgpa']??'')!=='' && is_numeric($opp['min_cgpa']) && (float)$opp['min_cgpa']>0)$cgpaValues[]=(float)$opp['min_cgpa'];
  foreach(preg_split('/[,\n;|]+/',(string)($opp['required_skills']??'')) as $skill){$skill=trim($skill); if($skill!=='')$domainCounts[$skill]=($domainCounts[$skill]??0)+1;}
}
arsort($qualificationDemand); arsort($domainCounts);
$data['market_insights']=['qualifications'=>array_slice($qualificationDemand,0,6,true),'avg_min_cgpa'=>$cgpaValues?round(array_sum($cgpaValues)/count($cgpaValues),2):null,'domains'=>array_slice($domainCounts,0,6,true)];
$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>SKILLBRIDGE | Institute Portal</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="../assets/css/print.css" media="print">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <img src="assets/skillbridge-logo.jpeg" alt="SkillBridge">
      <div class="brand-copy"><strong>SKILLBRIDGE</strong><small>Academia ↔ Industry Portal</small></div>
    </div>
    <nav class="nav">
      <div class="nav-group-label">Overview</div>
      <?php $nav = [
          ['dashboard', 'Dashboard', 'home'],
          ['students', 'Students', 'users'],
          ['progress', 'Student Progress', 'chart'],
          ['coordination', 'Placement Eligibility', 'target'],
      ]; foreach ($nav as $n): ?>
        <button class="nav-item <?= $n[0] === 'dashboard' ? 'active' : '' ?>" data-section="<?= $n[0] ?>">
          <span class="icon"><?= uiIcon($n[2]) ?></span><span><?= $n[1] ?></span>
        </button>
      <?php endforeach; ?>
      <div class="nav-group-label">Industry</div>
      <?php $nav = [
          ['opportunities', 'Jobs & Internships', 'briefcase'],
          ['applications', 'Applications', 'file'],
          ['collaboration', 'Collaboration', 'handshake'],
          ['resources', 'Industry Resources', 'book'],
          ['faculty', 'Faculty Programs', 'user'],
      ]; foreach ($nav as $n): ?>
        <button class="nav-item" data-section="<?= $n[0] ?>">
          <span class="icon"><?= uiIcon($n[2]) ?></span><span><?= $n[1] ?></span>
        </button>
      <?php endforeach; ?>
      <div class="nav-group-label">Reports</div>
      <button class="nav-item" data-section="reports"><span class="icon"><?= uiIcon('report') ?></span><span>Reports & Analytics</span></button>
    </nav>
    <div class="sidebar-footer">
      <div class="institute-mini">
        <span class="avatar"><?= htmlspecialchars(strtoupper(substr($selectedInstituteName, 0, 1))) ?></span>
        <div><strong><?= htmlspecialchars($selectedInstituteName) ?></strong><small>Institute Admin</small></div>
      </div>
    </div>
  </aside>

  <main class="main">
    <header class="topbar">
      <button class="menu-btn" id="menuBtn" aria-label="Open menu"><?= uiIcon('menu') ?></button>
      <div class="search">
        <span class="search-icon"><?= uiIcon('search') ?></span>
        <input id="globalSearch" placeholder="Search students, opportunities, skills...">
      </div>
      <div class="top-actions">
        <div class="profile">
          <span class="avatar"><?= htmlspecialchars(strtoupper(substr($selectedInstituteName, 0, 1))) ?></span>
          <div><strong><?= htmlspecialchars($selectedInstituteName) ?></strong><small>Institute Admin</small></div>
        </div>
      </div>
      <a href="../logout.php" class="portal-logout">Logout</a>
    </header>

    <div class="content">
      <div class="page-head">
        <div>
          <div class="eyebrow">INSTITUTE COLLABORATION PORTAL</div>
          <h1 id="pageTitle">Institute Dashboard</h1>
          <p id="pageSubtitle">Student talent, skill intelligence and industry alignment.</p>
        </div>
        <div class="institute-select">
          <label>Institute workspace</label>
          <strong><?= htmlspecialchars($selectedInstituteName) ?></strong>
        </div>
      </div>

      <?php if ($notice): ?>
        <div class="notice" style="background:#e0f2fe; color:#0369a1; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-weight:600;"><?= htmlspecialchars($notice) ?></div>
      <?php endif; ?>

      <!-- DASHBOARD -->
      <section id="dashboard" class="page active">
        <div class="hero">
          <div>
            <div class="eyebrow">INSTITUTE WORKSPACE</div>
            <h2>Welcome to <?= htmlspecialchars($selectedInstituteName) ?></h2>
            <p>Monitor student talent, connect with industry opportunities and build evidence-based skill intelligence.</p>
          </div>
          
        </div>
        <div class="metric-grid" id="metricGrid"></div>
        <div class="grid-2">
          <article class="card">
            <div class="card-head"><div><h3>Student Skill Strength</h3><p>Skills represented by current institute profiles.</p></div></div>
            <div id="skillBars" class="bars"></div>
          </article>
          <article class="card">
            <div class="card-head"><div><h3>Industry Demand vs Talent</h3><p>Shared skill data across the SkillBridge ecosystem.</p></div></div>
            <div id="demandBars" class="bars"></div>
          </article>
        </div>
        <div class="grid-2">
          <article class="card table-card">
            <div class="card-head">
              <div><h3>Recent Industry Opportunities</h3><p>Live records from the shared opportunities table.</p></div>
              <button class="link-btn" data-section-link="opportunities">View all <?= uiIcon('arrow') ?></button>
            </div>
            <div class="table-scroll">
              <table><thead><tr><th>Opportunity</th><th>Type</th><th>Location</th><th>Status</th></tr></thead><tbody id="dashboardOpps"></tbody></table>
            </div>
          </article>
          <article class="card table-card">
            <div class="card-head">
              <div><h3>Recent Applications</h3><p>Applications submitted by institute students.</p></div>
              <button class="link-btn" data-section-link="applications">View all <?= uiIcon('arrow') ?></button>
            </div>
            <div class="table-scroll">
              <table><thead><tr><th>Student</th><th>Opportunity</th><th>Status</th></tr></thead><tbody id="dashboardApps"></tbody></table>
            </div>
          </article>
        </div>
      </section>

      <!-- STUDENTS -->
      <section id="students" class="page">
        <div class="section-intro">
          <div><h2>Students</h2><p>Manage and review student profiles using live institute data.</p></div>
          <a href="../export/export_csv.php?type=students" class="secondary-btn" style="display:inline-flex; align-items:center; gap:6px; padding:6px 12px; font-size:13px; font-weight:600; text-decoration:none; background:#f1f5f9; border-radius:6px; color:#334155;"><?= uiIcon('file') ?> Export CSV</a>
        </div>
        <div class="student-toolbar">
          <div class="search-field"><span><?= uiIcon('search') ?></span><input id="studentSearch" placeholder="Search name, role, email or skill..."></div>
          <select id="qualificationFilter"><option value="">All qualifications</option></select>
          <select id="branchFilter"><option value="">All branches</option></select>
        </div>
        <div class="student-summary"><span id="studentResultCount">0 students</span><span>Profiles are sourced from the selected institute.</span></div>
        <div class="card table-card">
          <div class="table-scroll">
            <table class="students-table">
              <thead><tr><th>Student</th><th>Degree / Branch</th><th>CGPA</th><th>Semester</th><th>Verified Skills</th><th></th></tr></thead>
              <tbody id="studentsBody"></tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- PLACEMENT COORDINATION & ELIGIBILITY VIEW -->
      <section id="coordination" class="page">
        <div class="section-intro">
          <div>
            <h2>Placement Coordination & Eligibility View</h2>
            <p>Compare students from this institute against the selected industry's published eligibility rules. Every configured requirement is checked independently and unmet requirements are shown with exact reasons.</p>
          </div>
        </div>

        <div class="card" style="margin-bottom:20px; padding:20px;">
          <label style="font-weight:700; font-size:14px; display:block; margin-bottom:8px;">Select Industry Opportunity to Check Student Eligibility:</label>
          <select id="coordinationOppSelect" style="width:100%; max-width:480px; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px;">
            <option value="">-- Choose an Opportunity --</option>
            <?php foreach ($data['opportunities'] as $opp): ?>
              <option value="<?= (int)$opp['id'] ?>"><?= htmlspecialchars($opp['title']) ?> (<?= htmlspecialchars($opp['type']) ?><?= ((float)($opp['min_cgpa']??0) > 0) ? ' • Min CGPA: '.htmlspecialchars((string)(float)$opp['min_cgpa']) : ' • No CGPA minimum' ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div id="eligibilityResultArea">
          <div class="card empty-state">Select an industry opportunity above to view eligible vs non-eligible candidates with explicit ineligibility reasons.</div>
        </div>
      </section>

      <!-- INDUSTRY DEMAND CENTER -->
      <section id="demand" class="page">
        <div class="section-intro">
          <div>
            <h2>Industry Demand Center</h2>
            <p>Real-time aggregated market demand metrics derived from active industry opportunity postings.</p>
          </div>
        </div>

        <div class="grid-2">
          <article class="card" style="padding:20px;">
            <h3>Top Requested Skills in Market</h3>
            <p style="color:#64748b; font-size:13px; margin-bottom:16px;">Skills required most frequently across posted opportunities.</p>
            <div class="bars">
              <?php foreach ($data['demand'] as $dm): ?>
                <div class="bar-row">
                  <span><b><?= htmlspecialchars($dm['name']) ?></b></span>
                  <div class="bar-track"><div class="bar-fill" style="width:<?= min(100, (int)$dm['opportunity_count']*25) ?>%; background:#2563eb;"></div></div>
                  <span class="bar-value"><?= (int)$dm['opportunity_count'] ?> Opportunities</span>
                </div>
              <?php endforeach; ?>
              <?php if (!$data['demand']): ?>
                <p style="color:#94a3b8; font-size:13px;">No skill demand data accumulated yet.</p>
              <?php endif; ?>
            </div>
          </article>

          <article class="card" style="padding:20px;">
            <h3>Academic Requirements & Qualification Demand</h3>
            <p style="color:#64748b; font-size:13px; margin-bottom:16px;">Qualifications and branches requested by recruiting companies.</p>
            <div style="display:flex; flex-direction:column; gap:12px;">
              <div style="background:#f8fafc; padding:12px; border-radius:8px;">
                <strong style="color:#0f172a; font-size:14px; display:block;">Qualifications in current postings</strong>
                <span id="qualificationDemand">No opportunity qualification data available.</span>
              </div>
              <div style="background:#f8fafc; padding:12px; border-radius:8px;">
                <strong style="color:#0f172a; font-size:14px; display:block;">Average minimum CGPA</strong>
                <span id="cgpaDemand">No CGPA requirement data available.</span>
              </div>
              <div style="background:#f8fafc; padding:12px; border-radius:8px;">
                <strong style="color:#0f172a; font-size:14px; display:block;">Top required skills</strong>
                <span id="domainDemand">No skill-demand data available.</span>
              </div>
            </div>
          </article>
        </div>
      </section>

      <!-- STUDENT PROGRESS & SUGGESTIONS -->
      <section id="progress" class="page">
        <div class="section-intro">
          <div><h2>Student Progress & Faculty Suggestions</h2><p>Track skill progress, assessment scores, and faculty improvement recommendations.</p></div>
          <button class="secondary-btn" id="openSuggestionBtn" style="display:inline-flex; align-items:center; gap:6px; padding:8px 14px; font-size:13px; font-weight:600; background:#2563eb; color:#fff; border:none; border-radius:6px; cursor:pointer;"><?= uiIcon('plus') ?> Create Faculty Suggestion</button>
        </div>
        
        <div class="card" style="margin-bottom:24px; padding:20px;">
          <h3>Faculty Suggestions / Improvement Plans</h3>
          <p style="color:#64748b; font-size:13px; margin-bottom:16px;">Targeted recommendations issued by faculty to help students close skill gaps.</p>
          <div class="table-scroll">
            <table>
              <thead><tr><th>Student</th><th>Title / Target</th><th>Recommendation</th><th>Priority</th><th>Target Score</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($data['suggestions'] as $sg): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($sg['student_name']) ?></strong></td>
                    <td><b><?= htmlspecialchars($sg['title']) ?></b></td>
                    <td><?= htmlspecialchars($sg['suggestion_text']) ?><br><small style="color:#2563eb;">Action: <?= htmlspecialchars($sg['recommended_action']) ?></small></td>
                    <td><span style="padding:2px 8px; border-radius:12px; font-size:11px; font-weight:700; background:<?= $sg['priority']==='High'?'#fef2f2; color:#dc2626;':($sg['priority']==='Medium'?'#fffbeb; color:#d97706;':'#f0fdf4; color:#16a34a;') ?>"><?= htmlspecialchars($sg['priority']) ?></span></td>
                    <td><b><?= (int)$sg['target_score'] ?>%</b></td>
                    <td><span class="status success"><?= htmlspecialchars($sg['status']) ?></span></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$data['suggestions']): ?>
                  <tr><td colspan="6" class="empty-cell">No faculty suggestions recorded yet. Click "Create Faculty Suggestion" above to create one.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card" style="padding:20px;">
          <h3>Recent Student Assessment Records</h3>
          <p style="color:#64748b; font-size:13px; margin-bottom:16px;">Verified assessment attempts and scores completed by students.</p>
          <div class="table-scroll">
            <table>
              <thead><tr><th>Student</th><th>Category / Skill</th><th>Score</th><th>Passed</th><th>Date</th></tr></thead>
              <tbody>
                <?php foreach ($data['progress'] as $pr): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($pr['student_name']) ?></strong></td>
                    <td><?= htmlspecialchars($pr['category'] ?? 'General Assessment') ?></td>
                    <td><b><?= round((float)($pr['score_percent'] ?? $pr['score'] ?? 0)) ?>%</b></td>
                    <td><span class="status <?= !empty($pr['passed'])?'success':'danger' ?>"><?= !empty($pr['passed'])?'Passed':'Needs Improvement' ?></span></td>
                    <td><?= htmlspecialchars(date('d M Y', strtotime((string)($pr['completed_at'] ?? 'now')))) ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$data['progress']): ?>
                  <tr><td colspan="5" class="empty-cell">No student assessment attempts completed yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- INDUSTRY INSIGHTS & RESOURCES -->
      <section id="resources" class="page">
        <div class="section-intro">
          <div><h2>Industry Insights & Faculty Resources</h2><p>Technical question banks, interview topics, and curriculum recommendations published by companies.</p></div>
        </div>
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:20px;">
          <?php foreach ($data['resources'] as $res): ?>
            <div class="card" style="padding:20px; display:flex; flex-direction:column; justify-content:space-between;">
              <div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                  <span style="font-size:11px; font-weight:700; background:#e0f2fe; color:#0369a1; padding:2px 8px; border-radius:12px;"><?= htmlspecialchars($res['audience']) ?></span>
                  <small style="color:#64748b;"><?= (int)$res['total_acks'] ?> Acknowledged</small>
                </div>
                <h3 style="margin:0 0 6px 0; font-size:16px;"><?= htmlspecialchars($res['title']) ?></h3>
                <p style="font-size:13px; color:#475569; margin:0 0 10px 0;"><strong>Topic:</strong> <?= htmlspecialchars($res['topic']) ?></p>
                <p style="font-size:13px; color:#64748b; line-height:1.4; margin-bottom:12px;"><?= htmlspecialchars($res['description'] ?: 'No details provided.') ?></p>
                <?php if ($res['resource_url']): ?>
                  <div style="margin-bottom:12px;"><a href="<?= htmlspecialchars($res['resource_url']) ?>" target="_blank" style="color:#2563eb; font-weight:700; font-size:12px; text-decoration:none;">🌐 External Reference Link</a></div>
                <?php endif; ?>
              </div>
              <div style="border-top:1px solid #f1f5f9; pt-12px; margin-top:12px; text-align:right;">
                <?php if (!empty($res['is_acknowledged'])): ?>
                  <span style="color:#16a34a; font-weight:700; font-size:13px; display:inline-flex; align-items:center; gap:4px;"><?= uiIcon('check') ?> Acknowledged by Faculty</span>
                <?php else: ?>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="acknowledge_resource">
                    <input type="hidden" name="resource_id" value="<?= (int)$res['id'] ?>">
                    <button type="submit" style="background:#2563eb; color:#fff; border:none; padding:6px 12px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer;">Acknowledge Resource</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (!$data['resources']): ?>
            <div class="card empty-state" style="grid-column: 1/-1;">No industry resources published yet.</div>
          <?php endif; ?>
        </div>
      </section>

      <!-- PLACEMENT READINESS -->
      <section id="readiness" class="page">
        <div class="section-intro">
          <div><h2>Placement Readiness</h2><p>Overview of student placement readiness based on assessment scores and skill profiles.</p></div>
        </div>
        <div class="grid-2">
          <div class="card" style="padding:20px;">
            <h3>Readiness Distribution</h3>
            <p style="color:#64748b; font-size:13px; margin-bottom:16px;">Breakdown of institute candidates ready for placement.</p>
            <div id="readinessBars" class="bars"></div>
              <div class="readiness-note"><strong id="readinessAssessed">0</strong> students have verified assessment/skill evidence used for this view.</div>
          </div>
          <div class="card" style="padding:20px;">
            <h3>Top Ready Candidates</h3>
            <p style="color:#64748b; font-size:13px; margin-bottom:16px;">Students with the strongest verified skill evidence.</p>
            <div class="table-scroll">
              <table>
                <thead><tr><th>Student</th><th>Branch</th><th>Verified Skill Score</th></tr></thead><tbody id="readinessStudentsBody"></tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- JOBS & INTERNSHIPS -->
      <section id="opportunities" class="page">
        <div class="section-intro"><div><h2>Industry Opportunities</h2><p>Jobs and internships published by the Industry Portal.</p></div></div>
        <div class="card table-card">
          <div class="table-scroll">
            <table><thead><tr><th>Opportunity</th><th>Type</th><th>Department</th><th>Location</th><th>Deadline</th><th>Status</th></tr></thead><tbody id="opportunitiesBody"></tbody></table>
          </div>
        </div>
      </section>

      <!-- APPLICATIONS -->
      <section id="applications" class="page">
        <div class="section-intro">
          <div><h2>Applications & Placement</h2><p>Application records linked to students from the selected institute.</p></div>
          <a href="../export/export_csv.php?type=applications" class="secondary-btn" style="display:inline-flex; align-items:center; gap:6px; padding:6px 12px; font-size:13px; font-weight:600; text-decoration:none; background:#f1f5f9; border-radius:6px; color:#334155;"><?= uiIcon('file') ?> Export CSV</a>
        </div>
        <div class="card table-card">
          <div class="table-scroll">
            <table><thead><tr><th>Student</th><th>Opportunity</th><th>Match</th><th>Status</th><th>Applied</th></tr></thead><tbody id="applicationsBody"></tbody></table>
          </div>
        </div>
      </section>

      <!-- INDUSTRY COLLABORATION -->
      <section id="collaboration" class="page">
        <div class="section-intro"><div><h2>Industry Collaboration</h2><p>Workshops, campus drives, live projects and research collaboration.</p></div></div>
        <div class="card table-card">
          <div class="table-scroll">
            <table><thead><tr><th>Company</th><th>Type</th><th>Focus</th><th>Date</th><th>Status</th></tr></thead><tbody id="collaborationBody"></tbody></table>
          </div>
        </div>
      </section>

      <!-- FACULTY DASHBOARD -->
      <section id="faculty" class="page">
        <div class="section-intro">
          <div><h2>Faculty Dashboard & Opportunities</h2><p>Explore faculty internships, FDPs, workshops, and research collaborations offered by industry.</p></div>
        </div>
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:20px;">
          <?php foreach ($data['faculty_opportunities'] as $fo): ?>
            <div class="card" style="padding:20px;">
              <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span style="font-size:11px; font-weight:700; background:#f0fdf4; color:#16a34a; padding:2px 8px; border-radius:12px;"><?= htmlspecialchars($fo['type']) ?></span>
                <small style="color:#64748b;"><?= htmlspecialchars($fo['location'] ?: 'Remote') ?></small>
              </div>
              <h3 style="margin:0 0 6px 0; font-size:16px;"><?= htmlspecialchars($fo['title']) ?></h3>
              <p style="font-size:13px; color:#2563eb; font-weight:600; margin:0 0 10px 0;"><?= htmlspecialchars($fo['company_name'] ?: 'Industry Partner') ?></p>
              <p style="font-size:13px; color:#64748b; margin-bottom:10px;"><strong>Duration:</strong> <?= htmlspecialchars($fo['duration'] ?: 'N/A') ?> | <strong>Required Skills:</strong> <?= htmlspecialchars($fo['required_skills'] ?: 'General') ?></p>
              <p style="font-size:13px; color:#475569; line-height:1.4; margin-bottom:12px;"><?= htmlspecialchars($fo['description'] ?: 'No details provided.') ?></p>
              <?php $facultyStatus = $data['faculty_applications'][(int)$fo['id']] ?? null; ?>
              <?php if ($facultyStatus): ?>
                <div style="background:#f0fdf4; color:#166534; padding:8px 12px; border-radius:6px; font-size:13px; font-weight:700; text-align:center;">Application: <?= htmlspecialchars($facultyStatus) ?></div>
              <?php else: ?>
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                  <input type="hidden" name="action" value="apply_faculty_opportunity">
                  <input type="hidden" name="opportunity_id" value="<?= (int)$fo['id'] ?>">
                  <button type="submit" style="background:#2563eb; color:#fff; border:none; padding:6px 14px; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; width:100%;">Apply for Faculty Program</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          <?php if (!$data['faculty_opportunities']): ?>
            <div class="card empty-state" style="grid-column: 1/-1;">No faculty opportunities published by industry currently.</div>
          <?php endif; ?>
        </div>
      </section>

      <!-- REPORTS & ANALYTICS -->
      <section id="reports" class="page">
        <div class="section-intro">
          <div><h2>Reports & Analytics</h2><p>Operational analytics derived from live database records.</p></div>
          <button onclick="window.print()" class="secondary-btn" style="display:inline-flex; align-items:center; gap:6px; padding:6px 12px; font-size:13px; font-weight:600; background:#2563eb; color:#fff; border:none; border-radius:6px; cursor:pointer;"><?= uiIcon('report') ?> Print / Export Report</button>
        </div>
        <div class="metric-grid" id="analyticsMetrics"></div>
        <div class="card" style="padding:20px;"><div class="card-head"><h3>Data Availability Health</h3></div><div id="dataHealth" class="health-list"></div></div>
      </section>

      <!-- NOTIFICATIONS -->
      <section id="notifications" class="page">
        <div class="section-intro"><h2>Notifications</h2><p>Institute notification center.</p></div>
        <div class="empty-state"><span><?= uiIcon('bell') ?></span><h3>No unread notifications</h3><p>Your institute notification center is up to date.</p></div>
      </section>

      <!-- MESSAGES -->
      <section id="messages" class="page">
        <div class="section-intro"><h2>Messages</h2><p>Institute communication center.</p></div>
        <div class="empty-state"><span><?= uiIcon('mail') ?></span><h3>No messages</h3><p>Internal message history will appear here.</p></div>
      </section>
    </div>
  </main>
</div>

<!-- STUDENT PROFILE MODAL -->
<div class="modal-backdrop" id="studentModal" aria-hidden="true">
  <div class="profile-modal" role="dialog" aria-modal="true" aria-labelledby="profileName">
    <button class="modal-close" id="modalClose" aria-label="Close"><?= uiIcon('close') ?></button>
    <div id="profileContent"><div class="profile-loading">Loading student profile...</div></div>
  </div>
</div>

<!-- FACULTY SUGGESTION MODAL -->
<div class="modal-backdrop" id="suggestionModal" style="display:none; align-items:center; justify-content:center; background:rgba(0,0,0,0.5); position:fixed; top:0; left:0; right:0; bottom:0; z-index:9999;">
  <div style="background:#fff; border-radius:12px; padding:24px; max-width:500px; width:90%;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h3 style="margin:0; font-size:18px;">Create Faculty Suggestion</h3>
      <button onclick="document.getElementById('suggestionModal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer;">×</button>
    </div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="action" value="save_suggestion">
      <div style="margin-bottom:12px;">
        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Select Student</label>
        <select name="candidate_id" required style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
          <?php foreach ($data['students'] as $st): ?>
            <option value="<?= (int)$st['id'] ?>"><?= htmlspecialchars($st['name']) ?> (<?= htmlspecialchars($st['role_name'] ?: 'Student') ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="margin-bottom:12px;">
        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Title</label>
        <input name="title" required placeholder="e.g. Improve SQL Query Optimization" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
      </div>
      <div style="margin-bottom:12px;">
        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Recommendation Details</label>
        <textarea name="suggestion_text" rows="3" placeholder="Explain what the student should work on..." style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;"></textarea>
      </div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
        <div>
          <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Priority</label>
          <select name="priority" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
            <option value="High">High</option>
            <option value="Medium" selected>Medium</option>
            <option value="Low">Low</option>
          </select>
        </div>
        <div>
          <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Target Score (%)</label>
          <input type="number" name="target_score" value="80" min="50" max="100" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
        </div>
      </div>
      <div style="margin-bottom:16px;">
        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Recommended Action</label>
        <input name="recommended_action" placeholder="e.g. Complete SQL Practice Assessment" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
      </div>
      <div style="display:flex; justify-content:flex-end; gap:8px;">
        <button type="button" onclick="document.getElementById('suggestionModal').style.display='none'" style="padding:8px 16px; background:#f1f5f9; border:none; border-radius:6px; cursor:pointer;">Cancel</button>
        <button type="submit" style="padding:8px 16px; background:#2563eb; color:#fff; border:none; border-radius:6px; cursor:pointer;">Submit Suggestion</button>
      </div>
    </form>
  </div>
</div>

<div class="toast" id="toast"></div>
<script>
window.INSTITUTE_DATA = <?= $json ?>;
window.SELECTED_INSTITUTE_ID = <?= $selectedInstitute ?>;
window.STUDENT_API = 'api/student.php';
document.getElementById('openSuggestionBtn')?.addEventListener('click', function() {
  document.getElementById('suggestionModal').style.display = 'flex';
});
</script>
<script src="script.js"></script>
</body>
</html>
