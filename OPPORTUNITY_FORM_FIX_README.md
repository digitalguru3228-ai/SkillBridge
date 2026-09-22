# Industry Job & Internship Posting Form Fix

Updated the Industry Dashboard opportunity posting workflow without rewriting the existing portal.

## Fixed
- Removed duplicate HTML `id="opportunityForm"` that was assigned to a hidden CSRF input and the form. This was breaking `form.reset()` and could make the posting modal behave incorrectly.
- Added structured fields already supported by the database: work mode, minimum CGPA, allowed branches/degrees, learning outcomes, and linked pre-placement assessment.
- Added separate required fields for jobs vs internships.
- Added server-side validation for required fields, openings, CGPA, work mode, active deadline, and opportunity type.
- Added client-side required-field behavior when switching between Job and Internship.
- Added application deadline minimum date for the form.
- Added safe company ownership checks for edit operations.
- Kept skill synchronization with `opportunity_skills`.
- When a pre-placement assessment is linked from the opportunity form, its `opportunity_id` is synchronized; previous assessment links for that opportunity are cleared.
- Improved modal layout and responsive styling.
- Preserved the existing assessment fixes in this project package.

## Database
The existing Migration 005 already adds the structured opportunity columns used by this form, and Migration 008 remains included for industry assessment response storage.

Run the normal project migration runner before testing on a database that has not yet received the migrations.
