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

$presetTopic = trim((string)($_GET['topic'] ?? ''));

// Fetch available topics dynamically from assessment_questions
$topics = $pdo->query("SELECT DISTINCT topic FROM assessment_questions ORDER BY topic")->fetchAll(PDO::FETCH_COLUMN);
if (!$topics) {
    $topics = ['Python', 'SQL', 'JavaScript', 'Git', 'Data Structures', 'Java'];
}

// Fetch practice attempt history
$histStmt = $pdo->prepare("SELECT * FROM practice_attempts WHERE candidate_id = ? ORDER BY started_at DESC LIMIT 20");
$histStmt->execute([$candidateId]);
$history = $histStmt->fetchAll();

// Handle AJAX requests for practice questions & submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['ajax_action'];
    
    if ($action === 'fetch_practice_questions') {
        $topic = trim((string)($_POST['topic'] ?? 'Python'));
        $count = max(3, min(20, (int)($_POST['question_count'] ?? 5)));
        $diff = trim((string)($_POST['difficulty'] ?? 'Mixed'));
        
        $sql = "SELECT id, topic, question, option_a, option_b, option_c, option_d, correct_option, difficulty, explanation FROM assessment_questions WHERE 1=1";
        $params = [];
        if ($topic !== 'All Topics') {
            $sql .= " AND topic = ?";
            $params[] = $topic;
        }
        if (in_array($diff, ['Easy','Medium','Hard'], true)) {
            $sql .= " AND difficulty = ?";
            $params[] = $diff;
        }
        $sql .= " ORDER BY RAND() LIMIT " . (int)$count;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $questions = $stmt->fetchAll();

        echo json_encode(['ok' => true, 'questions' => $questions]);
        exit;
    }

    if ($action === 'submit_practice_result') {
        $topic = trim((string)($_POST['topic'] ?? 'Practice'));
        $total = (int)($_POST['total'] ?? 0);
        $correct = (int)($_POST['correct'] ?? 0);
        $pct = $total > 0 ? round(($correct / $total) * 100, 2) : 0.00;

        $stmt = $pdo->prepare("INSERT INTO practice_attempts (candidate_id, topic, total_questions, correct_answers, score_pct, status, completed_at) VALUES (?, ?, ?, ?, ?, 'Completed', CURRENT_TIMESTAMP)");
        $stmt->execute([$candidateId, $topic, $total, $correct, $pct]);

        echo json_encode(['ok' => true, 'score_pct' => $pct]);
        exit;
    }

    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SkillBridge | Practice Assessment Hub</title>
