============================================================
SKILLBRIDGE
Academia–Industry Collaboration Platform
SIH 2026 – Problem Statement 44
============================================================

SKILLBRIDGE is an Academia–Industry Collaboration Platform designed
to connect students, academic institutes, and industries through
skill mapping, assessment, internships, jobs, placement readiness,
and industry–institute collaboration.

TECHNOLOGY STACK
------------------------------------------------------------
• PHP
• MySQL
• HTML5
• CSS3
• JavaScript
• XAMPP
• Apache
• MySQL / MariaDB


============================================================
SYSTEM ARCHITECTURE
============================================================

SKILLBRIDGE contains three major access areas:

                    SKILLBRIDGE
                         |
              +----------+----------+
              |                     |
       Common Login           Student Login
              |                     |
        +-----+-----+               |
        |           |               |
     Industry    Institute          |
      Portal       Portal            |
        |           |               |
     Company     Institute          |
     Workspace   Workspace           |
                                    |
                              Student Portal


COMMON LOGIN
------------------------------------------------------------
Industry and Institute users share one common authentication
system.

The system identifies the user's role from the users table and
automatically redirects the user to the correct portal.

Industry account:
    -> Industry Dashboard

Institute account:
    -> Institute Dashboard


STUDENT LOGIN
------------------------------------------------------------
The Student Portal uses a separate authentication system.

Student authentication is NOT merged with the Industry + Institute
common login.

This separation keeps the existing Student Portal authentication
and database functionality independent.


============================================================
INDUSTRY PORTAL
============================================================

Industry accounts represent individual companies.

One Industry account is linked to one company through:

    users.company_id

Therefore, the Industry Portal does NOT require a company/workspace
selector.

Industry users can access the functionality available in the
Industry Portal, including:

• Dashboard
• Jobs
• Internships
• Opportunities
• Smart Skill Matching
• Skill Gap Analysis
• Candidates
• Applications
• Industry Collaboration
• Evaluations
• Notifications
• Messages
• Reports and Analytics


============================================================
INSTITUTE PORTAL
============================================================

Multiple academic institutes can use SKILLBRIDGE.

Each Institute account is linked to a specific institute through:

    users.institute_id

The Institute Portal therefore maintains an Institute Workspace.

Example:

    placement@vgec.example
             |
             v
        institute_id
             |
             v
    VGEC Institute Workspace


Another institute can have its own account:

    placement@daiict.example
             |
             v
        institute_id
             |
             v
    DA-IICT Institute Workspace


Institute data must remain isolated so that an institute user
can access only the students, applications, analytics, reports,
and other records belonging to their own institute.

Institute Portal includes:

• Dashboard
• Students
• Student Profiles
• Skill Intelligence
• Student Progress
• Batch / Department Analytics
• Industry Demand
• Placement Readiness
• Jobs & Internships
• Applications
• Industry Collaboration
• Faculty Dashboard
• Notifications
• Messages
• Reports & Analytics


============================================================
STUDENT PORTAL
============================================================

The Student Portal is a separate part of SKILLBRIDGE.

Student functionality includes the student-facing features
implemented in the existing Student Portal, such as:

• Student Authentication
• Student Dashboard
• Student Profile
• Skills
• Skill Assessment
• Adaptive Assessment
• Skill Gap Identification
• Learning / Skill Development
• Jobs and Internships
• Applications
• Progress
• Notifications


IMPORTANT:

Adaptive Assessment is a Student Portal capability.

The Institute Portal consumes relevant student assessment and
skill information for academic and placement insights rather
than acting as the assessment-taking portal.


============================================================
COMMON AUTHENTICATION
============================================================

The Industry + Institute common login uses:

    industry_hub.users

The users table stores:

    id
    name
    email
    password_hash
    role
    company_id
    institute_id
    status
    created_at


Supported roles:

    industry
    institute
    admin


The login system:

1. Accepts email and password.
2. Finds the account in users.
3. Verifies the password using password_verify().
4. Checks account status.
5. Creates a secure session.
6. Stores the user's role and organization ID.
7. Redirects the user to the correct portal.


============================================================
DATABASE
============================================================

Main database:

    industry_hub


Existing major tables include:

    companies
    institutes
    candidates
    skills
    candidate_skills
    opportunities
    opportunity_skills
    applications
    collaboration_requests
    evaluations
    notifications
    messages
    users


The users table connects authentication accounts to organizations:

Industry:

    users.company_id -> companies.id


Institute:

    users.institute_id -> institutes.id


Student authentication remains separate from this common
Industry + Institute authentication system.


============================================================
INSTALLATION
============================================================

1. REQUIREMENTS
------------------------------------------------------------

Install and run:

• XAMPP
• Apache
• MySQL

Recommended environment:

    Windows
    XAMPP
    PHP 8.x
    MySQL / MariaDB


2. COPY PROJECT
------------------------------------------------------------

Copy the SKILLBRIDGE project into:

    C:\xampp\htdocs\SKILLBRIDGE\

