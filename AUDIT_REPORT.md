# SKILLBRIDGE — Comprehensive System Audit Report (SIH 2026 PS-44)

**Project:** SkillBridge: Portal for Academia–Industry Collaboration for Skill Mapping, Internships and Placement  
**Date:** September 19, 2026  
**Environment:** PHP 8.2.12 (XAMPP), MariaDB 10.4.32, Apache 2.4, Vanilla JS / CSS  
**Auditor:** Senior Software Architect & Security Engineer  

---

## 1. Executive Summary

A comprehensive, non-destructive audit of the entire SkillBridge codebase, database schema, security policies, authentication mechanisms, and portal workflows was conducted. 

While substantial foundational work has been done across the Student, Industry, and Institute portals, critical gaps exist in:
1. Assessment results persisting into verified candidate skills (`candidate_skills`),
2. Opportunity-specific explainable skill matching and gap calculation (currently naive string matching),
3. Company and Institute isolation (presence of `ORDER BY id LIMIT 1` and cross-company data leakage),
4. Missing CSRF validation on Industry and Institute POST actions,
5. Fake/mock features (e.g., `alert()` on Faculty application in Institute portal),
6. Broken CSV export due to calls to non-existent `currentUser()` function, and
7. Legacy orphaned duplicate directories (`institute_dashboard_php/Industry dashboard/`).

Below is the detailed classification of every major component and subsystem.

---

## 2. Audit Matrix by Category

| Category | Component / Feature | Current State | Status | Findings / Severity |
| :--- | :--- | :--- | :--- | :--- |
| **P0: Core** | Assessment → Verified Skills | `student/api/assessment_api.php` | **BROKEN** | Assessment attempt & responses are saved, but line 138 does NOT update `candidate_skills` with verified topic scores. Skill profile changes are lost upon page reload. |
| **P0: Core** | Centralized Matching Engine | `includes/skill_mapping.php` | **PARTIAL** | Only checks substring presence in comma-delimited text. Ignores candidate proficiency scores, min required skill score, CGPA, branch, and degree. Lacks explainable scoring formula. |
| **P0: Security** | Company Isolation & IDOR | `Industry dashboard/index.php` | **SECURITY ISSUE** | Lines 91 & 106 use `SELECT id FROM companies ORDER BY id LIMIT 1`. Opportunities and applications queries do not filter by `o.company_id`, leaking all companies' jobs and applications across sessions. |
| **P0: Security** | CSRF Protection | `Industry dashboard`, `institute_dashboard_php` | **SECURITY ISSUE** | All POST actions (creating jobs, updating application status, scheduling workshops, adding questions, deleting resources) lack CSRF token checks. |
| **P0: Security** | Student Application Match Tampering | `student/index.php` | **SECURITY ISSUE** | `match_score` is accepted directly from `$_POST['match_score']` instead of being calculated server-side. |
| **P1: Workflow** | Faculty Opportunity Application | `institute_dashboard_php/index.php` | **BROKEN** | Line 602 triggers `alert('Faculty application submitted...')` with no backend database insertion into `faculty_applications`. |
| **P1: Workflow** | Reassessment & Skill Improvement Loop | `student/` | **PARTIAL** | Reassessments can be taken, but because scores don't update `candidate_skills`, the improvement loop does not propagate to opportunity matching. |
| **P1: Workflow** | Student Profile & Evidence Sync | `student/portfolio.php`, `skill_profile.php` | **PARTIAL** | Certificates and projects uploaded in `profile_edit.php` are not displayed in `portfolio.php`. |
| **P1: Workflow** | Institute Student Progress View | `institute_dashboard_php/api/student.php` | **PARTIAL** | Modal only returns basic candidate data; lacks assessment attempt breakdown, verified scores, learning progress, and placement readiness. |
| **P2: Integration**| CSV Export Engine | `export/export_csv.php` | **BROKEN** | Calls undefined function `currentUser()` on line 8, crashing with a Fatal PHP Error whenever an export is triggered. |
| **P2: Hygiene** | Legacy Duplicate Directory | `institute_dashboard_php/Industry dashboard/` | **DATA CONSISTENCY ISSUE** | An obsolete 56KB copy of an early Industry Dashboard exists inside `institute_dashboard_php/`. It is not referenced anywhere. |
| **P3: UI/UX** | Responsive & Design Consistency | Dashboards (Student, Industry, Institute) | **UX ISSUE** | Inline modals, non-responsive table layouts on mobile viewports (<768px), and inconsistent empty states without action prompts. |

---

## 3. Deep-Dive Findings

### Finding 1: Assessment Scoring Does Not Persist to Verified Skills (P0 - CRITICAL)
- **Location:** `student/api/assessment_api.php:138-140`
- **Impact:** After a student answers 5 questions across topics (Python, SQL, Data Structures, etc.), the test finishes and updates `assessment_attempts.status = 'Completed'`. However, `candidate_skills` is untouched. 
- **Required Fix:**
  1. Add necessary columns to `candidate_skills`: `score DECIMAL(5,2)`, `verified TINYINT(1)`, `source ENUM`, `last_assessed_at TIMESTAMP`.
  2. Add a `UNIQUE KEY (candidate_id, skill_id)` for safe UPSERT logic (`INSERT ... ON DUPLICATE KEY UPDATE`).
  3. In `assessment_api.php`, compute individual topic performance, map to `skills.id`, and execute UPSERT into `candidate_skills`.
  4. Automatically refresh `strengths` (score >= 70%) and `skill_gaps` (score < 70% or missing).

