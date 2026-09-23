<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
requireRole('student');

function e(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}

$pdo = db();
$candidateId = getStudentCandidateId($pdo);

if ($candidateId <= 0) {
    renderUnlinkedCandidateError();
}

$studentName = $_SESSION['user_name'] ?? 'Student User';

// Industry-defined pre-placement assessments available to this student.
// An assessment linked to an opportunity is visible only after the student has
// applied for that opportunity.
$requestedAssessmentId = (int)($_GET['assessment_id'] ?? 0);
$industryAssessments = [];
$iaStmt = $pdo->prepare("
    SELECT DISTINCT
           ia.id,
           ia.title,
           ia.description,
           ia.duration_mins,
           ia.passing_score_pct,
           ia.opportunity_id,
           ia.created_at,
           o.title AS opportunity_title,
           (
               SELECT COUNT(*)
               FROM industry_assessment_questions iaq
               WHERE iaq.assessment_id = ia.id
           ) AS question_count
    FROM industry_assessments ia
    LEFT JOIN opportunities o
        ON o.id = ia.opportunity_id
    LEFT JOIN applications a
        ON a.opportunity_id = ia.opportunity_id
       AND a.candidate_id = ?
    WHERE ia.status = 'Active'
      AND (ia.opportunity_id IS NULL OR a.id IS NOT NULL)
    ORDER BY ia.created_at DESC, ia.id DESC
");
$iaStmt->execute([$candidateId]);
$industryAssessments = $iaStmt->fetchAll();

$selectedIndustryAssessment = null;
if ($requestedAssessmentId > 0) {
    foreach ($industryAssessments as $ia) {
        if ((int)$ia['id'] === $requestedAssessmentId) {
            $selectedIndustryAssessment = $ia;
            break;
        }
    }
} elseif (count($industryAssessments) === 1) {
    $selectedIndustryAssessment = $industryAssessments[0];
}

// Check if an attempt is currently in progress
$activeStmt = $pdo->prepare("SELECT * FROM assessment_attempts WHERE candidate_id = ? AND status = 'In Progress' ORDER BY id DESC LIMIT 1");
$activeStmt->execute([$candidateId]);
$activeAttempt = $activeStmt->fetch();

$answeredCount = 0;
if ($activeAttempt) {
    $aCountStmt = $pdo->prepare("SELECT COUNT(*) FROM assessment_responses WHERE attempt_id = ?");
    $aCountStmt->execute([(int)$activeAttempt['id']]);
    $answeredCount = (int)$aCountStmt->fetchColumn();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SkillBridge | Industry Skill Assessment Workspace</title>
<link rel="stylesheet" href="../Industry%20dashboard/style.css">
<style>
body { background: #f4f7fc; }
.assessment-container { max-width: 820px; margin: 40px auto; padding: 0 20px; }
.quiz-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 36px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); }
.quiz-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #edf2f7; }
.difficulty-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 999px; font-weight: 700; font-size: 12px; }
.difficulty-badge.Easy { background: #e6fffa; color: #047857; }
.difficulty-badge.Medium { background: #fef3c7; color: #b45309; }
.difficulty-badge.Hard { background: #fee2e2; color: #b91c1c; }
.question-title { font-size: 20px; font-weight: 700; color: #1e293b; margin: 16px 0 24px; line-height: 1.4; }
.options-list { display: flex; flex-direction: column; gap: 12px; margin-bottom: 28px; }
.option-label { display: flex; align-items: center; padding: 14px 18px; border: 2px solid #e2e8f0; border-radius: 12px; cursor: pointer; transition: all 0.2s ease; font-size: 15px; font-weight: 500; color: #334155; }
.option-label:hover { border-color: #3b82f6; background: #eff6ff; }
.option-label.selected { border-color: #2563eb; background: #eff6ff; color: #1d4ed8; font-weight: 600; }
.option-label input { margin-right: 14px; accent-color: #2563eb; transform: scale(1.2); }
.explanation-box { background: #f8fafc; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 8px; margin-bottom: 24px; font-size: 14px; color: #475569; display: none; }
.result-box { text-align: center; padding: 20px 10px; }
.score-circle { width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 10px 25px rgba(37,99,235,0.3); }
.score-circle strong { font-size: 36px; font-weight: 800; line-height: 1; }
.score-circle small { font-size: 11px; text-transform: uppercase; opacity: 0.9; margin-top: 4px; }
.intro-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 24px 0; text-align: left; }
.intro-item { background: #f8fafc; border: 1px solid #e2e8f0; padding: 14px 18px; border-radius: 12px; }
.intro-item small { color: #64748b; font-size: 11px; font-weight: 700; text-transform: uppercase; display: block; }
.intro-item strong { color: #0f172a; font-size: 14px; margin-top: 2px; display: block; }
.rec-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 24px; text-align: left; }
.rec-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; }
</style>
</head>
<body>

<div class="assessment-container">
  <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <a href="index.php" style="color: #2563eb; font-weight: 600; text-decoration: none;">← Back to Student Dashboard</a>
    <a href="practice_assessment.php" class="btn secondary" style="text-decoration:none; padding: 6px 14px; font-size: 13px;">Open Practice Center</a>
  </div>

  <?php if (!$selectedIndustryAssessment && count($industryAssessments) > 1): ?>
    <div class="quiz-card" style="margin-bottom:20px;">
      <div class="eyebrow">AVAILABLE PRE-PLACEMENT TESTS</div>
      <h3 style="margin:6px 0 14px;">Select an industry assessment</h3>
      <div style="display:grid; gap:12px;">
        <?php foreach ($industryAssessments as $ia): ?>
          <a href="assessment.php?assessment_id=<?= (int)$ia['id'] ?>" style="display:block; padding:16px; border:1px solid #e2e8f0; border-radius:12px; text-decoration:none; color:#0f172a;">
            <strong><?= e($ia['title']) ?></strong>
            <div style="font-size:12px; color:#64748b; margin-top:5px;">
              <?= e($ia['opportunity_title'] ?: 'General Industry Assessment') ?> •
              <?= (int)$ia['question_count'] ?> questions • <?= (int)$ia['duration_mins'] ?> min • Pass <?= (float)$ia['passing_score_pct'] ?>%
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="quiz-card" id="quizShell">
    <div class="quiz-header">
      <div>
        <span class="eyebrow">OFFICIAL INDUSTRY ASSESSMENT</span>
        <h3 id="roleHeader" style="margin: 4px 0 0; font-size: 18px;"><?= e($selectedIndustryAssessment['title'] ?? 'Software Engineering Competency Test') ?></h3>
      </div>
      <div>
        <span class="difficulty-badge Medium" id="diffBadge">⚡ Adaptive Engine</span>
      </div>
    </div>

    <div id="quizBody">
      <!-- INTRODUCTION SCREEN -->
      <div style="text-align: center; padding: 10px 0;">
        <h2 style="font-size: 24px; color: #0f172a; margin-bottom: 8px;"><?= $selectedIndustryAssessment ? 'Pre-Placement Assessment' : 'Industry Competency Assessment' ?></h2>
        <p style="color: #64748b; max-width: 580px; margin: 0 auto;"><?= e($selectedIndustryAssessment['description'] ?? 'Evaluates your technical mastery to update your official verified Skill Profile & Gap analysis for industry matching.') ?></p>

        <div class="intro-grid">
          <div class="intro-item"><small>Assessment Source</small><strong><?= $selectedIndustryAssessment ? 'Industry-defined questions' : 'SkillBridge question bank' ?></strong></div>
          <div class="intro-item"><small>Opportunity</small><strong><?= e($selectedIndustryAssessment['opportunity_title'] ?? 'General Skill Assessment') ?></strong></div>
          <div class="intro-item"><small>Question Format</small><strong><?= (int)($selectedIndustryAssessment['question_count'] ?? 5) ?> available • Adaptive difficulty</strong></div>
          <div class="intro-item"><small>Duration / Pass Mark</small><strong><?= (int)($selectedIndustryAssessment['duration_mins'] ?? 10) ?> min • <?= (float)($selectedIndustryAssessment['passing_score_pct'] ?? 70) ?>%</strong></div>
        </div>

        <div style="background: #eff6ff; border-left: 4px solid #2563eb; padding: 16px; border-radius: 8px; text-align: left; margin-bottom: 28px;">
          <strong style="color: #1d4ed8; font-size: 14px;">Assessment Instructions & Rules:</strong>
          <ul style="margin: 8px 0 0 18px; color: #334155; font-size: 13px; line-height: 1.6;">
            <li>Difficulty automatically adapts after every answer (correct answers increase difficulty; incorrect answers decrease it).</li>
            <li>Each answer is evaluated immediately upon submission with detailed explanation.</li>
            <li>Closing the test mid-way saves your attempt. You can resume at any time.</li>
            <li><strong>Assessment source:</strong> <?= $selectedIndustryAssessment ? 'Every question is taken from the questions configured by the industry in this Pre-Placement Assessment.' : 'Questions are taken from the SkillBridge assessment bank.' ?></li>
          </ul>
        </div>

        <div style="display: flex; gap: 12px; justify-content: center;">
          <?php if (!$selectedIndustryAssessment && count($industryAssessments) > 1): ?>
            <span class="btn secondary" style="padding:12px 24px;">Select an assessment above to begin</span>
          <?php elseif($activeAttempt && $answeredCount < 5 && !$selectedIndustryAssessment): ?>
            <button class="btn primary" id="startBtn" style="padding: 12px 32px; font-size: 16px;">⚡ Continue Skill Assessment (Question <?= $answeredCount + 1 ?> of 5)</button>
          <?php else: ?>
            <button class="btn primary" id="startBtn" style="padding: 12px 32px; font-size: 16px;">⚡ <?= $selectedIndustryAssessment ? 'Start Pre-Placement Assessment' : 'Start Industry Assessment' ?></button>
          <?php endif; ?>
          <a href="practice_assessment.php" class="btn secondary" style="padding: 12px 24px; text-decoration: none; font-size: 15px;">Try Practice Quiz First</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let currentAttemptId = null;
let currentQuestion = null;
let qNum = 1;

const quizBody = document.getElementById('quizBody');
const diffBadge = document.getElementById('diffBadge');

document.getElementById('startBtn')?.addEventListener('click', startQuiz);

async function startQuiz() {
  quizBody.innerHTML = '<div style="text-align:center; padding: 40px;"><p>Loading adaptive assessment question...</p></div>';
  const formData = new FormData();
  formData.append('action', 'start');
  formData.append('target_role', 'Software Engineer');
  formData.append('csrf_token', '<?= e(csrfToken()) ?>');
  formData.append('assessment_id', '<?= (int)($selectedIndustryAssessment['id'] ?? 0) ?>');

  const res = await fetch('api/assessment_api.php', { method: 'POST', body: formData });
  const data = await res.json().catch(() => ({ok:false, message:'Server returned an invalid response.'}));
  if (data.ok) {
    currentAttemptId = data.attempt_id;
    qNum = data.question_number;
    renderQuestion(data.question, data.question_number, data.total_questions);
  } else {
    quizBody.innerHTML = `<div class="flash error">${data.message || 'Failed to initialize assessment.'}</div>`;
  }
}

function renderQuestion(q, num, total) {
  currentQuestion = q;
  diffBadge.textContent = `⚡ ${q.difficulty} Level`;
  diffBadge.className = `difficulty-badge ${q.difficulty}`;

  quizBody.innerHTML = `
    <div style="font-size: 12px; font-weight: 700; color: #2563eb; letter-spacing: 0.05em; text-transform: uppercase;">
      Question ${num} of ${total} • Topic: ${q.topic}
    </div>
    <div class="question-title">${q.question}</div>
    <div class="options-list">
      <label class="option-label"><input type="radio" name="opt" value="A"> A. ${q.option_a}</label>
      <label class="option-label"><input type="radio" name="opt" value="B"> B. ${q.option_b}</label>
      <label class="option-label"><input type="radio" name="opt" value="C"> C. ${q.option_c}</label>
      <label class="option-label"><input type="radio" name="opt" value="D"> D. ${q.option_d}</label>
    </div>
    <div class="explanation-box" id="explainBox"></div>
    <button class="btn primary" id="submitAnsBtn" style="width: 100%; height: 48px; font-size: 16px;" disabled>Submit Answer</button>
  `;

  document.querySelectorAll('.option-label').forEach(lbl => {
    lbl.addEventListener('click', () => {
      document.querySelectorAll('.option-label').forEach(x => x.classList.remove('selected'));
      lbl.classList.add('selected');
      const radio = lbl.querySelector('input');
      if (radio) radio.checked = true;
      document.getElementById('submitAnsBtn').disabled = false;
    });
  });

  document.getElementById('submitAnsBtn').addEventListener('click', submitAnswer);
}

async function submitAnswer() {
  const selected = document.querySelector('input[name="opt"]:checked')?.value;
  if (!selected) return;

  const btn = document.getElementById('submitAnsBtn');
  btn.disabled = true;
  btn.textContent = 'Evaluating...';

  const formData = new FormData();
  formData.append('action', 'answer');
  formData.append('attempt_id', currentAttemptId);
  formData.append('question_id', currentQuestion.id);
  formData.append('selected_option', selected);
  formData.append('question_number', qNum);
  formData.append('csrf_token', '<?= e(csrfToken()) ?>');
  formData.append('assessment_id', '<?= (int)($selectedIndustryAssessment['id'] ?? 0) ?>');

  const res = await fetch('api/assessment_api.php', { method: 'POST', body: formData });
  const data = await res.json().catch(() => ({ok:false, message:'Server returned an invalid response.'}));

  if (data.ok) {
    const expBox = document.getElementById('explainBox');
    expBox.style.display = 'block';
    expBox.innerHTML = `<strong>${data.is_correct ? '✓ Correct!' : '✗ Incorrect'}</strong><br>${data.explanation || ''}`;
    expBox.style.borderColor = data.is_correct ? '#10b981' : '#ef4444';

    setTimeout(() => {
      if (data.completed) {
        renderResults(data.final_score, data.strengths, data.gaps);
      } else {
        qNum = data.question_number;
        renderQuestion(data.question, data.question_number, data.total_questions);
      }
    }, 2000);
  }
}

function renderResults(score, strengths = [], gaps = []) {
  diffBadge.style.display = 'none';

  let strengthsHtml = (strengths || []).map(s => `<span class="strength-tag">${s.name} (${s.score}%)</span>`).join(' ') || '<small style="color:#64748b;">No verified strengths yet.</small>';
  let gapsHtml = (gaps || []).map(g => `<span class="gap-tag">${g.name} (${g.score}%)</span>`).join(' ') || '<small style="color:#047857;">No major gaps!</small>';

  quizBody.innerHTML = `
    <div class="result-box">
      <div class="score-circle">
        <strong>${Math.round(score)}%</strong>
        <small>Skill Score</small>
      </div>
      <span class="status active" style="font-weight:700;">✓ Official Verified Assessment Complete</span>
      <h2 style="margin-top:12px; color:#0f172a;">Skill Evaluation Summary</h2>
      <p style="color: #64748b; max-width: 520px; margin: 4px auto 20px;">Your responses have been processed. Your official Skill Profile and Gap recommendations have been updated across SkillBridge.</p>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; text-align:left; background:#f8fafc; padding:18px; border-radius:12px; margin-bottom:24px;">
        <div>
          <strong style="color:#047857; font-size:13px;">✓ Identified Strengths (≥70%):</strong>
          <div style="margin-top:8px; display:flex; flex-wrap:wrap; gap:6px;">${strengthsHtml}</div>
        </div>
        <div>
          <strong style="color:#be123c; font-size:13px;">⚠ Skill Gaps (&lt;70%):</strong>
          <div style="margin-top:8px; display:flex; flex-wrap:wrap; gap:6px;">${gapsHtml}</div>
        </div>
      </div>

      <div class="rec-grid">
        <div class="rec-card">
          <h4 style="margin:0 0 6px; font-size:14px; color:#0f172a;">⚡ Practice Skill Quizzes</h4>
          <p style="margin:0 0 12px; font-size:12px; color:#64748b;">Take untimed practice tests to improve specific gaps.</p>
          <a href="practice_assessment.php" class="btn secondary" style="padding:6px 14px; font-size:12px; text-decoration:none;">Open Practice Hub</a>
        </div>
        <div class="rec-card">
          <h4 style="margin:0 0 6px; font-size:14px; color:#0f172a;">🎓 Join Learning Programs</h4>
          <p style="margin:0 0 12px; font-size:12px; color:#64748b;">Enroll in industry courses designed to bridge skill gaps.</p>
          <a href="learning.php" class="btn primary" style="padding:6px 14px; font-size:12px; text-decoration:none;">View Learning Programs</a>
        </div>
      </div>

      <div style="margin-top:28px; display:flex; gap:12px; justify-content:center;">
        <a href="skill_profile.php" class="btn secondary" style="padding:10px 20px; text-decoration:none;">View Full Skill Profile</a>
        <a href="index.php" class="btn primary" style="padding:10px 24px; text-decoration:none;">Return to Dashboard</a>
      </div>
    </div>
  `;
}
</script>
</body>
</html>
