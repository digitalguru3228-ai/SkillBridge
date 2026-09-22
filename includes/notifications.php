<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

/**
 * Activity service.
 * This file deliberately supports both the original SKILLBRIDGE activity schema
 * and the normalized 010/011 schema so existing data is preserved.
 */

function sbActivityColumns(PDO $pdo, string $table): array {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $q = $pdo->prepare('SELECT column_name, is_nullable, column_default FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?');
    $q->execute([$table]);
    $map = [];
    foreach ($q->fetchAll() as $r) {
        $map[(string)$r['column_name']] = $r;
    }
    return $cache[$table] = $map;
}

function sbHasColumn(PDO $pdo, string $table, string $column): bool {
    return isset(sbActivityColumns($pdo, $table)[$column]);
}


function sbColumnType(PDO $pdo,string $table,string $column): string {
    $q=$pdo->prepare('SELECT column_type FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1');
    $q->execute([$table,$column]);
    return strtolower((string)($q->fetchColumn() ?: ''));
}

function sbNotificationTypeForColumn(PDO $pdo,string $column,string $type): string {
    if (!sbHasColumn($pdo,'notifications',$column)) return $type;
    $columnType=sbColumnType($pdo,'notifications',$column);
    if (strncmp($columnType,'enum(',5)!==0) return $type;
    preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/",$columnType,$m);
    $allowed=$m[1]??[];
    foreach($allowed as $v) if(strtolower($v)===strtolower($type)) return $v;
    foreach($allowed as $v) if(strtolower($v)==='system') return $v;
    return $allowed[0]??$type;
}

function sbUserIdByEmail(PDO $pdo, string $email): int {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND status = 'Active' LIMIT 1");
    $stmt->execute([$email]);
    return (int)($stmt->fetchColumn() ?: 0);
}

