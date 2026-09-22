# SKILLBRIDGE — Final Manual Integration Pack

This build is based on the supplied `SkillBridge.zip` source. It keeps the existing PHP/MySQL architecture and applies targeted integration fixes instead of rewriting the portal.

## Completed in this build

### Student → Skill Intelligence
- Assessment answers are protected against duplicate submission in the same attempt.
- Assessment writes are transactional.
- Completed assessments persist topic scores into `candidate_skills`.
- Verified skill metadata is updated (`verified=1`, `source='Assessment'`).
- Student opportunity recommendations use the same opportunity-specific matching engine used by Industry Smart Matching.
- Student applications calculate the match score server-side.
- Existing application withdrawal remains ownership-protected.

### Industry
- Company ownership is enforced for opportunity, assessment, collaboration, resource and application actions already present in the project.
- Assessment question deletion is ownership-protected.
- Industry notifications are scoped to the logged-in company.
- Smart Matching uses the server-side explainable matching API.
- Candidate cards no longer display stale global `candidates.match_score` as if it were an opportunity match.
- Shortlist POST now includes CSRF.
- Broken hidden-input markup in the Industry dashboard was corrected.
- Industry resource audience values now match the database ENUM.

### Institute
- All institute POST actions use the shared CSRF token.
- Faculty suggestions validate that the selected student belongs to the current institute.
- Suggestion current score is derived from the student's verified skills.
- Industry resource acknowledgement records the current user.
- Faculty opportunities now have a real database-backed Apply workflow.
- Faculty application status is displayed after submission.
- Faculty applications are linked to institute ownership.

### Database
- Added `database/007_final_sih_integration.sql`.
- It safely adds/backfills `faculty_applications.institute_id`.
- It adds the institute index.
- It adds a duplicate-prevention unique key only when no existing duplicates are present, so it does not delete data.

## Required migration order

Run the existing migrations in order:

1. 001
2. 002
3. 003
4. 004
5. 005
6. 006
7. 007

Do not drop or truncate production/demo data.

## Important testing

This package was syntax-checked with PHP and JavaScript parsers. A live Apache/MySQL end-to-end test cannot be performed in this environment.

After copying the project to XAMPP, test this exact flow:

Student:
1. Login
2. Complete/update profile
3. Start assessment
4. Answer all questions
5. Confirm assessment becomes Completed
6. Confirm `candidate_skills` receives verified scores
7. Open Skill Profile
8. Open Job & Internship Matches
9. Apply to an opportunity
10. Withdraw an eligible application

Industry:
1. Login as company A
2. Open Smart Matching
3. Select an opportunity
4. Confirm scores are calculated per opportunity
5. Inspect Skill Gap Analyzer
6. Shortlist a candidate
7. Open Applications and change status
8. Create an assessment/question
9. Create a learning program/resource

Institute:
1. Login as institute A
2. Confirm only its students appear
3. Open Student Progress
4. Create a faculty suggestion
5. Open Industry Resources and acknowledge one
6. Open Faculty Dashboard
7. Apply to a faculty opportunity
8. Refresh and confirm the application status remains

Security:
- Test direct access to another company/institute record.
- Test duplicate assessment answer.
- Test invalid CSRF.
- Test another student's application ID.
- Test another company's opportunity ID.
