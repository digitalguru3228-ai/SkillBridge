<?php
declare(strict_types=1);

define('DB_HOST', getenv('SKILLBRIDGE_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('SKILLBRIDGE_DB_PORT') ?: 3306));
define('DB_NAME', getenv('SKILLBRIDGE_DB_NAME') ?: 'industry_hub');
define('DB_USER', getenv('SKILLBRIDGE_DB_USER') ?: 'root');
define('DB_PASS', getenv('SKILLBRIDGE_DB_PASS') ?: '');

define('ASSESSMENT_QUESTIONS_PER_ATTEMPT', (int)(getenv('SKILLBRIDGE_ASSESSMENT_QUESTIONS') ?: 5));

define('GOOGLE_CLIENT_ID', getenv('SKILLBRIDGE_GOOGLE_CLIENT_ID') ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('SKILLBRIDGE_GOOGLE_CLIENT_SECRET') ?: '');
define('GOOGLE_REDIRECT_URI', getenv('SKILLBRIDGE_GOOGLE_REDIRECT_URI') ?: 'http://localhost/SkillBridge/google_callback.php');

define('EMAIL_ENABLED', filter_var(getenv('SKILLBRIDGE_EMAIL_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN));
define('EMAIL_TRANSPORT', getenv('SKILLBRIDGE_EMAIL_TRANSPORT') ?: 'smtp');
define('EMAIL_FROM', getenv('SKILLBRIDGE_EMAIL_FROM') ?: getenv('SKILLBRIDGE_SMTP_USERNAME') ?: 'noreply@skillbridge.local');
define('EMAIL_FROM_NAME', getenv('SKILLBRIDGE_EMAIL_FROM_NAME') ?: 'SKILLBRIDGE');
define('SMTP_HOST', getenv('SKILLBRIDGE_SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', (int)(getenv('SKILLBRIDGE_SMTP_PORT') ?: 587));
define('SMTP_ENCRYPTION', getenv('SKILLBRIDGE_SMTP_ENCRYPTION') ?: 'tls');
define('SMTP_USERNAME', getenv('SKILLBRIDGE_SMTP_USERNAME') ?: '');
define('SMTP_PASSWORD', getenv('SKILLBRIDGE_SMTP_PASSWORD') ?: '');
define('SMTP_HELO_NAME', getenv('SKILLBRIDGE_SMTP_HELO_NAME') ?: ($_SERVER['HTTP_HOST'] ?? 'localhost'));
