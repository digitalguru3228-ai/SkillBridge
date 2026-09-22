<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/notifications.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
function redirectByRole(string $role): void {
    if ($role === 'industry') {
        header('Location: Industry%20dashboard/');
    } elseif ($role === 'institute') {
        header('Location: institute_dashboard_php/');
    } elseif ($role === 'student') {
        header('Location: student/');
    } else {
        header('Location: index.php');
    }
    exit;
}
function requireLogin():void{if(empty($_SESSION['user_id'])||empty($_SESSION['user_role'])){header('Location: ../');exit;}}
function requireRole(string $role):void{requireLogin();if($_SESSION['user_role']!==$role){http_response_code(403);exit('Access denied.');}}
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verifyCsrfToken(?string $token): bool {
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

if (!function_exists('e')) {
    function e(mixed $val): string {
        return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

function signInUser(array $u, ?PDO $pdo = null): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$u['id'];
    $_SESSION['user_name'] = (string)$u['name'];
    $_SESSION['user_email'] = (string)$u['email'];
    $_SESSION['user_role'] = (string)$u['role'];
    $_SESSION['company_id'] = $u['company_id'] !== null ? (int)$u['company_id'] : null;
    $_SESSION['institute_id'] = $u['institute_id'] !== null ? (int)$u['institute_id'] : null;
    $_SESSION['candidate_id'] = 0;
    if ((string)$u['role'] === 'student' && $pdo instanceof PDO) {
        $stmt = $pdo->prepare('SELECT id FROM candidates WHERE email = ? LIMIT 1');
        $stmt->execute([(string)$u['email']]);
        $_SESSION['candidate_id'] = (int)($stmt->fetchColumn() ?: 0);
    }

    // Login activity is recorded after the session is established. Notification/email
    // failures must never prevent a valid user from signing in.
    if ($pdo instanceof PDO) {
        try {
            sbNotifyUser($pdo, (int)$u['id'], 'New sign-in to SKILLBRIDGE',
                'Your SKILLBRIDGE account was signed in successfully. If this was not you, review your account security.',
                'login', 'user', (int)$u['id'], true);
        } catch (Throwable $ignored) {
            // Activity delivery is non-blocking for authentication.
        }
    }
}

function getStudentCandidateId(PDO $pdo): int {
    $cid = (int)($_SESSION['candidate_id'] ?? 0);
    if ($cid <= 0 && !empty($_SESSION['user_email'])) {
        $cStmt = $pdo->prepare("SELECT id FROM candidates WHERE email = ? LIMIT 1");
        $cStmt->execute([$_SESSION['user_email']]);
        $cid = (int)($cStmt->fetchColumn() ?: 0);
        if ($cid > 0) {
            $_SESSION['candidate_id'] = $cid;
        }
    }
    return $cid;
}

function currentUser(?PDO $pdo = null): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $uid = (int)$_SESSION['user_id'];
    if ($pdo instanceof PDO) {
        $stmt = $pdo->prepare("SELECT id, name, email, role, company_id, institute_id, status, auth_provider, avatar_url, email_verified_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$uid]);
        $u = $stmt->fetch();
        if ($u) return $u;
    }
    return [
        'id' => $uid,
        'name' => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? '',
        'company_id' => $_SESSION['company_id'] ?? null,
        'institute_id' => $_SESSION['institute_id'] ?? null
    ];
}



