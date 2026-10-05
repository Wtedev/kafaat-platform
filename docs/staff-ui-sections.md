# هيكل واجهة الموظفين المعتمد

## Users (two tabs: المستفيدين | الموظفين)

- Beneficiaries: table with search and filters (status, profile completeness); identity number hidden. Profile page order: basic info and contact, professional profile (skills, CV, education), registrations (programs, volunteering), certificates. Actions: edit data, activate/deactivate account, download CV, internal notes, reveal identity (button + audit log, as today).
- Staff: table (name, role, status, last login). Invite staff by email: admin sets name, email, role; the staff member sets their own password via a link. Change role. Deactivate instead of delete (history kept).
- Postponed: candidate pool.

## Programs (learning paths postponed)

- List: table and cards (cover + status) with a toggle.
- Create: wizard — basic info, schedule and days, acceptance conditions and method (manual or automatic, set per program), publishing.
- Inside a program, in this order: registrations and acceptance (single and bulk, export), certificates, attendance, grades, broadcasts, prep days and attendance checkers.
- Removed: internal program notes.

## Permissions

- Predefined roles; each staff member has exactly one role.
- Matrix: sections × (view / edit) per role, editable by admin only.
- Sensitive actions (identity reveal, certificate eligibility, exports) are included in the section's "edit" permission, and every use is recorded in the audit log.
- Permissions apply to all programs.