The common login should be accessible from:

    http://localhost/SKILLBRIDGE/


3. START XAMPP
------------------------------------------------------------

Open XAMPP Control Panel and start:

    Apache
    MySQL


4. DATABASE
------------------------------------------------------------

Open phpMyAdmin:

    http://localhost/phpmyadmin/

Select:

    industry_hub


Run ONLY the authentication migration:

    database/001_create_users.sql


IMPORTANT:

DO NOT rerun the original/destructive foundation SQL on an
existing working database.

The foundation SQL contains destructive table operations and
should not be executed again on the live/project database.


5. CREATE DEMO COMMON-LOGIN USERS
------------------------------------------------------------

Open:

    http://localhost/SKILLBRIDGE/setup_demo_users.php

This creates the demo Industry and Institute authentication
accounts using the existing company and institute records.


6. DELETE SETUP FILE
------------------------------------------------------------

After the demo accounts have been created successfully, delete:

    setup_demo_users.php

This prevents the setup script from remaining publicly
accessible.


============================================================
DEMO ACCOUNTS
============================================================

COMMON INDUSTRY LOGIN
------------------------------------------------------------

Email:

    admin@techcorp.example

Password:

    SkillBridge@2026

Role:

    Industry

Redirect:

    Industry Dashboard


COMMON INSTITUTE LOGIN
------------------------------------------------------------

Email:

    placement@vgec.example

Password:

    SkillBridge@2026

Role:

    Institute

Institute:

    Vishwakarma Government Engineering College


STUDENT LOGIN
------------------------------------------------------------

Student authentication is separate from the common
Industry + Institute login.

Existing Student Portal demo credentials:

Email:

    rahul@example.com

Password:

    SkillBridge@2026

IMPORTANT:

The Student account is NOT stored/managed through the
Industry + Institute users authentication flow.


============================================================
ROUTING
============================================================

COMMON LOGIN:

    http://localhost/SKILLBRIDGE/


Industry account:

    Common Login
        |
        v
    Industry Dashboard


Institute account:

    Common Login
        |
        v
    Institute Dashboard


Student account:

    Student Login
        |
        v
    Student Dashboard


============================================================
SECURITY & ACCESS CONTROL
============================================================

SKILLBRIDGE uses role-based authentication and organization-level
access control.

Industry accounts use:

    $_SESSION['company_id']


Institute accounts use:

    $_SESSION['institute_id']


The application should use these session values when querying
organization-specific data.

This prevents users from accessing another company's or
institute's data through direct URL or request manipulation.

Authentication uses:

    password_hash()
    password_verify()
    PHP sessions
    Prepared SQL statements


============================================================
IMPORTANT DEVELOPMENT RULES
============================================================

1. DO NOT rerun destructive foundation SQL.

2. DO NOT merge Student authentication into the common
   Industry + Institute authentication system.

3. DO NOT add an Industry workspace selector.

   One Industry account represents one company.

4. KEEP the Institute Workspace.

   Each Institute account is associated with its own
   institute_id.

5. Institute data must be filtered by the logged-in
   institute_id.

6. Industry data must be filtered by the logged-in
   company_id.

7. Do not replace working Industry or Institute functionality
   unnecessarily.

8. Database changes should be added through separate migration
   SQL files.

9. Do not hardcode dashboard statistics when real database
   data is available.

10. Do not expose database credentials, passwords, API keys,
    or private student information.


============================================================
PROJECT OBJECTIVE
============================================================

SKILLBRIDGE aims to bridge the gap between academia and industry
by connecting:

    Student Skills
          +
    Industry Requirements
          +
    Skill Assessment
          +
    Skill Gap Identification
          +
    Smart Matching
          +
    Jobs & Internships
          +
    Placement Readiness
          +
    Industry–Institute Collaboration


The platform provides a common ecosystem where:

INDUSTRY
    can discover talent, define requirements, publish
    opportunities, evaluate candidates, and collaborate
    with institutes.


INSTITUTES
    can monitor student progress, analyze skills and
    industry demand, support placement readiness, and
    collaborate with industry.


STUDENTS
    can assess their skills, identify skill gaps, develop
    relevant skills, discover opportunities, and apply
    for jobs and internships.


============================================================
SIH 2026
============================================================

Project:

    SKILLBRIDGE

Hackathon:

    Smart India Hackathon 2026

Problem Statement:

    PS-44

Focus:

    Academia–Industry Collaboration for Skill Mapping,
    Internships and Placement


============================================================
PROJECT STATUS
============================================================

Prototype features are being developed and integrated across:

    • Common Authentication
    • Industry Portal
    • Institute Portal
    • Student Portal
    • Skill Mapping
    • Skill Assessment
    • Skill Intelligence
    • Smart Matching
    • Jobs & Internships
    • Applications
    • Placement Readiness
    • Industry–Institute Collaboration
    • Analytics and Reporting


============================================================
END
============================================================
