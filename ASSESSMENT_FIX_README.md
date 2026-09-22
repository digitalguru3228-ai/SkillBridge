# SkillBridge Assessment Fix

## Fixed
1. `student/assessment.php` now sends the session CSRF token on both assessment start and answer requests. This fixes the `Invalid security token` error.
2. The adaptive engine now changes difficulty progressively:
   - Correct: Easy -> Medium -> Hard
   - Incorrect: Hard -> Medium -> Easy
3. Industry pre-placement assessments use **only** the questions configured by the industry in `industry_assessment_questions`.
4. Industry assessment questions are stored per attempt in `industry_assessment_responses`.
5. Industry assessments linked to an opportunity are available to students who have applied to that opportunity.
6. Student `My Applications` now shows a `Take Test` button for an active industry assessment linked to the applied opportunity.
7. Industry assessments ask all questions configured for that assessment (in `order_num` order, while adaptive difficulty selects the next available difficulty when possible).
8. Industry assessment results are stored in `industry_assessment_attempts` and compared with the assessment passing score.

## Database
Run:
`database/008_industry_assessment_runtime.sql`

The existing `database/run_migration.php` was also updated to execute migration 008.

## Important workflow
Industry Dashboard -> Assessments -> Create Pre-Placement Assessment -> attach to an Opportunity -> Add Questions.

Student -> My Applications -> Take Test.

The generic SkillBridge adaptive assessment remains available when no industry-specific assessment is selected.
