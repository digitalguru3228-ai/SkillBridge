<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/notifications.php';
requireRole('student');

$pdo = db();
$candidateId = getStudentCandidateId($pdo);

if ($candidateId <= 0) {
    renderUnlinkedCandidateError();
}

$msg = ''; $err = '';

// Handle POST actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $err = "Invalid security token. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';


    try {
        if ($action === 'save_profile') {
            $name = trim((string)($_POST['name'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $location = trim((string)($_POST['location'] ?? ''));
            $bio = trim((string)($_POST['bio'] ?? ''));
            
            $degree = trim((string)($_POST['degree'] ?? ''));
            $branch = trim((string)($_POST['branch'] ?? ''));
            $semester = (int)($_POST['semester'] ?? 7);
            $cgpa = (float)($_POST['cgpa'] ?? 0.0);
            $gradYear = (int)($_POST['graduation_year'] ?? 2026);
            $careerInterest = trim((string)($_POST['career_interest'] ?? ''));
            
            $linkedin = trim((string)($_POST['linkedin_url'] ?? ''));
            $github = trim((string)($_POST['github_url'] ?? ''));
            $portfolio = trim((string)($_POST['portfolio_url'] ?? ''));
            $website = trim((string)($_POST['website_url'] ?? ''));

            if ($linkedin !== '' && !filter_var($linkedin, FILTER_VALIDATE_URL)) throw new RuntimeException("Invalid LinkedIn URL format.");
            if ($github !== '' && !filter_var($github, FILTER_VALIDATE_URL)) throw new RuntimeException("Invalid GitHub URL format.");
            if ($portfolio !== '' && !filter_var($portfolio, FILTER_VALIDATE_URL)) throw new RuntimeException("Invalid Portfolio URL format.");

            // Update candidates table
            $pdo->prepare("UPDATE candidates SET name = ?, phone = ?, location = ?, bio = ?, graduation_year = ? WHERE id = ?")->execute([$name, $phone, $location, $bio, $gradYear, $candidateId]);

            // Upsert student_profiles table
            $spCheck = $pdo->prepare("SELECT id FROM student_profiles WHERE candidate_id = ? LIMIT 1");
            $spCheck->execute([$candidateId]);
            if ($spCheck->fetchColumn()) {
                $spStmt = $pdo->prepare("UPDATE student_profiles SET degree = ?, branch = ?, semester = ?, cgpa = ?, career_interest = ?, linkedin_url = ?, github_url = ?, portfolio_url = ?, website_url = ? WHERE candidate_id = ?");
                $spStmt->execute([$degree, $branch, $semester, $cgpa, $careerInterest, $linkedin, $github, $portfolio, $website, $candidateId]);
            } else {
                $spStmt = $pdo->prepare("INSERT INTO student_profiles (candidate_id, degree, branch, semester, cgpa, career_interest, linkedin_url, github_url, portfolio_url, website_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $spStmt->execute([$candidateId, $degree, $branch, $semester, $cgpa, $careerInterest, $linkedin, $github, $portfolio, $website]);
            }

            sbNotifyUser($pdo,(int)($_SESSION['user_id'] ?? 0),'Profile updated','Your SKILLBRIDGE profile details were updated successfully.','profile','candidate',$candidateId,true);
            $msg = "Profile updated successfully!";
        }

        if ($action === 'add_project') {
            $pName = trim((string)($_POST['project_name'] ?? ''));
            $pDesc = trim((string)($_POST['description'] ?? ''));
            $pTech = trim((string)($_POST['tech_stack'] ?? ''));
            $pGh = trim((string)($_POST['github_url'] ?? ''));
            $pLive = trim((string)($_POST['live_url'] ?? ''));
            if ($pName === '') throw new RuntimeException("Project name is required.");

            $pdo->prepare("INSERT INTO student_projects (candidate_id, project_name, description, tech_stack, github_url, live_url) VALUES (?, ?, ?, ?, ?, ?)")->execute([$candidateId, $pName, $pDesc, $pTech, $pGh, $pLive]);
            $msg = "Project added!";
        }

        if ($action === 'delete_project') {
            $pid = (int)($_POST['id'] ?? 0);
            $pdo->prepare("DELETE FROM student_projects WHERE id = ? AND candidate_id = ?")->execute([$pid, $candidateId]);
            $msg = "Project removed.";
        }

        if ($action === 'upload_certificate') {
            $cName = trim((string)($_POST['certificate_name'] ?? ''));
            $cOrg = trim((string)($_POST['issuing_org'] ?? ''));
            $cDate = trim((string)($_POST['issue_date'] ?? '')) ?: null;
            $cCred = trim((string)($_POST['credential_id'] ?? ''));
            if ($cName === '') throw new RuntimeException("Certificate name is required.");

            $filePath = null;
            if (isset($_FILES['cert_file']) && $_FILES['cert_file']['error'] === UPLOAD_ERR_OK) {
                $tmp = $_FILES['cert_file']['tmp_name'];
                $size = $_FILES['cert_file']['size'];
                $origName = $_FILES['cert_file']['name'];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

                if ($size > 5242880) throw new RuntimeException("Certificate file must be under 5MB.");
                if (!in_array($ext, ['pdf', 'png', 'jpg', 'jpeg'], true)) throw new RuntimeException("Invalid file format. Only PDF, PNG, and JPG allowed.");

                $uploadDir = __DIR__ . '/../uploads/certificates/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                $fileName = 'cert_' . $candidateId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $targetFile = $uploadDir . $fileName;

                if (move_uploaded_file($tmp, $targetFile)) {
                    $filePath = 'uploads/certificates/' . $fileName;
                } else {
                    throw new RuntimeException("Failed to save uploaded certificate file.");
                }
            }

            $pdo->prepare("INSERT INTO student_certificates (candidate_id, certificate_name, issuing_org, issue_date, credential_id, file_path) VALUES (?, ?, ?, ?, ?, ?)")->execute([$candidateId, $cName, $cOrg, $cDate, $cCred, $filePath]);
            $msg = "Certificate uploaded successfully!";
        }

        if ($action === 'delete_certificate') {
            $cid = (int)($_POST['id'] ?? 0);
            $cStmt = $pdo->prepare("SELECT file_path FROM student_certificates WHERE id = ? AND candidate_id = ?");
            $cStmt->execute([$cid, $candidateId]);
            $file = $cStmt->fetchColumn();
            if ($file && file_exists(__DIR__ . '/../' . $file)) {
                @unlink(__DIR__ . '/../' . $file);
            }
            $pdo->prepare("DELETE FROM student_certificates WHERE id = ? AND candidate_id = ?")->execute([$cid, $candidateId]);
            $msg = "Certificate deleted.";
        }

        if ($action === 'add_achievement') {
            $title = trim((string)($_POST['title'] ?? ''));
            $category = trim((string)($_POST['category'] ?? 'General'));
            $desc = trim((string)($_POST['description'] ?? ''));
            $date = trim((string)($_POST['achievement_date'] ?? '')) ?: null;
            if ($title === '') throw new RuntimeException("Achievement title is required.");

            $pdo->prepare("INSERT INTO student_achievements (candidate_id, title, category, description, achievement_date) VALUES (?, ?, ?, ?, ?)")->execute([$candidateId, $title, $category, $desc, $date]);
            $msg = "Achievement added!";
        }

        if ($action === 'delete_achievement') {
            $aid = (int)($_POST['id'] ?? 0);
            $pdo->prepare("DELETE FROM student_achievements WHERE id = ? AND candidate_id = ?")->execute([$aid, $candidateId]);
            $msg = "Achievement removed.";
        }

    } catch (Throwable $ex) {
        $err = $ex->getMessage();
    }
    }
}


// Fetch Candidate & Extended Data
$cand = $pdo->prepare("
    SELECT c.*, sp.degree, sp.branch, sp.semester, sp.cgpa, sp.career_interest, sp.linkedin_url, sp.github_url, sp.portfolio_url, sp.website_url 
    FROM candidates c 
    LEFT JOIN student_profiles sp ON sp.candidate_id = c.id 
    WHERE c.id = ? LIMIT 1
");
$cand->execute([$candidateId]);
$cData = $cand->fetch() ?: [];

// Fetch Projects, Certs, Achievements
$pStmt = $pdo->prepare("SELECT * FROM student_projects WHERE candidate_id = ? ORDER BY created_at DESC");
$pStmt->execute([$candidateId]);
$projects = $pStmt->fetchAll();

$cStmt = $pdo->prepare("SELECT * FROM student_certificates WHERE candidate_id = ? ORDER BY created_at DESC");
$cStmt->execute([$candidateId]);
$certificates = $cStmt->fetchAll();

$aStmt = $pdo->prepare("SELECT * FROM student_achievements WHERE candidate_id = ? ORDER BY created_at DESC");
$aStmt->execute([$candidateId]);
$achievements = $aStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SkillBridge | Edit My Profile & Digital Portfolio</title>
<link rel="stylesheet" href="../Industry%20dashboard/style.css">
<style>
body { background: #f8fafc; }
.edit-shell { max-width: 900px; margin: 40px auto; padding: 0 20px; }
.card-section { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 28px; margin-bottom: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
</style>
</head>
<body>

<div class="edit-shell">
  <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <a href="index.php" style="color: #2563eb; font-weight: 600; text-decoration: none;">← Back to Dashboard</a>
    <a href="portfolio.php" class="btn secondary" style="text-decoration:none;">View Digital Portfolio</a>
  </div>

  <?php if($msg): ?><div class="flash success"><?= e($msg) ?></div><?php endif; ?>
  <?php if($err): ?><div class="flash error"><?= e($err) ?></div><?php endif; ?>

  <!-- BASIC & ACADEMIC INFO FORM -->
  <div class="card-section">
    <div class="modal-header" style="margin-bottom: 20px;"><span class="eyebrow">PROFILE EDITOR</span><h2>Basic & Academic Details</h2></div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="save_profile">
      <div class="form-grid-2">
        <label>Full Name<input class="input" name="name" value="<?= e($cData['name']??'') ?>" required></label>
        <label>Email<input class="input" value="<?= e($cData['email']??'') ?>" readonly style="background:#f1f5f9;"></label>
        <label>Phone<input class="input" name="phone" value="<?= e($cData['phone']??'') ?>"></label>
        <label>Location<input class="input" name="location" value="<?= e($cData['location']??'') ?>"></label>
        <label>Degree<input class="input" name="degree" value="<?= e($cData['degree']??'B.Tech') ?>"></label>
        <label>Branch<input class="input" name="branch" value="<?= e($cData['branch']??'Computer Science') ?>"></label>
        <label>Semester<input class="input" type="number" min="1" max="10" name="semester" value="<?= (int)($cData['semester']??7) ?>"></label>
        <label>CGPA<input class="input" type="number" step="0.01" min="0" max="10" name="cgpa" value="<?= (float)($cData['cgpa']??8.4) ?>"></label>
        <label>Graduation Year<input class="input" type="number" name="graduation_year" value="<?= (int)($cData['graduation_year']??2026) ?>"></label>
        <label>Career Interest<input class="input" name="career_interest" value="<?= e($cData['career_interest']??'Software Engineering') ?>"></label>
      </div>
      <label>About Me / Bio<textarea class="input textarea" name="bio" rows="3"><?= e($cData['bio']??'') ?></textarea></label>

      <h3 style="font-size: 16px; margin: 24px 0 12px; color: #334155;">Professional Links</h3>
      <div class="form-grid-2">
        <label>LinkedIn URL<input class="input" type="url" name="linkedin_url" value="<?= e($cData['linkedin_url']??'') ?>" placeholder="https://linkedin.com/in/..."></label>
        <label>GitHub URL<input class="input" type="url" name="github_url" value="<?= e($cData['github_url']??'') ?>" placeholder="https://github.com/..."></label>
        <label>Portfolio URL<input class="input" type="url" name="portfolio_url" value="<?= e($cData['portfolio_url']??'') ?>" placeholder="https://myportfolio.com"></label>
        <label>Website URL<input class="input" type="url" name="website_url" value="<?= e($cData['website_url']??'') ?>" placeholder="https://mywebsite.com"></label>
      </div>

      <button class="btn primary" type="submit" style="margin-top: 16px;">Save Profile Changes</button>
    </form>
  </div>

  <!-- PROJECTS SECTION -->
  <div class="card-section">
    <div class="modal-header" style="margin-bottom: 20px;"><span class="eyebrow">PORTFOLIO PROJECTS</span><h2>Projects</h2></div>
    <form method="post" style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid #e2e8f0;">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="add_project">
      <div class="form-grid-2">
        <label>Project Name<input class="input" name="project_name" required></label>
        <label>Tech Stack<input class="input" name="tech_stack" placeholder="PHP, MySQL, React, Python"></label>
        <label>GitHub URL<input class="input" type="url" name="github_url"></label>
        <label>Live URL<input class="input" type="url" name="live_url"></label>
      </div>
      <label>Description<textarea class="input textarea" name="description" rows="2"></textarea></label>
      <button class="btn secondary" type="submit" style="margin-top: 12px;">+ Add Project</button>
    </form>

    <div>
      <?php foreach($projects as $p): ?>
        <div style="background:#f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
          <div>
            <strong><?= e($p['project_name']) ?></strong> <small>(<?= e($p['tech_stack']) ?>)</small>
            <p style="margin: 4px 0 0; font-size: 13px; color: #475569;"><?= e($p['description']) ?></p>
          </div>
          <form method="post">
            <input type="hidden" name="action" value="delete_project">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button class="text-btn" style="color:#ef4444;" type="submit" onclick="return confirm('Remove project?')">Delete</button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if(!$projects): ?><p style="color:#64748b; font-size: 13px;">No projects added yet.</p><?php endif; ?>
    </div>
  </div>

  <!-- CERTIFICATES SECTION -->
  <div class="card-section">
    <div class="modal-header" style="margin-bottom: 20px;"><span class="eyebrow">VERIFIED CERTIFICATIONS</span><h2>Certificates & Uploads</h2></div>
    <form method="post" enctype="multipart/form-data" style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid #e2e8f0;">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="upload_certificate">
      <div class="form-grid-2">
        <label>Certificate Name<input class="input" name="certificate_name" required></label>
        <label>Issuing Organization<input class="input" name="issuing_org" placeholder="e.g. AWS, Coursera"></label>
        <label>Issue Date<input class="input" type="date" name="issue_date"></label>
        <label>Credential ID<input class="input" name="credential_id"></label>
      </div>
      <label>Attach File (PDF / Image, Max 5MB)
        <input class="input" type="file" name="cert_file" accept=".pdf,.png,.jpg,.jpeg">
      </label>
      <button class="btn secondary" type="submit" style="margin-top: 12px;">+ Upload Certificate</button>
    </form>

    <div>
      <?php foreach($certificates as $c): ?>
        <div style="background:#f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
          <div>
            <strong><?= e($c['certificate_name']) ?></strong> <small>(<?= e($c['issuing_org']) ?>)</small>
            <?php if($c['file_path']): ?>
              <br><a href="../<?= e($c['file_path']) ?>" target="_blank" style="color:#2563eb; font-size:12px; font-weight:600;">📄 View Uploaded Document</a>
            <?php endif; ?>
          </div>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="delete_certificate">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button class="text-btn" style="color:#ef4444;" type="submit" onclick="return confirm('Remove certificate?')">Delete</button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if(!$certificates): ?><p style="color:#64748b; font-size: 13px;">No certificates uploaded yet.</p><?php endif; ?>
    </div>
  </div>

  <!-- ACHIEVEMENTS SECTION -->
  <div class="card-section">
    <div class="modal-header" style="margin-bottom: 20px;"><span class="eyebrow">HONORS & RECOGNITION</span><h2>Achievements</h2></div>
    <form method="post" style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid #e2e8f0;">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="add_achievement">
      <div class="form-grid-2">
        <label>Title<input class="input" name="title" required placeholder="e.g. 1st Place - SIH Hackathon"></label>
        <label>Category<input class="input" name="category" placeholder="Hackathon, Academic, Leadership"></label>
        <label>Date<input class="input" type="date" name="achievement_date"></label>
      </div>
      <label>Description<textarea class="input textarea" name="description" rows="2" placeholder="Brief summary of achievement..."></textarea></label>
      <button class="btn secondary" type="submit" style="margin-top: 12px;">+ Add Achievement</button>
    </form>

    <div>
      <?php foreach($achievements as $ach): ?>
        <div style="background:#f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
          <div>
            <strong>🏆 <?= e($ach['title']) ?></strong> <small>(<?= e($ach['category']?:'General') ?>)</small>
            <p style="margin: 4px 0 0; font-size: 13px; color: #475569;"><?= e($ach['description']) ?></p>
          </div>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="delete_achievement">
            <input type="hidden" name="id" value="<?= (int)$ach['id'] ?>">
            <button class="text-btn" style="color:#ef4444;" type="submit" onclick="return confirm('Remove achievement?')">Delete</button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if(!$achievements): ?><p style="color:#64748b; font-size: 13px;">No achievements added yet.</p><?php endif; ?>
    </div>
  </div>
</div>

</body>
</html>
