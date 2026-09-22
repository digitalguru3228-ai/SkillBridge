<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

function googleHttp(string $url, array $headers = [], ?string $body = null): array {
    if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL extension is required for Google Sign-In.');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($response === false || $err) throw new RuntimeException('Google request failed.');
    $data = json_decode($response, true);
    if ($status < 200 || $status >= 300 || !is_array($data)) throw new RuntimeException('Google authentication response was invalid.');
    return $data;
}

$oauth = $_SESSION['google_oauth'] ?? null;
unset($_SESSION['google_oauth']);
if (!is_array($oauth) || empty($oauth['state']) || !hash_equals((string)$oauth['state'], (string)($_GET['state'] ?? '')) || (time() - (int)($oauth['created_at'] ?? 0)) > 600) {
    http_response_code(400);
    exit('Google sign-in session expired. Please start again.');
}
if (!empty($_GET['error'])) {
    header('Location: index.php?google=cancelled'); exit;
}
$code = (string)($_GET['code'] ?? '');
if ($code === '') {
    http_response_code(400); exit('Google authorization code was not returned.');
}
try {
    $token = googleHttp('https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ]));
    $accessToken = (string)($token['access_token'] ?? '');
    if ($accessToken === '') throw new RuntimeException('Google access token was not returned.');
    $profile = googleHttp('https://openidconnect.googleapis.com/v1/userinfo', ['Authorization: Bearer ' . $accessToken]);
    $googleSub = trim((string)($profile['sub'] ?? ''));
    $email = strtolower(trim((string)($profile['email'] ?? '')));
    $name = trim((string)($profile['name'] ?? 'Google User'));
    $avatar = trim((string)($profile['picture'] ?? ''));
    $verified = filter_var($profile['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if ($googleSub === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$verified) throw new RuntimeException('Google did not return a verified email address.');

    $pdo = db();
    $q = $pdo->prepare('SELECT id,name,email,role,company_id,institute_id,status,auth_provider FROM users WHERE google_sub=? OR email=? LIMIT 1');
    $q->execute([$googleSub, $email]);
    $u = $q->fetch();

    if ($u) {
        if (($u['status'] ?? '') !== 'Active') throw new RuntimeException('This SkillBridge account is inactive.');
        if (empty($u['google_sub'])) {
            $pdo->prepare('UPDATE users SET google_sub=?, auth_provider=\'google\', avatar_url=?, email_verified_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$googleSub,$avatar,(int)$u['id']]);
            $u['auth_provider'] = 'google';
        }
        signInUser($u, $pdo);
        redirectByRole((string)$u['role']);
    }

    if (($oauth['mode'] ?? 'login') !== 'register') {
        header('Location: register.php?google=' . rawurlencode('new')); exit;
    }

    $role = (string)($oauth['role'] ?? '');
    $orgId = max(0, (int)($oauth['organization_id'] ?? 0));
    if (!in_array($role, ['student','institute','industry'], true)) throw new RuntimeException('Choose a valid SkillBridge role before continuing with Google.');

    $companyId = null; $instituteId = null;
    if ($role === 'industry') {
        $st = $pdo->prepare('SELECT id FROM companies WHERE id=? LIMIT 1'); $st->execute([$orgId]);
        if (!$st->fetchColumn()) throw new RuntimeException('Selected company was not found.');
        $companyId = $orgId;
    } elseif ($role === 'institute') {
        $st = $pdo->prepare('SELECT id FROM institutes WHERE id=? LIMIT 1'); $st->execute([$orgId]);
        if (!$st->fetchColumn()) throw new RuntimeException('Selected institute was not found.');
        $instituteId = $orgId;
    } else {
        if ($orgId > 0) {
            $st = $pdo->prepare('SELECT id FROM institutes WHERE id=? LIMIT 1'); $st->execute([$orgId]);
            if (!$st->fetchColumn()) throw new RuntimeException('Selected institute was not found.');
            $instituteId = $orgId;
        }
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO users (name,email,password_hash,role,company_id,institute_id,status,auth_provider,google_sub,avatar_url,email_verified_at) VALUES (?,?,?,?,?,?,\'Active\',\'google\',?,?,CURRENT_TIMESTAMP)');
    $stmt->execute([$name,$email,null,$role,$companyId,$instituteId,$googleSub,$avatar]);
    $userId = (int)$pdo->lastInsertId();

    if ($role === 'student') {
        $candidateStmt = $pdo->prepare('INSERT INTO candidates (name,email,qualification,experience_years,match_score,skills,institute_id) VALUES (?,?,?,?,?,?,?)');
        $candidateStmt->execute([$name,$email,'',0,0,'[]',$instituteId]);
        $candidateId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO student_profiles (candidate_id,user_id,degree,branch,semester,cgpa) VALUES (?,?,?,?,?,?)')->execute([$candidateId,$userId,'','','',0]);
    }
    $pdo->commit();

    $q = $pdo->prepare('SELECT id,name,email,role,company_id,institute_id,status FROM users WHERE id=? LIMIT 1');
    $q->execute([$userId]);
    $u = $q->fetch();
    signInUser($u, $pdo);
    redirectByRole($role);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{font-family:Inter,Arial;background:#f5f8fc;padding:40px;color:#102a4c}.card{max-width:620px;margin:auto;background:#fff;padding:30px;border-radius:18px;border:1px solid #e2e8f0}.btn{display:inline-block;margin-top:16px;background:#167be8;color:#fff;padding:12px 18px;border-radius:10px;text-decoration:none}</style><div class="card"><h2>Google Sign-In could not be completed</h2><p>'.e($e->getMessage()).'</p><a class="btn" href="index.php">Back to Sign In</a></div>';
}
