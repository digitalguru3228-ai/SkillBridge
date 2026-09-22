SKILLBRIDGE — COMMON INDUSTRY + INSTITUTE LOGIN

INSTALL
1. Copy the contents of this package into:
   C:\xampp\htdocs\SKILLBRIDGE\

2. Make sure Apache and MySQL are running in XAMPP.

3. In phpMyAdmin, select industry_hub and run ONLY:
   database/001_create_users.sql

4. Open once:
   http://localhost/SKILLBRIDGE/setup_demo_users.php

5. Test the common login:
   http://localhost/SKILLBRIDGE/

DEMO ACCOUNTS
Industry
Email: admin@techcorp.example
Password: SkillBridge@2026

Institute
Email: placement@vgec.example
Password: SkillBridge@2026

ROUTING
Industry account -> Industry dashboard\
Institute account -> institute_dashboard_php\

IMPORTANT
- Student Portal remains separate.
- Existing Industry and Institute dashboard UI/features are preserved.
- Do NOT rerun the destructive foundation SQL.
- Delete setup_demo_users.php after demo users are created.
- The login uses industry_hub.users; it does not replace the Student Portal database.
