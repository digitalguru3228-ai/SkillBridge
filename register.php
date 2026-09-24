<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_role'])) redirectByRole((string)$_SESSION['user_role']);

$pdo = db();
$error = '';
$old = ['name'=>'','email'=>'','role'=>'student','organization_id'=>''];

function orgOptions(PDO $pdo, string $role): array {
    if ($role === 'industry') {
        return $pdo->query('SELECT id, name FROM companies ORDER BY name')->fetchAll();
    }
    return $pdo->query('SELECT id, name FROM institutes ORDER BY name')->fetchAll();
}

$postedRole = isset($_POST['role']) ? (string)$_POST['role'] : 'student';
$role = in_array($postedRole, ['student','institute','industry'], true) ? $postedRole : 'student';
$organizations = orgOptions($pdo, $role);
// When a workspace has only one organization (common for a fresh/demo deployment),
// bind the account to it automatically so Institute/Industry registration cannot
// fail merely because the user did not manually select the only available option.
if ($role !== 'student' && count($organizations) === 1 && empty($old['organization_id'])) {
    $old['organization_id'] = (string)$organizations[0]['id'];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $old = [
        'name'=>trim((string)($_POST['name'] ?? '')),
        'email'=>strtolower(trim((string)($_POST['email'] ?? ''))),
        'role'=>$role,
        'organization_id'=>(string)($_POST['organization_id'] ?? '')
    ];
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session token expired. Please refresh and try again.';
    } elseif ($old['name'] === '' || mb_strlen($old['name']) < 2) {
        $error = 'Please enter your full name.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must contain at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $existing = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
            $existing->execute([$old['email']]);
            if ($existing->fetchColumn()) {
                $error = 'An account with this email already exists. Please sign in instead.';
            } else {
                $orgId = (int)$old['organization_id'];
                if ($role !== 'student' && $orgId <= 0) {
                    $error = 'Please select your organization.';
                } else {
                    $pdo->beginTransaction();

                    $companyId = null;
                    $instituteId = null;
                    if ($role === 'industry') {
                        $check = $pdo->prepare('SELECT id FROM companies WHERE id=? LIMIT 1');
                        $check->execute([$orgId]);
                        if (!$check->fetchColumn()) throw new RuntimeException('Selected company was not found.');
                        $companyId = $orgId;
                    } elseif ($role === 'institute') {
                        $check = $pdo->prepare('SELECT id FROM institutes WHERE id=? LIMIT 1');
                        $check->execute([$orgId]);
                        if (!$check->fetchColumn()) throw new RuntimeException('Selected institute was not found.');
                        $instituteId = $orgId;
                    } else {
                        $instituteId = $orgId > 0 ? $orgId : null;
                        if ($instituteId !== null) {
                            $check = $pdo->prepare('SELECT id FROM institutes WHERE id=? LIMIT 1');
                            $check->execute([$instituteId]);
                            if (!$check->fetchColumn()) throw new RuntimeException('Selected institute was not found.');
                        }
                    }

                    $stmt = $pdo->prepare('INSERT INTO users (name,email,password_hash,role,company_id,institute_id,status,auth_provider,email_verified_at) VALUES (?,?,?,?,?,?,\'Active\',\'password\',NULL)');
                    $stmt->execute([$old['name'],$old['email'],password_hash($password,PASSWORD_DEFAULT),$role,$companyId,$instituteId]);
                    $userId = (int)$pdo->lastInsertId();

                    if ($role === 'student') {
                        $candidateId = 0;
                        $candidateSql = 'INSERT INTO candidates (name,email,qualification,experience_years,match_score,skills,institute_id) VALUES (?,?,?,?,?,?,?)';
                        $candidateStmt = $pdo->prepare($candidateSql);
                        $candidateStmt->execute([$old['name'],$old['email'],'',0,0,'[]',$instituteId]);
                        $candidateId = (int)$pdo->lastInsertId();

                        $profileStmt = $pdo->prepare('INSERT INTO student_profiles (candidate_id,user_id,degree,branch,semester,cgpa) VALUES (?,?,?,?,?,?)');
                        $profileStmt->execute([$candidateId,$userId,'','',0,0]);
                    }

                    $pdo->commit();

                    $q = $pdo->prepare('SELECT id,name,email,role,company_id,institute_id,status FROM users WHERE id=? LIMIT 1');
                    $q->execute([$userId]);
                    $u = $q->fetch();
                    signInUser($u, $pdo);
                    redirectByRole($role);
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e instanceof PDOException && (int)$e->errorInfo[1] === 1062
                ? 'This email is already registered.'
                : 'Account creation failed: ' . $e->getMessage();
        }
    }
    $organizations = orgOptions($pdo, $role);
    if ($role !== 'student' && count($organizations) === 1 && empty($old['organization_id'])) {
        $old['organization_id'] = (string)$organizations[0]['id'];
    }
}
$csrf = csrfToken();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SKILLBRIDGE | Create Account</title>
<style>
@import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap");
:root{--blue:#167be8;--navy:#123d78;--bg:#f5f8fc;--line:#e2e8f0;--muted:#64748b;--text:#102a4c}
*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,system-ui,sans-serif;background:radial-gradient(circle at top right,#eaf5ff,transparent 35%),var(--bg);color:var(--text);padding:28px;display:grid;place-items:center}.card{width:min(620px,100%);background:#fff;border:1px solid var(--line);border-radius:24px;padding:38px;box-shadow:0 24px 70px rgba(25,55,95,.11)}.brand{display:flex;align-items:center;gap:12px;margin-bottom:24px}.brand img{width:48px;height:48px;object-fit:contain}.eyebrow{font-size:10px;letter-spacing:.13em;color:var(--blue);font-weight:800}.brand strong{font-size:18px;color:var(--navy)}h1{font-size:28px;margin:8px 0}.sub{color:var(--muted);font-size:13px;line-height:1.6;margin-bottom:24px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.field{margin-bottom:16px}.field.full{grid-column:1/-1}.field label{display:block;font-size:12px;font-weight:700;margin-bottom:7px}.field input,.field select{width:100%;height:46px;border:1px solid #dbe4ef;border-radius:11px;padding:0 12px;background:#fff;outline:none}.field input:focus,.field select:focus{border-color:#79b4f2;box-shadow:0 0 0 3px #eaf4ff}.roles{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}.role-option{border:1px solid #dbe4ef;border-radius:11px;padding:12px;text-align:center;cursor:pointer;font-size:12px;font-weight:700}.role-option input{display:none}.role-option:has(input:checked){border-color:#167be8;background:#eff7ff;color:#167be8}.divider{display:flex;align-items:center;gap:12px;color:#94a3b8;font-size:11px;margin:18px 0}.divider:before,.divider:after{content:"";height:1px;background:#e2e8f0;flex:1}.google{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;height:46px;border:1px solid #d7e0eb;border-radius:11px;background:#fff;color:#243b53;font-weight:700;text-decoration:none}.btn{width:100%;height:48px;border:0;border-radius:11px;background:var(--blue);color:#fff;font-weight:700;cursor:pointer}.error{background:#fff1f2;color:#b83d49;border:1px solid #ffd7da;border-radius:10px;padding:11px 12px;font-size:12px;margin-bottom:18px}.login{text-align:center;color:var(--muted);font-size:12px;margin-top:18px}.login a{color:var(--blue);font-weight:700;text-decoration:none}.hint{font-size:11px;color:#94a3b8;margin-top:6px;line-height:1.5}@media(max-width:600px){body{padding:14px}.card{padding:24px}.grid{grid-template-columns:1fr}.field.full{grid-column:auto}.roles{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="card">
<div class="brand"><img src="assets/skillbridge-logo.jpeg" alt=""><div><div class="eyebrow">SKILLBRIDGE</div><strong>Academia • Industry • Talent</strong></div></div>
<h1>Create your account</h1>
<p class="sub">Create one secure account and we'll open the correct portal for your role.</p>
<?php if($error): ?><div class="error"><?=e($error)?></div><?php endif; ?>
<form method="post" autocomplete="on">
<input type="hidden" name="csrf_token" value="<?=e($csrf)?>">
<div class="field full"><label>I am registering as</label><div class="roles">
<label class="role-option"><input type="radio" name="role" value="student" <?= $role==='student'?'checked':'' ?>>Student</label>
<label class="role-option"><input type="radio" name="role" value="institute" <?= $role==='institute'?'checked':'' ?>>Institute</label>
<label class="role-option"><input type="radio" name="role" value="industry" <?= $role==='industry'?'checked':'' ?>>Industry</label>
</div></div>
<div class="grid">
<div class="field"><label for="name">Full name</label><input id="name" name="name" value="<?=e($old['name'])?>" required></div>
<div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" value="<?=e($old['email'])?>" required></div>
<div class="field full"><label for="organization_id"><span id="orgLabel"><?= $role==='industry'?'Company':'Institute' ?></span> <?= $role==='student'?'(optional for now)':'' ?></label>
<select id="organization_id" name="organization_id" <?= $role!=='student' ? 'required' : '' ?>><option value="">Select <?= $role==='industry'?'company':'institute' ?></option>
<?php foreach($organizations as $org): ?><option value="<?= (int)$org['id'] ?>" <?= (string)$old['organization_id']===(string)$org['id']?'selected':'' ?>><?=e($org['name'])?></option><?php endforeach; ?>
</select><div class="hint" id="orgHint"><?= $role==='industry' ? 'This account will manage the selected company workspace.' : ($role==='institute' ? 'This account will manage the selected institute workspace.' : 'Select the institute you currently attend. You can complete academic details from your profile.') ?></div></div>
<div class="field"><label for="password">Password</label><input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required></div>
<div class="field"><label for="confirm_password">Confirm password</label><input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required></div>
</div>
<button class="btn" type="submit">Create Account</button>
</form>
<div class="divider">OR</div>
<a id="googleRegister" class="google" href="google_start.php?mode=register&role=student&organization_id=0">G&nbsp;&nbsp;Sign up with Google</a>
<div class="login">Already have an account? <a href="index.php">Sign in</a></div>
</div>
<script>
const roleInputs=document.querySelectorAll('input[name="role"]');
const orgSelect=document.getElementById('organization_id');
const orgLabel=document.getElementById('orgLabel');
const data={student:<?=json_encode($pdo->query('SELECT id,name FROM institutes ORDER BY name')->fetchAll())?>,institute:<?=json_encode($pdo->query('SELECT id,name FROM institutes ORDER BY name')->fetchAll())?>,industry:<?=json_encode($pdo->query('SELECT id,name FROM companies ORDER BY name')->fetchAll())?>};
const googleRegister=document.getElementById('googleRegister');
const orgHint=document.getElementById('orgHint');
function updateOrg(){
 const role=document.querySelector('input[name="role"]:checked')?.value||'student';
 const list=data[role]||[];
 const previous=orgSelect.value;
 orgLabel.textContent=role==='industry'?'Company':'Institute';
 orgSelect.innerHTML='<option value="">Select '+(role==='industry'?'company':'institute')+'</option>';
 list.forEach(x=>{const o=document.createElement('option');o.value=x.id;o.textContent=x.name;orgSelect.appendChild(o);});
 // Keep the current organization when switching within the same list; otherwise
 // automatically choose the only available workspace.
 if (list.some(x=>String(x.id)===String(previous))) {
   orgSelect.value=previous;
 } else if (list.length===1) {
   orgSelect.value=String(list[0].id);
 }
 orgSelect.required = role !== 'student';
 orgHint.textContent = role==='industry'
   ? 'This account will manage the selected company workspace.'
   : role==='institute'
     ? 'This account will manage the selected institute workspace.'
     : 'Select the institute you currently attend. You can complete academic details from your profile.';
 googleRegister.href='google_start.php?mode=register&role='+encodeURIComponent(role)+'&organization_id='+encodeURIComponent(orgSelect.value||0);
}
roleInputs.forEach(x=>x.addEventListener('change',updateOrg));
orgSelect.addEventListener('change',updateOrg);
updateOrg();
</script>
</body></html>
