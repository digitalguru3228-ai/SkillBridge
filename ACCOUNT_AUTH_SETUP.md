# SKILLBRIDGE - Real Account & Authentication Update

This package is based on the attached latest `SkillBridge(3).zip`.

## Included

- Email/password login for Student, Institute and Industry
- Create Account page
- Secure password hashing with `password_hash()`
- Password verification with `password_verify()`
- Session regeneration after authentication
- CSRF protection on login/registration
- Role-based dashboard redirect
- Student account automatically creates a candidate + student profile
- Institute account links to an existing institute
- Industry account links to an existing company
- Google OAuth/OpenID Connect support
- Google identity linking to an existing email account
- Migration `database/009_account_authentication.sql`

## 1. Apply database migration

Start Apache + MySQL in XAMPP.

Open:

http://localhost/SkillBridge/database/run_migration.php

You should see a SUCCESS message.

The migration adds:
- `auth_provider`
- `google_sub`
- `avatar_url`
- `email_verified_at`
- nullable `password_hash`
- Google identity index
- authentication-related indexes

## 2. Test email/password account creation

Open:

http://localhost/SkillBridge/register.php

Create a Student account.

Choose the existing institute if appropriate.

After submission, the account should automatically open:

http://localhost/SkillBridge/student/

The new user is stored in `users`, and the Student also gets a `candidates` record and `student_profiles` record.

You can similarly create Institute and Industry accounts by selecting the existing organization.

## 3. Test login

Open:

http://localhost/SkillBridge/

Sign in using the newly created account.

The role determines the portal:
- student -> `student/`
- institute -> `institute_dashboard_php/`
- industry -> `Industry dashboard/`

## 4. Google Login setup

Google Sign-In is implemented but requires OAuth credentials from your own Google Cloud project.

Set these environment variables in the PHP/Apache environment:

SKILLBRIDGE_GOOGLE_CLIENT_ID
SKILLBRIDGE_GOOGLE_CLIENT_SECRET
SKILLBRIDGE_GOOGLE_REDIRECT_URI

For local XAMPP the redirect URI is:

http://localhost/SkillBridge/google_callback.php

The Google OAuth consent configuration must use that exact redirect URI.

After credentials are configured:
- Login page shows Continue with Google
- Registration page shows Sign up with Google
- New Google accounts are stored in `users`
- Existing accounts with the same verified email are linked instead of duplicated
- Google-only accounts do not need a local password

## 5. Security notes

Do not commit Google client secrets into GitHub.

After deployment, use HTTPS and secure environment variables.

Delete or restrict setup/demo scripts before production deployment.

## Validation

The modified PHP files were syntax-checked successfully before packaging.


### Candidate skills JSON fix
The `candidates.skills` field in the production schema is JSON-validated. New student accounts therefore initialize this field with the valid empty JSON array `[]` rather than an empty string. This is applied to both password registration and Google registration.