<link rel="stylesheet" href="../Industry%20dashboard/style.css">
<style>
body { background: #f8fafc; }
.practice-shell { max-width: 960px; margin: 40px auto; padding: 0 20px; }
.card-hero { background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #fff; border-radius: 20px; padding: 36px; margin-bottom: 28px; box-shadow: 0 10px 30px rgba(37,99,235,0.15); }
.card-hero h1 { margin: 0 0 8px; font-size: 28px; font-weight: 800; color: #fff; }
.topic-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px; }
.topic-card { background: #fff; border: 2px solid #e2e8f0; border-radius: 16px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.2s ease; }
.topic-card:hover { border-color: #2563eb; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.04); }
.topic-card.selected { border-color: #2563eb; background: #eff6ff; }
.topic-card strong { display: block; font-size: 17px; color: #0f172a; margin-top: 6px; }
.quiz-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 36px; display: none; box-shadow: 0 10px 30px rgba(0,0,0,0.04); }
.option-box { display: flex; align-items: center; padding: 14px 18px; border: 2px solid #e2e8f0; border-radius: 12px; margin-bottom: 12px; cursor: pointer; font-size: 15px; }
.option-box.selected { border-color: #2563eb; background: #eff6ff; font-weight: 600; color: #1d4ed8; }
.option-box.correct { border-color: #10b981; background: #ecfdf5; color: #047857; font-weight: 600; }
.option-box.incorrect { border-color: #ef4444; background: #fef2f2; color: #b91c1c; }
.timer-badge { background: #fef3c7; color: #b45309; padding: 6px 14px; border-radius: 999px; font-weight: 700; font-size: 13px; }
.tab-nav { display: flex; gap: 12px; border-bottom: 2px solid #e2e8f0; margin-bottom: 24px; }
.tab-btn { padding: 10px 18px; border: none; background: none; font-weight: 600; color: #64748b; cursor: pointer; font-size: 14px; border-bottom: 2px solid transparent; margin-bottom: -2px; }
.tab-btn.active { color: #2563eb; border-bottom-color: #2563eb; }
</style>
</head>
<body>

<div class="practice-shell">
  <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <a href="index.php" style="color: #2563eb; font-weight: 600; text-decoration: none;">← Back to Student Dashboard</a>
    <span class="status draft" style="background:#e0e7ff; color:#3730a3; font-weight:700;">PRACTICE MODE (Does not alter official profile)</span>
  </div>

  <div class="card-hero">
    <h1>Practice Assessment Hub</h1>
    <p style="opacity: 0.9; margin: 0; max-width: 600px;">Sharpen your skills with unlimited practice quizzes across core technical topics. Get detailed explanations and track your improvement over time.</p>
  </div>

  <div class="tab-nav">
    <button class="tab-btn active" onclick="switchTab('start')">Start Practice</button>
    <button class="tab-btn" onclick="switchTab('history')">Practice History (<?= count($history) ?>)</button>
  </div>

  <!-- START PRACTICE TAB -->
  <div id="tabStart">
    <h3 style="font-size: 16px; margin-bottom: 12px; color: #334155;">1. Select Topic</h3>
    <div class="topic-grid">
      <div class="topic-card <?= ($presetTopic === '' || $presetTopic === 'All Topics') ? 'selected' : '' ?>" data-topic="All Topics">
        <span style="font-size: 24px;">🚀</span>
        <strong>All Topics</strong>
      </div>
      <?php foreach($topics as $tp): 
        $isSelected = (mb_strtolower($presetTopic) === mb_strtolower($tp));
      ?>
        <div class="topic-card <?= $isSelected ? 'selected' : '' ?>" data-topic="<?= e($tp) ?>">
          <span style="font-size: 24px;">💻</span>
          <strong><?= e($tp) ?></strong>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="card" style="margin-bottom: 28px; padding: 24px;">
      <h3 style="font-size: 16px; margin-bottom: 16px; color: #334155;">2. Configure Quiz</h3>
      <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
        <label>Questions
          <select class="input" id="qCountSelect">
            <option value="5" selected>5 Questions</option>
            <option value="10">10 Questions</option>
          </select>
        </label>
        <label>Difficulty
          <select class="input" id="diffSelect">
            <option value="Mixed" selected>Mixed Difficulty</option>
            <option value="Easy">Easy</option>
            <option value="Medium">Medium</option>
            <option value="Hard">Hard</option>
          </select>
        </label>
        <label>Timer
          <select class="input" id="timerSelect">
            <option value="0" selected>Off (Untimed)</option>
            <option value="5">5 Minutes</option>
            <option value="10">10 Minutes</option>
          </select>
        </label>
      </div>
      <button class="btn primary" id="startPracticeBtn" style="margin-top: 20px; width: 100%; height: 48px; font-size: 16px;">Start Practice Quiz</button>
    </div>
  </div>

  <!-- PRACTICE HISTORY TAB -->
  <div id="tabHistory" style="display: none;">
    <div class="card table-card">
      <div class="table-scroll">
        <table>
          <thead><tr><th>Topic</th><th>Questions</th><th>Score</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach($history as $h): ?>
              <tr>
                <td><strong><?= e($h['topic']) ?></strong></td>
                <td><?= (int)$h['correct_answers'] ?> / <?= (int)$h['total_questions'] ?></td>
                <td><span class="match-badge <?= (float)$h['score_pct']>=75?'high':((float)$h['score_pct']>=50?'mid':'low') ?>"><?= (float)$h['score_pct'] ?>%</span></td>
                <td><?= e(date('d M Y, H:i', strtotime((string)$h['started_at']))) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if(!$history): ?><tr><td colspan="4" style="text-align:center; padding: 24px; color: #64748b;">No practice attempts recorded yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- QUIZ INTERFACE PANEL -->
  <div class="quiz-panel" id="quizPanel">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #edf2f7; padding-bottom: 16px;">
      <span class="eyebrow" id="quizMetaHeader">Question 1 of 5</span>
      <span class="timer-badge" id="timerBadge" style="display:none;">⏱ <span id="timerText">05:00</span></span>
    </div>

    <div id="quizQuestionBody"></div>
  </div>
</div>

<script>
let selectedTopic = '<?= e($presetTopic ?: "All Topics") ?>';
let questionsList = [];
let currentIdx = 0;
let userAnswers = {};
let timerInterval = null;

document.querySelectorAll('.topic-card').forEach(c => {
  c.addEventListener('click', () => {
    document.querySelectorAll('.topic-card').forEach(x => x.classList.remove('selected'));
    c.classList.add('selected');
    selectedTopic = c.dataset.topic;
  });
});

function switchTab(tab) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  if (tab === 'start') {
    document.getElementById('tabStart').style.display = 'block';
    document.getElementById('tabHistory').style.display = 'none';
    document.querySelector('.tab-btn:nth-child(1)').classList.add('active');
  } else {
    document.getElementById('tabStart').style.display = 'none';
    document.getElementById('tabHistory').style.display = 'block';
    document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
  }
}

document.getElementById('startPracticeBtn')?.addEventListener('click', async () => {
  const count = document.getElementById('qCountSelect').value;
  const diff = document.getElementById('diffSelect').value;

  const formData = new FormData();
  formData.append('ajax_action', 'fetch_practice_questions');
  formData.append('topic', selectedTopic);
  formData.append('question_count', count);
  formData.append('difficulty', diff);

  const res = await fetch('practice_assessment.php', { method: 'POST', body: formData });
  const data = await res.json();

  if (data.ok && data.questions && data.questions.length > 0) {
    questionsList = data.questions;
    currentIdx = 0;
    userAnswers = {};
    document.getElementById('tabStart').style.display = 'none';
    document.getElementById('quizPanel').style.display = 'block';

    const timeMins = parseInt(document.getElementById('timerSelect').value);
    if (timeMins > 0) {
      startTimer(timeMins * 60);
    } else {
      document.getElementById('timerBadge').style.display = 'none';
    }

    renderPracticeQuestion();
  } else {
    alert('No practice questions found for the selected topic.');
  }
});

function renderPracticeQuestion() {
  const q = questionsList[currentIdx];
  const total = questionsList.length;

  document.getElementById('quizMetaHeader').textContent = `Question ${currentIdx + 1} of ${total} • ${q.topic} (${q.difficulty})`;

  const qBody = document.getElementById('quizQuestionBody');
  qBody.innerHTML = `
    <h3 style="font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 20px;">${q.question}</h3>
    <div class="options-list">
      <div class="option-box ${userAnswers[currentIdx]==='A'?'selected':''}" onclick="selectOpt('A')">A. ${q.option_a}</div>
      <div class="option-box ${userAnswers[currentIdx]==='B'?'selected':''}" onclick="selectOpt('B')">B. ${q.option_b}</div>
      <div class="option-box ${userAnswers[currentIdx]==='C'?'selected':''}" onclick="selectOpt('C')">C. ${q.option_c}</div>
      <div class="option-box ${userAnswers[currentIdx]==='D'?'selected':''}" onclick="selectOpt('D')">D. ${q.option_d}</div>
    </div>
    <div style="display: flex; justify-content: space-between; margin-top: 24px;">
      <button class="btn secondary" onclick="navQ(-1)" ${currentIdx===0?'disabled':''}>Previous</button>
      ${currentIdx === total - 1 ? '<button class="btn primary" onclick="finishPractice()">Finish Quiz</button>' : '<button class="btn primary" onclick="navQ(1)">Next Question</button>'}
    </div>
  `;
}

function selectOpt(opt) {
  userAnswers[currentIdx] = opt;
  renderPracticeQuestion();
}

function navQ(dir) {
  currentIdx = Math.max(0, Math.min(questionsList.length - 1, currentIdx + dir));
  renderPracticeQuestion();
}

function startTimer(seconds) {
  clearInterval(timerInterval);
  const badge = document.getElementById('timerBadge');
  const text = document.getElementById('timerText');
  badge.style.display = 'inline-block';

  let remaining = seconds;
  timerInterval = setInterval(() => {
    remaining--;
    const m = String(Math.floor(remaining / 60)).padStart(2, '0');
    const s = String(remaining % 60).padStart(2, '0');
    text.textContent = `${m}:${s}`;

    if (remaining <= 0) {
      clearInterval(timerInterval);
      alert('Time is up! Submitting your practice quiz.');
      finishPractice();
    }
  }, 1000);
}

async function finishPractice() {
  clearInterval(timerInterval);
  let correct = 0;
  questionsList.forEach((q, i) => {
    if (userAnswers[i] === q.correct_option) correct++;
  });

  const total = questionsList.length;

  const formData = new FormData();
  formData.append('ajax_action', 'submit_practice_result');
  formData.append('topic', selectedTopic);
  formData.append('total', total);
  formData.append('correct', correct);

  await fetch('practice_assessment.php', { method: 'POST', body: formData });

  const qBody = document.getElementById('quizQuestionBody');
  const pct = Math.round((correct / total) * 100);

  let breakdownHtml = questionsList.map((q, i) => {
    const userAns = userAnswers[i] || 'None';
    const isRight = userAns === q.correct_option;
    return `
      <div style="background: #f8fafc; border-left: 4px solid ${isRight?'#10b981':'#ef4444'}; padding: 16px; margin-bottom: 14px; border-radius: 8px;">
        <strong>Q${i+1}: ${q.question}</strong>
        <p style="margin: 6px 0; font-size: 13px;">Your Answer: <b>${userAns}</b> | Correct: <b>${q.correct_option}</b></p>
        <p style="margin: 0; font-size: 13px; color: #475569;"><i>${q.explanation || 'No explanation.'}</i></p>
      </div>
    `;
  }).join('');

  qBody.innerHTML = `
    <div style="text-align: center; margin-bottom: 32px;">
      <h2 style="font-size: 28px; margin-bottom: 4px;">Practice Score: ${pct}%</h2>
      <p style="color: #64748b;">You answered ${correct} out of ${total} questions correctly.</p>
    </div>
    <h4 style="margin-bottom: 16px;">Question Breakdown & Explanations</h4>
    ${breakdownHtml}
    <button class="btn primary" onclick="location.reload()" style="margin-top: 20px; width: 100%;">Return to Practice Center</button>
  `;
}
</script>
</body>
</html>
