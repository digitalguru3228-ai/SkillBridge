<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

if (GOOGLE_CLIENT_ID === '' || GOOGLE_CLIENT_SECRET === '') {
    http_response_code(503);
    exit('Google Sign-In is not configured. Set SKILLBRIDGE_GOOGLE_CLIENT_ID and SKILLBRIDGE_GOOGLE_CLIENT_SECRET.');
}

$mode = ($_GET['mode'] ?? 'login') === 'register' ? 'register' : 'login';
$role = $_GET['role'] ?? '';
$allowed = ['student','institute','industry'];
if ($mode === 'register' && !in_array($role, $allowed, true)) {
    header('Location: register.php'); exit;
}
$orgId = max(0, (int)($_GET['organization_id'] ?? 0));
$state = bin2hex(random_bytes(24));
$_SESSION['google_oauth'] = [
    'state' => $state,
    'mode' => $mode,
    'role' => $mode === 'register' ? $role : null,
    'organization_id' => $mode === 'register' ? $orgId : null,
    'created_at' => time()
];
$params = http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'access_type' => 'online',
    'prompt' => 'select_account',
    'state' => $state
]);
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;
