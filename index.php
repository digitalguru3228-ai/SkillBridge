<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_role'])) {
    redirectByRole((string)$_SESSION['user_role']);
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session token expired. Please refresh and try again.';
    } else {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $error = 'Please enter a valid email address and password.';
        } else {
            try {
                $q = db()->prepare('SELECT id,name,email,password_hash,role,company_id,institute_id,status FROM users WHERE email=? LIMIT 1');
                $q->execute([$email]);
                $u = $q->fetch();
                if ($u && $u['status'] === 'Active' && !empty($u['password_hash']) && password_verify($password, $u['password_hash'])) {
                    signInUser($u, db());
                    redirectByRole((string)$u['role']);
                }
                $error = $u && ($u['status'] ?? '') !== 'Active'
                    ? 'This account is inactive. Please contact the administrator.'
                    : 'Invalid email or password.';
            } catch (Throwable $e) {
                $error = 'Login is temporarily unavailable. Check the database connection and authentication migration.';
            }
        }
    }
}
$csrf = csrfToken();
$googleReady = GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SKILLBRIDGE | Sign In</title>
<style>
@import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap");
:root{--navy:#123d78;--blue:#167be8;--bg:#f5f8fc;--line:#e2e8f0;--muted:#64748b;--text:#102a4c}
*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,system-ui,sans-serif;background:radial-gradient(circle at top right,#eaf5ff,transparent 35%),var(--bg);color:var(--text);display:grid;place-items:center;padding:24px}
.shell{width:min(1040px,100%);min-height:650px;background:#fff;border:1px solid var(--line);border-radius:28px;box-shadow:0 24px 70px rgba(25,55,95,.12);display:grid;grid-template-columns:1.05fr .95fr;overflow:hidden}
.intro{padding:58px;display:flex;flex-direction:column;justify-content:center;background:linear-gradient(145deg,#f8fbff,#eef7ff)}.logo{width:78px;height:78px;object-fit:contain;margin-bottom:22px}.eyebrow{font-size:10px;letter-spacing:.13em;color:var(--blue);font-weight:800}.intro h1{margin:7px 0 0;font-size:42px;letter-spacing:-1.8px;color:var(--navy)}.intro h1 span{color:var(--blue)}.intro p{max-width:470px;color:#61738e;line-height:1.75;font-size:15px}.pill{display:inline-flex;width:max-content;padding:8px 12px;border-radius:999px;background:#e8f2ff;color:#176fd2;font-size:11px;font-weight:700;letter-spacing:.08em;margin-top:16px}.roles{display:flex;gap:12px;margin-top:34px;flex-wrap:wrap}.role{border:1px solid var(--line);background:#fff;padding:12px 15px;border-radius:12px;font-size:12px;color:#355477}
.login{padding:58px 54px;display:flex;flex-direction:column;justify-content:center}.login h2{font-size:30px;margin:8px 0}.sub{font-size:13px;color:var(--muted);margin:0 0 26px;line-height:1.6}.field{margin-bottom:16px}.field label{display:block;font-size:12px;font-weight:700;margin-bottom:8px}.field input{width:100%;height:46px;border:1px solid #dbe4ef;border-radius:11px;padding:0 13px;outline:none}.field input:focus{border-color:#79b4f2;box-shadow:0 0 0 3px #eaf4ff}.btn{width:100%;height:48px;border:0;border-radius:11px;background:var(--blue);color:#fff;font-weight:700;cursor:pointer}.google{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;height:46px;border:1px solid #d7e0eb;border-radius:11px;background:#fff;color:#243b53;font-weight:700;text-decoration:none;margin-top:12px}.google.disabled{opacity:.55;cursor:not-allowed}.divider{display:flex;align-items:center;gap:12px;color:#94a3b8;font-size:11px;margin:18px 0}.divider:before,.divider:after{content:"";height:1px;background:#e2e8f0;flex:1}.register{margin-top:22px;text-align:center;font-size:12px;color:var(--muted)}.register a{color:var(--blue);font-weight:700;text-decoration:none}.error{background:#fff1f2;color:#b83d49;border:1px solid #ffd7da;border-radius:10px;padding:11px 12px;font-size:12px;margin-bottom:18px}.footer{font-size:11px;color:#8795a9;text-align:center;margin-top:24px}
@media(max-width:800px){.shell{grid-template-columns:1fr;max-width:560px}.intro{padding:36px}.intro h1{font-size:34px}.login{padding:38px}.roles{margin-top:20px}}
</style>
</head>
<body>
<div class="shell">
<section class="intro">
<img class="logo" src="assets/skillbridge-logo.jpeg" alt="SKILLBRIDGE">
<div class="eyebrow">ACADEMIA • INDUSTRY • TALENT</div>
<h1>SKILL<span>BRIDGE</span></h1>
<p>A unified platform connecting academic institutes, industry opportunities and student talent through one secure workspace.</p>
<span class="pill">ONE PLATFORM · ROLE-BASED ACCESS</span>
<div class="roles"><span class="role">Industry Portal</span><span class="role">Institute Portal</span><span class="role">Student Portal</span></div>
</section>
<section class="login">
<div class="eyebrow">SECURE ACCESS</div>
<h2>Welcome back</h2>
<p class="sub">Sign in to your SKILLBRIDGE account. Your role automatically opens the correct workspace.</p>
<?php if($error): ?><div class="error"><?=e($error)?></div><?php endif; ?>
<form method="post" autocomplete="on">
<input type="hidden" name="csrf_token" value="<?=e($csrf)?>">
<div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="username" required></div>
<div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
<button class="btn" type="submit">Sign In</button>
</form>
<div class="divider">OR</div>
<?php if($googleReady): ?>
<a class="google" href="google_start.php">G&nbsp;&nbsp;Continue with Google</a>
<?php else: ?>
<span class="google disabled" title="Configure Google OAuth credentials first">G&nbsp;&nbsp;Continue with Google</span>
<?php endif; ?>
<div class="register">New to SkillBridge? <a href="register.php">Create an account</a></div>
<div class="footer">SKILLBRIDGE · SIH 2026 · PS-44</div>
</section>
</div>
</body>
</html>