function sbActiveUser(PDO $pdo, int $userId): ?array {
    if ($userId <= 0) return null;
    $stmt = $pdo->prepare("SELECT id,name,email,role,company_id,institute_id FROM users WHERE id=? AND status='Active' LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function sbUserByCandidate(PDO $pdo, int $candidateId): ?array {
    $stmt = $pdo->prepare("SELECT u.id,u.name,u.email,u.role,u.company_id,u.institute_id FROM users u JOIN candidates c ON c.email=u.email WHERE c.id=? AND u.status='Active' LIMIT 1");
    $stmt->execute([$candidateId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function sbUserByCompany(PDO $pdo, int $companyId): ?array {
    $stmt = $pdo->prepare("SELECT id,name,email,role,company_id,institute_id FROM users WHERE company_id=? AND role='industry' AND status='Active' ORDER BY id LIMIT 1");
    $stmt->execute([$companyId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function sbUserByInstitute(PDO $pdo, int $instituteId): ?array {
    $stmt = $pdo->prepare("SELECT id,name,email,role,company_id,institute_id FROM users WHERE institute_id=? AND role='institute' AND status='Active' ORDER BY id LIMIT 1");
    $stmt->execute([$instituteId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function sbEntityCompanyId(PDO $pdo, ?string $entityType, ?int $entityId): ?int {
    if (!$entityType || !$entityId) return null;
    try {
        switch ($entityType) {
            case 'application':
                $q=$pdo->prepare('SELECT o.company_id FROM applications a JOIN opportunities o ON o.id=a.opportunity_id WHERE a.id=? LIMIT 1');
                $q->execute([$entityId]); return ($v=$q->fetchColumn()) !== false ? (int)$v : null;
            case 'opportunity':
                $q=$pdo->prepare('SELECT company_id FROM opportunities WHERE id=? LIMIT 1');
                $q->execute([$entityId]); return ($v=$q->fetchColumn()) !== false ? (int)$v : null;
            case 'learning_program':
                $q=$pdo->prepare('SELECT company_id FROM learning_programs WHERE id=? LIMIT 1');
                $q->execute([$entityId]); return ($v=$q->fetchColumn()) !== false ? (int)$v : null;
            case 'learning_enrollment':
                $q=$pdo->prepare('SELECT lp.company_id FROM learning_enrollments le JOIN learning_programs lp ON lp.id=le.program_id WHERE le.id=? LIMIT 1');
                $q->execute([$entityId]); return ($v=$q->fetchColumn()) !== false ? (int)$v : null;
        }
    } catch (Throwable $e) {
        error_log('SKILLBRIDGE entity company lookup failed: '.$e->getMessage());
    }
    return null;
}

function sbInsertDynamic(PDO $pdo, string $table, array $values): void {
    $columns = sbActivityColumns($pdo, $table);
    $data = [];
    foreach ($values as $column => $value) {
        if (isset($columns[$column])) $data[$column] = $value;
    }
    if (!$data) throw new RuntimeException("No compatible columns found in {$table}.");
    $names = array_keys($data);
    $sql = 'INSERT INTO '.$table.' (`'.implode('`,`',$names).'`) VALUES ('.implode(',',array_fill(0,count($names),'?')).')';
    $stmt=$pdo->prepare($sql);
    $stmt->execute(array_values($data));
}

function sbCreateNotification(PDO $pdo, int $userId, string $title, string $message, string $type='system', ?string $entityType=null, ?int $entityId=null): void {
    if ($userId <= 0 || !tableExists($pdo,'notifications')) return;
    $user = sbActiveUser($pdo,$userId);
    $companyId = $user && !empty($user['company_id']) ? (int)$user['company_id'] : sbEntityCompanyId($pdo,$entityType,$entityId);

    $values = [
        'user_id'=>$userId,
        'type'=>sbNotificationTypeForColumn($pdo,'type',$type),
        'notification_type'=>sbNotificationTypeForColumn($pdo,'notification_type',$type),
        'title'=>mb_substr($title,0,180),
        'message'=>$message,
        'entity_type'=>$entityType,
        'entity_id'=>$entityId,
        'company_id'=>$companyId,
        'is_read'=>0
    ];
    sbInsertDynamic($pdo,'notifications',$values);
}

function sbCreateMessage(PDO $pdo, int $recipientUserId, string $subject, string $body, ?int $senderUserId=null, ?int $companyId=null): void {
    if ($recipientUserId <= 0 || !tableExists($pdo,'messages')) return;

    $recipient = sbActiveUser($pdo,$recipientUserId);
    $sender = $senderUserId ? sbActiveUser($pdo,$senderUserId) : null;
    $resolvedCompany = $companyId
        ?? ($recipient && !empty($recipient['company_id']) ? (int)$recipient['company_id'] : null)
        ?? ($sender && !empty($sender['company_id']) ? (int)$sender['company_id'] : null);

    $values = [
        'sender_user_id'=>$senderUserId ?: null,
        'recipient_user_id'=>$recipientUserId,
        'company_id'=>$resolvedCompany,
        'sender_name'=>$sender['name'] ?? 'SKILLBRIDGE System',
        'recipient_name'=>$recipient['name'] ?? '',
        'subject'=>mb_substr($subject,0,180),
        'body'=>$body,
        'message'=>$body,
        'is_read'=>0
    ];
    sbInsertDynamic($pdo,'messages',$values);
}

function sbSendEmail(PDO $pdo, int $userId, string $to, string $subject, string $htmlBody): bool {
    if (!filter_var($to,FILTER_VALIDATE_EMAIL)) return false;

    if (!defined('EMAIL_ENABLED') || !EMAIL_ENABLED) {
        sbInsertEmailLog($pdo,$userId,$to,$subject,'disabled','Email delivery is disabled in config.php');
        return false;
    }

    $ok=false; $error=null;
    try {
        $transport = defined('EMAIL_TRANSPORT') ? strtolower((string)EMAIL_TRANSPORT) : 'smtp';
        if ($transport === 'smtp') {
            $ok = sbSmtpSend($to,$subject,$htmlBody);
            if (!$ok) $error='SMTP delivery failed. Check SMTP host, port, encryption, username/password and recipient.';
        } else {
            $from = defined('EMAIL_FROM') ? EMAIL_FROM : 'noreply@skillbridge.local';
            $fromName = defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : 'SKILLBRIDGE';
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= 'From: '.sbHeaderSafe($fromName).' <'.sbHeaderSafe($from).">\r\n";
            $headers .= 'Reply-To: '.sbHeaderSafe($from)."\r\n";
            $ok = @mail($to,$subject,$htmlBody,$headers);
            if (!$ok) $error='PHP mail() returned false.';
        }
    } catch (Throwable $e) {
        $error=$e->getMessage();
        error_log('SKILLBRIDGE email failed: '.$error);
    }
    sbInsertEmailLog($pdo,$userId,$to,$subject,$ok?'sent':'failed',$error);
    return $ok;
}

function sbInsertEmailLog(PDO $pdo,int $userId,string $to,string $subject,string $status,?string $error):void {
    try {
        if (!tableExists($pdo,'email_logs')) return;
        sbInsertDynamic($pdo,'email_logs',[
            'user_id'=>$userId ?: null,
            'recipient_email'=>$to,
            'subject'=>mb_substr($subject,0,255),
            'status'=>$status,
            'error_message'=>$error
        ]);
    } catch(Throwable $e) {
        error_log('SKILLBRIDGE email log failed: '.$e->getMessage());
    }
}

function sbHeaderSafe(string $v): string {
    return trim(str_replace(["\r","\n"],'',$v));
}

function sbSmtpRead($socket): string {
    $response='';
    while (($line=fgets($socket,515)) !== false) {
        $response.=$line;
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    $code=(int)substr($response,0,3);
    if ($code < 200 || $code >= 400) throw new RuntimeException('SMTP '.$code.': '.trim($response));
    return $response;
}

function sbSmtpCmd($socket,string $command): string {
    fwrite($socket,$command."\r\n");
    return sbSmtpRead($socket);
}

function sbSmtpSend(string $to,string $subject,string $htmlBody): bool {
    $host = defined('SMTP_HOST') ? trim((string)SMTP_HOST) : '';
    $port = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
    $user = defined('SMTP_USERNAME') ? (string)SMTP_USERNAME : '';
    $pass = defined('SMTP_PASSWORD') ? (string)SMTP_PASSWORD : '';
    $encryption = defined('SMTP_ENCRYPTION') ? strtolower((string)SMTP_ENCRYPTION) : 'tls';
    $from = defined('EMAIL_FROM') ? (string)EMAIL_FROM : $user;
    $fromName = defined('EMAIL_FROM_NAME') ? (string)EMAIL_FROM_NAME : 'SKILLBRIDGE';

    if ($host==='' || $user==='' || $pass==='') throw new RuntimeException('SMTP is not configured. Set SMTP_HOST, SMTP_USERNAME and SMTP_PASSWORD in config.php.');

    $remote = $encryption === 'ssl' ? 'ssl://'.$host.':'.$port : $host.':'.$port;
    $context = stream_context_create(['ssl'=>[
        'verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false,
        'crypto_method'=>STREAM_CRYPTO_METHOD_TLS_CLIENT
    ]]);
    $socket=@stream_socket_client($remote,$errno,$errstr,20,STREAM_CLIENT_CONNECT,$context);
    if (!$socket) throw new RuntimeException("SMTP connection failed: {$errstr} ({$errno})");
    stream_set_timeout($socket,20);

    try {
        sbSmtpRead($socket);
        $local = defined('SMTP_HELO_NAME') ? (string)SMTP_HELO_NAME : 'localhost';
        sbSmtpCmd($socket,'EHLO '.$local);

        if ($encryption === 'tls') {
            sbSmtpCmd($socket,'STARTTLS');
            if (!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP STARTTLS negotiation failed.');
            }
            sbSmtpCmd($socket,'EHLO '.$local);
        }

        sbSmtpCmd($socket,'AUTH LOGIN');
        sbSmtpCmd($socket,base64_encode($user));
        sbSmtpCmd($socket,base64_encode($pass));
        sbSmtpCmd($socket,'MAIL FROM:<'.sbHeaderSafe($from).'>');
        sbSmtpCmd($socket,'RCPT TO:<'.sbHeaderSafe($to).'>');
        sbSmtpCmd($socket,'DATA');

        $encodedSubject='=?UTF-8?B?'.base64_encode($subject).'?=';
        $headers = 'From: '.sbHeaderSafe($fromName).' <'.sbHeaderSafe($from)."\r\n";
        $headers .= 'To: <'.sbHeaderSafe($to).">\r\n";
        $headers .= 'Subject: '.$encodedSubject."\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";
        $headers .= "Date: ".date(DATE_RFC2822)."\r\n";
        $data = preg_replace("/\r?\n/","\r\n",$headers."\r\n".$htmlBody);
        $data = preg_replace('/^\./m','..',$data ?? '');
        fwrite($socket,$data."\r\n.\r\n");
        sbSmtpRead($socket);
        sbSmtpCmd($socket,'QUIT');
        return true;
    } finally {
        fclose($socket);
    }
}

function sbNotifyUser(PDO $pdo, int $userId, string $title, string $message, string $type='system', ?string $entityType=null, ?int $entityId=null, bool $sendEmail=true): void {
    if ($userId <= 0) return;

    // Each delivery channel is independent. A legacy schema issue in one
    // channel must not prevent the other channels from being delivered.
    try {
        sbCreateNotification($pdo,$userId,$title,$message,$type,$entityType,$entityId);
    } catch (Throwable $e) {
        error_log('SKILLBRIDGE notification insert failed for user '.$userId.': '.$e->getMessage());
    }

    try {
        sbCreateMessage($pdo,$userId,$title,$message,null,sbEntityCompanyId($pdo,$entityType,$entityId));
    } catch (Throwable $e) {
        error_log('SKILLBRIDGE activity message insert failed for user '.$userId.': '.$e->getMessage());
    }

    if ($sendEmail) {
        try {
            $u=sbActiveUser($pdo,$userId);
            if ($u && filter_var($u['email'],FILTER_VALIDATE_EMAIL)) {
                $safeName=htmlspecialchars((string)$u['name'],ENT_QUOTES,'UTF-8');
                $safeTitle=htmlspecialchars($title,ENT_QUOTES,'UTF-8');
                $safeMsg=nl2br(htmlspecialchars($message,ENT_QUOTES,'UTF-8'));
                $html='<div style="font-family:Arial,sans-serif;max-width:640px;margin:0 auto;padding:28px;color:#102a4c"><h2 style="margin:0 0 8px;color:#123d78">SKILLBRIDGE</h2><h3 style="margin:0 0 18px">'.$safeTitle.'</h3><p>Hello '.$safeName.',</p><p style="line-height:1.7">'.$safeMsg.'</p><p style="margin-top:28px;color:#64748b;font-size:12px">This is an automated SKILLBRIDGE activity notification.</p></div>';
                sbSendEmail($pdo,$userId,(string)$u['email'],$title,$html);
            }
        } catch (Throwable $e) {
            error_log('SKILLBRIDGE activity email failed for user '.$userId.': '.$e->getMessage());
        }
    }
}

function sbSendUserMessage(PDO $pdo,int $senderUserId,int $recipientUserId,string $subject,string $body,bool $sendEmail=true):void {
    $recipient=sbActiveUser($pdo,$recipientUserId);
    if (!$recipient) throw new RuntimeException('Recipient account is not active.');
    $sender=sbActiveUser($pdo,$senderUserId);
    $companyId = $sender && !empty($sender['company_id']) ? (int)$sender['company_id'] : (!empty($recipient['company_id']) ? (int)$recipient['company_id'] : null);

    sbCreateMessage($pdo,$recipientUserId,$subject,$body,$senderUserId,$companyId);
    sbCreateNotification($pdo,$recipientUserId,$subject,$body,'message','message',null);

    if ($sendEmail && filter_var($recipient['email'],FILTER_VALIDATE_EMAIL)) {
        $safeName=htmlspecialchars((string)$recipient['name'],ENT_QUOTES,'UTF-8');
        $safeSubject=htmlspecialchars($subject,ENT_QUOTES,'UTF-8');
        $safeBody=nl2br(htmlspecialchars($body,ENT_QUOTES,'UTF-8'));
        $html='<div style="font-family:Arial,sans-serif;max-width:640px;margin:0 auto;padding:28px;color:#102a4c"><h2 style="color:#123d78">SKILLBRIDGE</h2><h3>'.$safeSubject.'</h3><p>Hello '.$safeName.',</p><p style="line-height:1.7">'.$safeBody.'</p></div>';
        sbSendEmail($pdo,$recipientUserId,(string)$recipient['email'],$subject,$html);
    }
}

function sbNotifyCandidate(PDO $pdo,int $candidateId,string $title,string $message,string $type='system',?string $entityType=null,?int $entityId=null):void {
    $u=sbUserByCandidate($pdo,$candidateId);
    if ($u) sbNotifyUser($pdo,(int)$u['id'],$title,$message,$type,$entityType,$entityId,true);
}

function sbGetNotificationsForUser(PDO $pdo,int $userId,int $limit=80):array {
    if ($userId<=0 || !tableExists($pdo,'notifications')) return [];
    $companyId=(int)(sbActiveUser($pdo,$userId)['company_id'] ?? 0);
    $sql='SELECT * FROM notifications WHERE user_id=?';
    $params=[$userId];
    // Legacy rows may have user_id NULL but belong to the user's company.
    if ($companyId>0 && sbHasColumn($pdo,'notifications','company_id')) {
        $sql='SELECT * FROM notifications WHERE user_id=? OR (user_id IS NULL AND company_id=?)';
        $params=[$userId,$companyId];
    }
    $sql.=' ORDER BY is_read ASC, created_at DESC, id DESC LIMIT '.max(1,min(200,$limit));
    try {$q=$pdo->prepare($sql);$q->execute($params);return $q->fetchAll();} catch(Throwable $e){error_log('SKILLBRIDGE notification read failed: '.$e->getMessage());return [];}
}

function sbGetMessagesForUser(PDO $pdo,int $userId,int $limit=50):array {
    if ($userId<=0 || !tableExists($pdo,'messages')) return [];
    $companyId=(int)(sbActiveUser($pdo,$userId)['company_id'] ?? 0);
    $hasRecipient=sbHasColumn($pdo,'messages','recipient_user_id');
    $hasCompany=sbHasColumn($pdo,'messages','company_id');
    $where=$hasRecipient?'m.recipient_user_id=?':'1=0';
    $params=[$userId];
    if ($companyId>0 && $hasCompany) {$where='('.$where.' OR (m.recipient_user_id IS NULL AND m.company_id=?))';$params[]=$companyId;}
    $q=$pdo->prepare('SELECT m.*, u.name AS resolved_sender_name FROM messages m LEFT JOIN users u ON u.id=m.sender_user_id WHERE '.$where.' ORDER BY m.is_read ASC,m.created_at DESC,m.id DESC LIMIT '.max(1,min(100,$limit)));
    try {$q->execute($params);$rows=$q->fetchAll();foreach($rows as &$r){if(empty($r['sender_name']))$r['sender_name']=$r['resolved_sender_name']??'SKILLBRIDGE System';if(empty($r['body']))$r['body']=$r['message']??'';}return $rows;}catch(Throwable $e){error_log('SKILLBRIDGE message read failed: '.$e->getMessage());return [];}
}

function sbMessageRecipients(PDO $pdo,int $currentUserId,string $currentRole=''):array {
    $sql="SELECT id,name,email,role FROM users WHERE status='Active' AND id<>?";
    $params=[$currentUserId];
    // Keep the picker useful without exposing inactive accounts.
    if ($currentRole==='student') {
        $sql.=" AND role IN ('industry','institute','admin')";
    } elseif ($currentRole==='industry') {
        $sql.=" AND role IN ('student','institute','admin')";
    } elseif ($currentRole==='institute') {
        $sql.=" AND role IN ('student','industry','admin')";
    }
    $sql.=" ORDER BY role,name";
    $q=$pdo->prepare($sql);$q->execute($params);return $q->fetchAll();
}

function sbMarkNotificationRead(PDO $pdo,int $userId,int $notificationId):void {
    if ($notificationId<=0 || !tableExists($pdo,'notifications')) return;
    $companyId=(int)(sbActiveUser($pdo,$userId)['company_id']??0);
    if ($companyId>0 && sbHasColumn($pdo,'notifications','company_id')) {
        $q=$pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND (user_id=? OR (user_id IS NULL AND company_id=?))');
        $q->execute([$notificationId,$userId,$companyId]);
    } else {$q=$pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?');$q->execute([$notificationId,$userId]);}
}

function sbMarkMessageRead(PDO $pdo,int $userId,int $messageId):void {
    if ($messageId<=0 || !tableExists($pdo,'messages')) return;
    $q=$pdo->prepare('UPDATE messages SET is_read=1 WHERE id=? AND recipient_user_id=?');
    $q->execute([$messageId,$userId]);
}

function sbMarkAllRead(PDO $pdo,int $userId):void {
    if ($userId<=0) return;
    if (tableExists($pdo,'notifications')) {
        $companyId=(int)(sbActiveUser($pdo,$userId)['company_id']??0);
        if ($companyId>0 && sbHasColumn($pdo,'notifications','company_id')) {
            $q=$pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=? OR (user_id IS NULL AND company_id=?)');$q->execute([$userId,$companyId]);
        } else {$q=$pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?');$q->execute([$userId]);}
    }
    if (tableExists($pdo,'messages')) {
        $q=$pdo->prepare('UPDATE messages SET is_read=1 WHERE recipient_user_id=?');$q->execute([$userId]);
    }
}

function sbUnreadNotificationCount(PDO $pdo,int $userId):int {
    if ($userId<=0) return 0;
    try {
        $companyId=(int)(sbActiveUser($pdo,$userId)['company_id']??0);
        if ($companyId>0 && sbHasColumn($pdo,'notifications','company_id')) {
            $q=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE is_read=0 AND (user_id=? OR (user_id IS NULL AND company_id=?))');$q->execute([$userId,$companyId]);
        } else {$q=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');$q->execute([$userId]);}
        return (int)$q->fetchColumn();
    } catch(Throwable $e){return 0;}
}

function sbUnreadActivityCount(PDO $pdo,int $userId):int {
    $n=sbUnreadNotificationCount($pdo,$userId);
    try {$q=$pdo->prepare('SELECT COUNT(*) FROM messages WHERE recipient_user_id=? AND is_read=0');$q->execute([$userId]);return $n+(int)$q->fetchColumn();}catch(Throwable $e){return $n;}
}
