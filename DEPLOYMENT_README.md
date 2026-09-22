# SkillBridge — Production Deployment Package

Prepared from the working SkillBridge project for PHP + MySQL hosting.

## Safety
The original XAMPP project is not modified. This is a separate deployment copy.
Never commit real passwords, API keys, OAuth secrets, SMTP passwords, or `.env`.

Development-only endpoints removed from this deployment copy:
- setup_demo_users.php
- run_migration_003.php
- email_test.php

## Database
Database: `industry_hub`

SQL migrations remain under `database/`. Apply the migrations to a fresh production database in the project's intended order after checking the target database state. Do not rerun destructive migrations blindly.

## Environment variables
Configure these in Railway (not in GitHub):
- SKILLBRIDGE_DB_HOST
- SKILLBRIDGE_DB_PORT
- SKILLBRIDGE_DB_NAME
- SKILLBRIDGE_DB_USER
- SKILLBRIDGE_DB_PASS

Optional Google:
- SKILLBRIDGE_GOOGLE_CLIENT_ID
- SKILLBRIDGE_GOOGLE_CLIENT_SECRET
- SKILLBRIDGE_GOOGLE_REDIRECT_URI

Optional SMTP:
- SKILLBRIDGE_EMAIL_ENABLED
- SKILLBRIDGE_EMAIL_TRANSPORT
- SKILLBRIDGE_EMAIL_FROM
- SKILLBRIDGE_EMAIL_FROM_NAME
- SKILLBRIDGE_SMTP_HOST
- SKILLBRIDGE_SMTP_PORT
- SKILLBRIDGE_SMTP_ENCRYPTION
- SKILLBRIDGE_SMTP_USERNAME
- SKILLBRIDGE_SMTP_PASSWORD
- SKILLBRIDGE_SMTP_HELO_NAME

## Docker
Dockerfile uses PHP 8.2 + Apache with PDO MySQL, cURL and ZIP.

## Deployment
1. Put this package in a private GitHub repository.
2. Create a Railway project and add MySQL.
3. Import the database schema/data into Railway MySQL.
4. Deploy this repository; Railway uses the included Dockerfile.
5. Add environment variables.
6. Test `/health.php`, then test Student, Industry and Institute login.
7. Test assessment, matching, opportunities, applications, learning, uploads and reports.