### Finding 2: Lack of Centralized, Explainable Matching Engine (P0 - CRITICAL)
- **Location:** `includes/skill_mapping.php` vs `Industry dashboard/script.js:81`
- **Impact:** Matching is currently fragmented and naive. Student portal uses a simple word search; Industry portal uses another in JavaScript. Neither adheres to the requirement of calculating:
  - Skill Compatibility (50 points)
  - Qualification & Degree Match (15 points)
  - CGPA Match (10 points)
  - Verified Assessment Performance (15 points)
  - Branch Eligibility (10 points)
- **Required Fix:** Create a single authoritative matching function `calculateOpportunityMatchDetails(PDO $pdo, int $candidateId, int $opportunityId)` that returns the transparent formula, matched skills, missing skills, and eligibility breakdown.

### Finding 3: Company and Institute Multi-Tenancy Leakage (P0 - HIGH SECURITY)
- **Location:** `Industry dashboard/index.php:91, 106, 303, 306`
- **Impact:**
  - `SELECT id FROM companies ORDER BY id LIMIT 1` is used in multiple delete/update operations.
  - Opportunities table query: `SELECT o.* FROM opportunities o ...` without `WHERE o.company_id = ?`.
  - Applications table query: `SELECT a.* ... FROM applications a ...` without `WHERE o.company_id = ?`.
- **Required Fix:** Bind all Industry queries and actions strictly to `(int)$_SESSION['company_id']`. Ensure non-empty session checks.

### Finding 4: Missing CSRF Protection & Untrusted Client Inputs (P0/P1 - SECURITY)
- **Location:** `Industry dashboard/index.php`, `institute_dashboard_php/index.php`, `student/index.php:28`
- **Impact:**
  - State-changing POST forms in Industry and Institute dashboards do not contain or verify CSRF tokens.
  - In `student/index.php`, `$_POST['match_score']` is directly accepted into `INSERT INTO applications`.
- **Required Fix:**
  - Embed `<?= csrfToken() ?>` in all POST forms and AJAX endpoints across Industry and Institute dashboards.
  - Compute match score strictly on the backend when students apply.

### Finding 5: Fake Faculty Application Alert (P1 - FUNCTIONAL)
- **Location:** `institute_dashboard_php/index.php:602`
- **Impact:** Clicking "Apply for Faculty Program" executes `alert(...)` with zero database writes.
- **Required Fix:** Implement a real POST action `apply_faculty_opportunity`, record applicant details in `faculty_applications`, and display an application tracker with status badges.

### Finding 6: Fatal Error in CSV Export (P1 - FUNCTIONAL)
- **Location:** `export/export_csv.php:8`
- **Impact:** Calling undefined `currentUser()` results in 500 Fatal Error.
- **Required Fix:** Define `currentUser()` in `auth.php` or retrieve user data via `$_SESSION` and `PDO`, and enforce company/institute scoped CSV exports.

### Finding 7: Unused Duplicate Directory (P2 - CODE QUALITY)
- **Location:** `institute_dashboard_php/Industry dashboard/`
- **Impact:** Stale legacy copy of Industry Dashboard that can confuse future maintenance and git tracking.
- **Required Fix:** Safely remove once confirmed that zero includes or routes reference it.

---

## 4. Priority Plan

1. **Phase 1: Database Hardening (Migration 006)**
   - Add `score`, `verified`, `source`, `last_assessed_at` to `candidate_skills`.
   - Add `UNIQUE KEY (candidate_id, skill_id)` to `candidate_skills`.
   - Ensure foreign key and column integrity across all portals.
2. **Phase 2: Assessment → Verified Skills Persistence (P0)**
   - Complete the loop in `assessment_api.php` and `practice_assessment.php`.
   - Update `strengths` and `skill_gaps` in `student_profiles`.
3. **Phase 3: Centralized Explainable Matching Engine (P0)**
   - Standardize matching algorithm in `includes/skill_mapping.php`.
   - Connect to Student Recommendations and Industry Smart Matching.
4. **Phase 4: Security & Isolation (P0)**
   - Eliminate all `ORDER BY id LIMIT 1` queries.
   - Enforce company and institute data isolation.
   - Inject and verify CSRF tokens on all POST actions.
5. **Phase 5: Fix Broken & Placeholder Features (P1)**
   - Real faculty application insertion into `faculty_applications`.
   - Fix `export/export_csv.php`.
   - Display certificates and projects in `student/portfolio.php`.
   - Enhance Institute student progress modal.
6. **Phase 6: UI/UX & Responsive Polish**
   - Clean professional design system, responsive navigation, and meaningful empty states.
7. **Phase 7: End-to-End & Regression Testing**
   - Execute all 12 test scenarios from user prompt.
