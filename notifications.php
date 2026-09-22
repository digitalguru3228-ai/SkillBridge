<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/notifications.php';

requireLogin();

$pdo = db();
$userId = (int)$_SESSION['user_id'];
$userRole = (string)($_SESSION['user_role'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $_SESSION['activity_flash'] = ['type'=>'error','message'=>'Security token expired. Please refresh and try again.'];
        header('Location: notifications.php');
        exit;
    }

    try {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'mark_all') {
            sbMarkAllRead($pdo, $userId);
            $_SESSION['activity_flash'] = ['type'=>'success','message'=>'All notifications and messages marked as read.'];
        } elseif ($action === 'mark_notification') {
            $id = (int)($_POST['id'] ?? 0);
            sbMarkNotificationRead($pdo, $userId, $id);
        } elseif ($action === 'mark_message') {
            $id = (int)($_POST['id'] ?? 0);
            sbMarkMessageRead($pdo, $userId, $id);
        } elseif ($action === 'send_message') {
            $recipientId = (int)($_POST['recipient_user_id'] ?? 0);
            $subject = trim((string)($_POST['subject'] ?? ''));
            $body = trim((string)($_POST['body'] ?? ''));

            if ($recipientId <= 0 || $recipientId === $userId) {
                throw new RuntimeException('Please select a valid recipient.');
            }
            if ($subject === '' || mb_strlen($subject) > 180) {
                throw new RuntimeException('Subject is required and must be 180 characters or fewer.');
            }
            if ($body === '' || mb_strlen($body) > 10000) {
                throw new RuntimeException('Message is required and must be 10,000 characters or fewer.');
            }

            $recipient = sbActiveUser($pdo, $recipientId);
            if (!$recipient) {
                throw new RuntimeException('The selected recipient is not available.');
            }

            sbSendUserMessage($pdo, $userId, $recipientId, $subject, $body, true);
            $_SESSION['activity_flash'] = ['type'=>'success','message'=>'Message sent successfully.'];
        }
    } catch (Throwable $e) {
        error_log('SKILLBRIDGE activity action failed: ' . $e->getMessage());
        $_SESSION['activity_flash'] = ['type'=>'error','message'=>$e->getMessage()];
    }

    header('Location: notifications.php');
    exit;
}

$notifications = sbGetNotificationsForUser($pdo, $userId, 80);
$messages = sbGetMessagesForUser($pdo, $userId, 50);
$unread = sbUnreadActivityCount($pdo, $userId);
$recipients = sbMessageRecipients($pdo, $userId, $userRole);
$flash = $_SESSION['activity_flash'] ?? null;
unset($_SESSION['activity_flash']);

