# Placement Coordination & Eligibility Fix

The Institute Portal Placement Coordination & Eligibility view now compares each institute student against the selected industry's opportunity requirements using live database records.

Checks:
- Qualification family (B.E./B.Tech etc.) is matched independently from branch/specialization.
- Branch aliases such as IT, CSE, CE, AI/ML and full names are normalized.
- If a student's branch field is empty, a recognizable branch in the degree text can be used.
- Minimum CGPA is enforced only when the opportunity specifies a value greater than zero.
- Required skills are matched with common aliases such as NodeJS/Node.js, ReactJS/React and JS/JavaScript.
- Exact missing/mismatched requirements are returned for every non-eligible student.
- Student data is restricted to the logged-in institute.
- The UI shows the selected opportunity's actual eligibility rules before the candidate lists.
- "0" CGPA is displayed as "No minimum specified" rather than as a real requirement.

No new database migration is required for this eligibility calculation.
