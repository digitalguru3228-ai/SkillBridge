# SKILLBRIDGE Institute Portal — Rebuilt Files

## What was fixed
- Removed fake fallback academic values such as B.Tech, CSE, Semester 7 and CGPA 8.00.
- Student data now comes from `candidates` + `student_profiles` + mapped `candidate_skills`.
- Placement eligibility is calculated server-side from the selected opportunity and the selected institute's students.
- Eligibility checks CGPA, degree/qualification, branch, and required skills using live database records.
- Required skills use normalized `opportunity_skills` when available, otherwise the opportunity's `required_skills` field.
- Placement Readiness now uses verified `candidate_skills.score`, not the old global candidate `match_score`.
- Average Match metric now uses opportunity-specific application match scores.
- Industry Demand student counts are correctly limited to the current institute.
- Hardcoded market qualification/CGPA/domain examples were replaced with live opportunity-derived values.
- Student profile modal now shows real degree, branch, semester, CGPA, career interest and verified skill scores.
- Added `api/eligibility.php` for secure institute-scoped eligibility calculation.
- Improved mobile/tablet sidebar, topbar, cards, filters, tables and profile modal responsiveness.

## Files to replace
Copy these files/folders into your existing:
`C:\xampp\htdocs\SkillBridge\institute_dashboard_php\`

- `index.php`
- `script.js`
- `style.css`
- `api/student.php`
- `api/eligibility.php` (new)
- keep the existing `assets/` and `.htaccess`

No database migration is required for this UI/eligibility rebuild, provided your existing production schema already contains the fields used by the current SkillBridge migrations (especially `student_profiles`, `candidate_skills`, `opportunities`, and `opportunity_skills`).

## Important
This rebuild intentionally does not invent student CGPA/branch/degree values. If a field is missing in the database, the UI shows `Not provided` and eligibility reports the missing requirement instead of assuming a value.

Run Apache/MySQL and refresh the Institute Portal after replacing the files. Test the selected opportunity again from **Placement Coordination & Eligibility**.