function e2(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$dash = $userRole === 'student'
    ? 'student/'
    : ($userRole === 'industry' ? 'Industry%20dashboard/' : 'institute_dashboard_php/');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SKILLBRIDGE | Notifications & Messages</title>
<style>
:root{--blue:#167be8;--navy:#102a4c;--bg:#f5f8fc;--line:#e2e8f0;--muted:#64748b;--green:#0f9f6e;--red:#c53b4a}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--navy);font-family:Inter,Arial,sans-serif}
.wrap{max-width:1120px;margin:32px auto;padding:0 20px}.top{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;margin-bottom:20px}
.eyebrow{font-size:11px;letter-spacing:.12em;color:var(--blue);font-weight:800}.top h1{margin:5px 0 0;font-size:30px}.top p{margin:7px 0 0;color:var(--muted);font-size:13px}
.actions{display:flex;gap:10px;align-items:center}.back{color:var(--blue);text-decoration:none;font-weight:700;font-size:13px}
.card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:22px;margin-bottom:18px;box-shadow:0 8px 28px rgba(20,50,90,.05)}
.grid{display:grid;grid-template-columns:1.2fr .8fr;gap:18px}.item{padding:16px 0;border-bottom:1px solid #edf2f7}.item:last-child{border-bottom:0}
.unread{background:#f5faff;border-left:3px solid var(--blue);padding-left:13px}.title{font-weight:800;margin-bottom:5px}.msg{font-size:13px;line-height:1.6;color:#475569}.meta{font-size:11px;color:#94a3b8;margin-top:8px}
.btn{border:0;border-radius:9px;padding:9px 13px;background:var(--blue);color:#fff;font-weight:700;cursor:pointer}.btn.secondary{background:#eef6ff;color:#126bc8}
.empty{color:#94a3b8;text-align:center;padding:30px}.flash{padding:12px 14px;border-radius:10px;margin-bottom:16px;font-size:13px}.flash.success{background:#ecfdf5;color:#087f5b}.flash.error{background:#fff1f2;color:#b42336}
.form-grid{display:grid;gap:12px}.field label{display:block;font-size:12px;font-weight:800;margin-bottom:6px}.field input,.field select,.field textarea{width:100%;border:1px solid #dbe4ef;border-radius:10px;padding:10px 12px;font:inherit;color:var(--navy);outline:none}.field textarea{min-height:130px;resize:vertical}.field input:focus,.field select:focus,.field textarea:focus{border-color:#79b4f2;box-shadow:0 0 0 3px #eaf4ff}
h2{font-size:18px;margin:0 0 5px}.section-sub{font-size:12px;color:var(--muted);margin:0 0 15px}.count{display:inline-flex;min-width:22px;height:22px;border-radius:99px;background:#eaf4ff;color:var(--blue);align-items:center;justify-content:center;font-size:11px;font-weight:800;margin-left:6px}
@media(max-width:800px){.grid{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<main class="wrap">
<div class="top">
  <div><div class="eyebrow">ACCOUNT ACTIVITY</div><h1>Notifications & Messages</h1><p>Applications, sign-ins, assessment scores, learning progress, profile activity and direct messages.</p></div>
  <div class="actions"><a class="back" href="<?=$dash?>">← Dashboard</a><?php if($unread): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=e2(csrfToken())?>"><input type="hidden" name="action" value="mark_all"><button class="btn">Mark all read</button></form><?php endif; ?></div>
</div>

<?php if($flash): ?><div class="flash <?=e2($flash['type'])?>"><?=e2($flash['message'])?></div><?php endif; ?>

<div class="grid">
<section class="card">
<h2>Notifications <span class="count"><?=count($notifications)?></span></h2>
<p class="section-sub">Automatic activity alerts for your account.</p>
<?php if(!$notifications): ?><div class="empty">No notifications yet.</div>
<?php else: foreach($notifications as $n): ?>
<div class="item <?=((int)$n['is_read']===0?'unread':'')?>">
  <div class="title"><?=e2($n['title'])?></div>
  <div class="msg"><?=nl2br(e2($n['message']))?></div>
  <div class="meta"><?=e2(date('d M Y, h:i A',strtotime((string)$n['created_at'])))?> · <?=e2(ucfirst((string)($n['type'] ?? $n['notification_type'] ?? 'system')))?>
  <?php if((int)$n['is_read']===0): ?><form method="post" style="display:inline;margin-left:10px"><input type="hidden" name="csrf_token" value="<?=e2(csrfToken())?>"><input type="hidden" name="action" value="mark_notification"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button style="border:0;background:none;color:var(--blue);font-weight:700;cursor:pointer">Mark read</button></form><?php endif; ?></div>
</div>
<?php endforeach; endif; ?>
</section>

<section class="card">
<h2>Send a Message</h2>
<p class="section-sub">Send a direct portal message. The recipient also receives an in-app notification and an email attempt.</p>
<form method="post" class="form-grid">
<input type="hidden" name="csrf_token" value="<?=e2(csrfToken())?>">
<input type="hidden" name="action" value="send_message">
<div class="field"><label>Recipient</label><select name="recipient_user_id" required><option value="">Select a user</option><?php foreach($recipients as $r): ?><option value="<?= (int)$r['id'] ?>"><?=e2($r['name'])?> · <?=e2(ucfirst($r['role']))?></option><?php endforeach; ?></select></div>
<div class="field"><label>Subject</label><input name="subject" maxlength="180" required></div>
<div class="field"><label>Message</label><textarea name="body" maxlength="10000" required></textarea></div>
<button class="btn" type="submit">Send message</button>
</form>
</section>
</div>

<section class="card">
<h2>Messages <span class="count"><?=count($messages)?></span></h2>
<p class="section-sub">Direct and system messages addressed to this account.</p>
<?php if(!$messages): ?><div class="empty">No messages yet.</div>
<?php else: foreach($messages as $m): ?>
<div class="item <?=((int)$m['is_read']===0?'unread':'')?>">
  <div class="title"><?=e2($m['subject'] ?: 'Message')?></div>
  <div class="msg"><?=nl2br(e2($m['body'] ?: $m['message'] ?? ''))?></div>
  <div class="meta"><?=e2(date('d M Y, h:i A',strtotime((string)$m['created_at'])))?> · <?= $m['sender_name'] ? 'From '.e2($m['sender_name']) : 'SKILLBRIDGE System' ?>
  <?php if((int)$m['is_read']===0): ?><form method="post" style="display:inline;margin-left:10px"><input type="hidden" name="csrf_token" value="<?=e2(csrfToken())?>"><input type="hidden" name="action" value="mark_message"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button style="border:0;background:none;color:var(--blue);font-weight:700;cursor:pointer">Mark read</button></form><?php endif; ?></div>
</div>
<?php endforeach; endif; ?>
</section>
</main>
</body>
</html>
