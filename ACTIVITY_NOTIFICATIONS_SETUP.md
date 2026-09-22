# SKILLBRIDGE Activity, Messaging & Email

This build completes the activity pipeline without replacing the existing email/password authentication or application workflows.

## 1. Run the database repair

Start Apache + MySQL and open:

`http://localhost/SkillBridge/database/run_migration.php`

The runner applies migration 010 and then repairs older activity schemas in-place. It does not drop `notifications` or `messages`.

It normalizes:
- notifications: `user_id`, `type`, `notification_type`, `company_id`, entity fields and read state
- messages: sender/recipient IDs, company compatibility, subject/body and legacy message fields
- email_logs: delivery audit fields and indexes

Existing company notifications are backfilled to the active industry account where possible.

## 2. Activity events wired

- Login
- Student application
- Student withdrawal
- Industry application-status changes
- Learning program join/re-join/withdraw
- Learning progress milestones 25%, 50%, 75%, 100%
- Practice/official assessment completion
- Industry assessment completion and score
- Profile update
- Direct messages

Every event can create:
1. an in-app notification,
2. an inbox message,
3. an email delivery attempt.

Activity delivery is non-blocking: a mail failure never cancels the underlying application, assessment or profile action.

## 3. Notifications & Messages

Open:

`http://localhost/SkillBridge/notifications.php`

The page now:
- reads both legacy and normalized notification/message records,
- supports mark-read and mark-all-read,
- lets the logged-in user send a direct message,
- creates a recipient notification,
- attempts recipient email delivery.

Industry dashboard notification/message widgets also read by `recipient_user_id` and retain compatibility with legacy `company_id` rows.

## 4. Real SMTP email

The local build uses authenticated SMTP by default.

Edit `config.php` locally or provide environment variables:

```php
define('EMAIL_ENABLED', true);
define('EMAIL_TRANSPORT', 'smtp');

define('EMAIL_FROM', 'yourgmail@gmail.com');
define('EMAIL_FROM_NAME', 'SKILLBRIDGE');

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_USERNAME', 'yourgmail@gmail.com');
define('SMTP_PASSWORD', 'YOUR_16_CHARACTER_GOOGLE_APP_PASSWORD');
define('SMTP_HELO_NAME', 'localhost');
```

For Gmail, use a **Google App Password**, not your normal Google account password.

Never commit a real SMTP password to GitHub.

For another SMTP provider, replace host, port and encryption with that provider's settings.

## 5. Test real email

After configuring SMTP, log in and open:

`http://localhost/SkillBridge/email_test.php`

Send a test to your own address.

Then check:

```sql
SELECT id, recipient_email, subject, status, error_message, created_at
FROM email_logs
ORDER BY id DESC
LIMIT 10;
```

`sent` means the SMTP server accepted the message. `failed` includes the recorded reason.

## 6. End-to-end verification

Test in this order:

### Login
Student login -> notification + message + email log.

### Application
Student Apply -> student notification/message/email + industry notification/message.

### Application status
Industry changes status -> student notification/message/email.

### Withdrawal
Student Withdraw -> student + industry notification/message.

### Learning
Join -> progress 25/50/75/100 -> completion events.

### Assessment
Complete assessment -> score notification/message/email.

### Direct message
Open `notifications.php` -> Send a Message -> recipient receives message + notification + email.

### Email
Run `email_test.php` and verify inbox + `email_logs`.

If an activity still does not appear, inspect PHP `error_log` and the newest `email_logs` row; the activity service no longer hides the reason.
