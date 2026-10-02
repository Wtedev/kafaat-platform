# Staff / admin UI inventory

Inventory of the staff and admin interface as loaded in this working tree (`fix/program-card-disabled-ux`, including uncommitted files). Generated from `php artisan route:list --path=admin` plus the Filament resources, pages, policies, and Blade views those routes render.

Filament pages are Livewire screens. They do not have a classic `Controller@method`. The route action is the page class. Mutations (approve, export, delete, and so on) are Livewire actions on that same GET URL, not separate named routes.

`TrainingProgramEditorsRelationManager` exists on disk and is not registered in `TrainingProgramResource::getRelations()`.

## Shared panel shell

Every Filament URL under `/admin` except login uses this middleware, in order:

`SetUpPanel:admin`, `EncryptCookies`, `AddQueuedCookiesToResponse`, `StartSession`, `ShareErrorsFromSession`, `EnsureOperationalAccount`, `Authenticate`, `AuthenticateSession`, `PreventRequestForgery`, `SubstituteBindings`, `DisableBladeIconComponents`, `DispatchServingFilamentEvent`, `EnsureOtpVerified`

Login (`GET /admin/login`) uses the same stack without `Authenticate` and without `EnsureOtpVerified`.

Guard: `web`. Panel id: `admin`. Locale forced to `ar`. Default theme: dark. SPA mode: off.

Unless a row names a project Blade file, the page is rendered by Filament’s panel layout (`x-filament-panels::page` / resource pages), not a project view.

Navigation access: if a resource’s `requiredNavigationPermissions()` returns a non-empty list, `canViewAny()` requires every permission in that list and does not also call the policy. Record actions still go through policies.

## Shared layouts, partials, and components

| Piece | Where | Used by |
| --- | --- | --- |
| Filament panel layout | vendor `filament-panels` | Every `/admin` page |
| `filament.components.admin-main-site-button` | render hook `GLOBAL_SEARCH_BEFORE` | All admin pages |
| `filament.components.admin-notifications-bell` | render hook `USER_MENU_BEFORE` | All admin pages |
| `partials.notification-prefs-modal` | render hook `BODY_END` when `notification_prefs_set_at` is null | All admin pages, once |
| `public/css/shamel-fonts.css`, `public/css/filament-admin-surface.css` | `HEAD_END` | All admin pages |
| `public/js/filament/training-schedule-calendar.js`, `public/js/filament/news-content-editor.js` | `SCRIPTS_AFTER` | All admin pages |
| `filament.components.admin-sidebar-staff-profile` | sidebar | Panel chrome |
| `filament.components.entity-view-panel` | entity view pages | Programs, paths, and similar view screens |
| `filament.components.entity-image-preview` | forms | Image fields |
| `filament.components.settings-changes-summary` | settings tab | Training entity settings |
| `filament.components.user-technical-log-timeline` | user view | Technical log relation |
| `filament.components.attendance-live-session-panel` | attendance relation managers | Program and path attendance |
| `filament.forms.components.training-schedule-calendar` | schedule field | Program / path forms |
| `filament.program-broadcasts.preview` | broadcast relation | Program broadcasts |
| `filament.news.edit.image-preview`, `filament.news.edit.dates-card` | news edit | News |
| `filament.pages.support-inbox` | `SupportInbox` | Support inbox |
| `filament.pages.error-page-stats` | `ErrorPageStatsPage` | Error stats |
| `filament.pages.staff-permission-matrix` | `StaffPermissionMatrix` | Permission matrix |
| `filament.resources.training-program-resource.pages.manage-certificate-design` | certificate designer | Program, path, and volunteer certificate design (path and volunteer pages reuse this view) |
| `filament.resources.training-program-resource.relation-managers.certificate-status-bar` | certificates relation | Program, path, and volunteer certificates |
| `layouts.gate` | gate portal | Prep-officer portal, login, scan |
| `gate.partials.live-session`, `gate.partials.manual-list`, `gate.pagination` | gate portal | Prep portal |

Dashboard widgets (not their own routes): `PlatformStatsWidget` (permission `statistics.view`), `LatestInAppNotificationsWidget` (link to the notification center).

---

## Shell and account

| Method | URI | Route name | Page | View | Permission |
| --- | --- | --- | --- | --- | --- |
| GET | `/admin` | `filament.admin.pages.dashboard` | `Filament\Pages\Dashboard` | Filament dashboard | Any user who can open the panel |
| GET | `/admin/login` | `filament.admin.auth.login` | `App\Filament\Pages\Auth\Login` | Filament login | Guest |
| POST | `/admin/logout` | `filament.admin.auth.logout` | `Filament\Auth\Http\Controllers\LogoutController` | redirect | Authenticated panel user |
| GET | `/admin/profile` | `filament.admin.pages.profile` | `StaffProfilePage` | Filament page | `canAccessFilamentAdmin()` |

**Dashboard actions:** stats cards (users, pending registrations, certificates this month) when `statistics.view` is held; latest in-app notifications with “عرض جميع التنبيهات”.

**Profile actions:** request email-change code, resend code, verify code, cancel email change, change password, save profile.

**Login logic outside a service:** `Login::getCredentialsFromFormData()` lowercases the email through `EmailNormalizer` before authentication.

## Users and trainees

Navigation group: المستخدمون / إدارة الوصول / المستفيدون.

| Method | URI | Route name | Page | Permission to open |
| --- | --- | --- | --- | --- |
| GET | `/admin/users` | `filament.admin.resources.users.index` | `ListUsers` | `users.view` |
| GET | `/admin/users/create` | `…users.create` | `CreateUser` | `users.view` plus policy `create` |
| GET | `/admin/users/{record}` | `…users.view` | `ViewUser` | `users.view`; record policy `view` |
| GET | `/admin/users/{record}/edit` | `…users.edit` | `EditUser` | `users.view`; record policy `update` |
| GET | `/admin/profiles` | `…profiles.index` | `ListProfiles` | Policy: `roles.view` or `edit_profile_badges` |
| GET | `/admin/profiles/create` | `…profiles.create` | `CreateProfile` | Policy `create` (`roles.view`) |
| GET | `/admin/profiles/{record}/edit` | `…profiles.edit` | `EditProfile` | Policy `update` |
| GET | `/admin/candidate-pool-members` | `…candidate-pool-members.index` | `ListCandidatePoolMembers` | Candidate-pool policy / parent `canViewAny` (no extra navigation permission list) |
| GET | `/admin/beneficiaries/{user}/cv-pdf` | `admin.beneficiaries.cv-pdf` | `BeneficiaryCvPdfController` | Policy `downloadCv` (`beneficiary.cv.download` or `candidate_pool.cv.download`). Middleware: `web`, `EnsureOperationalAccount`, `Authenticate`, `EnsureOtpVerified`. No Blade; PDF stream |
| GET | `/admin/beneficiaries/{user}/cv/download` | `admin.beneficiaries.cv-file.download` | `BeneficiaryCvFileDownloadController` | Same auth stack; file download |
| POST | `/admin/beneficiaries/{user}/identity/reveal` | `admin.beneficiaries.identity.reveal` | `BeneficiaryIdentityRevealController` | Same auth stack plus `throttle:10,1`. JSON, no Blade |

**Users — list:** search and columns (name, identity type, identity number, profile completeness, email, platform role, mobile, active, email notifications, last login, created). Filters: active, email notifications. Header: create. Row: view, edit. Action `manage_volunteer_team` (إدارة عضوية الفريق).

**Users — view tabs / relations:** training registrations, volunteer registrations, technical log timeline, notes. Settings tab gated by `canAccessSettingsTab()`.

**Users — create / edit:** name, email, password, mobile, active, email notifications, role. Sensitive fields follow `UserPolicy` (`beneficiaries.update_basic`, `beneficiaries.update_sensitive`, `users.update`, `permissions.assign` / `manage_roles` for role changes).

**Profiles — list:** columns for user, email, badges, gender, city, membership type, skill, birth date, created. Filters: gender, membership type. Bulk delete. Header export `exportBeneficiaryProfiles` (تصدير Excel), permission `exports.beneficiaries.basic`.

**Candidate pool — list only:** view profile, download CV. No create/edit/delete routes.

**Identity and CV logic outside a service:**

- `BeneficiaryCvPdfController` authorizes `downloadCv`, aborts unless `isPortalUser()`, builds the filename in the controller, then streams via `CompetencyMpdfExporter`.
- `BeneficiaryIdentityRevealController` rate-limits (5 attempts / 300 seconds), writes audit rows on denial, and reveals the identity number. The check is in the controller, not only in a policy method on the route.

## Training

Navigation group: التدريب.

| Method | URI | Route name | Page | Permission to open |
| --- | --- | --- | --- | --- |
| GET | `/admin/training-programs` | `…training-programs.index` | `ListTrainingPrograms` | `programs.view` |
| GET | `/admin/training-programs/create` | `…create` | `CreateTrainingProgram` | `programs.view` plus `TrainingProgramPolicy::create` |
| GET | `/admin/training-programs/{record}` | `…view` | `ViewTrainingProgram` | `programs.view`; policy `view` |
| GET | `/admin/training-programs/{record}/edit` | `…edit` | `EditTrainingProgram` | policy `update` |
| GET | `/admin/training-programs/{record}/certificate-design` | `…certificate-design` | `ManageCertificateDesign` | `ManageCertificateDesign::canAccess` |
| GET | `/admin/learning-paths` | `…learning-paths.index` | `ListLearningPaths` | `paths.view` |
| GET | `/admin/learning-paths/create` | `…create` | `CreateLearningPath` | `paths.view` plus policy `create` |
| GET | `/admin/learning-paths/{record}` | `…view` | `ViewLearningPath` | policy `view` |
| GET | `/admin/learning-paths/{record}/edit` | `…edit` | `EditLearningPath` | policy `update` |
| GET | `/admin/learning-paths/{record}/certificate-design` | `…certificate-design` | `ManagePathCertificateDesign` | design-page `canAccess` |
| GET | `/admin/program-registrations` | `…program-registrations.index` | `ListProgramRegistrations` | Navigation requires `roles.view` (trait). Policy `viewAny` is `registrations.view` |
| GET | `/admin/program-registrations/{record}` | `…view` | `ViewProgramRegistration` | same |
| GET | `/admin/path-registrations` | `…path-registrations.index` | `ListPathRegistrations` | Navigation requires `roles.view`. Policy `viewAny` is `registrations.view` |
| GET | `/admin/path-registrations/{record}` | `…view` | `ViewPathRegistration` | same |

**Programs — list:** create (إضافة برنامج), delete, search, status and kind filters, inline field edit (`editEntityField`), publish now (`publishEntityNow`). Ownership transfer (`transferOwnership`). Export registrants (`exportProgramRegistrants`) from the view.

**Programs — view relations:**

- Registrations: approve (قبول), reject (رفض).
- Broadcasts: create draft, delete draft, view, edit draft, send now, retry failed, copy to a new draft. Preview Blade: `filament.program-broadcasts.preview`.
- Attendance: daily mode, matrix of all days, pick prep day, open QR scan, start live session.
- Prep days: add day.
- Attendance checkers: add checker, reveal access link, regenerate link, toggle active.
- Grades: enter grade, enter full grade for every registrant.
- Certificates: open designer, issue eligible, export ZIP, export Excel roster, email certificates, issue one, exceptional issue, download, open verify link, regenerate, revoke, bulk issue / ZIP / email. Status bar Blade listed above.
- Notes: add note.

**Programs — edit:** full program form (kind, schedule calendar, acceptance conditions, publication). Settings tab: `saveSettings`, gated by `canAccessSettingsTab()`.

**Paths — list:** create (إضافة مسار), delete (only if `can('delete', $record)`).

**Paths — view relations:** programs in the path (create program in path, attach existing, open program settings, detach), path registrations (approve, reject), attendance (start 5-minute live session, manual attendance), grades (same grade actions as programs), certificates (same certificate relation as programs), editors (add editor, remove editor), notes.

**Program registrations — list and view:** filters on status (and related columns). Actions: approve, reject, update attendance and score, mark completed, issue certificate, delete (bulk delete on the table). Attendance relation: mark present, mark absent.

**Path registrations — list and view:** filters on status and path. Actions: approve, reject, complete path, bulk delete.

**Logic outside a Model or Service:**

- `PlatformStatsWidget::getStats()` counts pending path, program, and volunteer registrations and certificates issued this month directly on the models.
- `ViewTrainingProgram` / `ViewLearningPath` settings-tab visibility is computed on the page (`canAccessSettingsTab()`), not in a policy method named for that tab.
- Navigation for program and path registrations checks `roles.view` instead of `registrations.view`. The policy still uses `registrations.view` for record authorization. A rebuild that only copies the policy will hide or show the menu differently from today.

## Certificates

Navigation group: الشهادات. Designer pages live under programs, paths, and volunteer opportunities.

| Method | URI | Route name | Page | Permission to open |
| --- | --- | --- | --- | --- |
| GET | `/admin/certificates` | `…certificates.index` | `ListCertificates` | `certificates.view` and `roles.view` |
| GET | `/admin/certificates/{record}` | `…certificates.view` | `ViewCertificate` | same, plus policy `view` |

**List / view actions:** verify link (رابط التحقق), download PDF. No create or delete route. Issuing, revoking, regenerating, ZIP, Excel, and email are on the program, path, and volunteer certificate relation managers, not on this resource.

Policy `certificates.issue` gates issuing. `CertificateTemplatePolicy` delegates update to the owning program, path, or opportunity.

## Volunteering

Navigation group: التطوع.

| Method | URI | Route name | Page | Permission to open |
| --- | --- | --- | --- | --- |
| GET | `/admin/volunteer-opportunities` | `…index` | `ListVolunteerOpportunities` | `volunteering.view` |
| GET | `/admin/volunteer-opportunities/create` | `…create` | `CreateVolunteerOpportunity` | policy `volunteering.create` |
| GET | `/admin/volunteer-opportunities/{record}` | `…view` | `ViewVolunteerOpportunity` | policy `view` |
| GET | `/admin/volunteer-opportunities/{record}/edit` | `…edit` | `EditVolunteerOpportunity` | policy `update` |
| GET | `/admin/volunteer-opportunities/{record}/certificate-design` | `…certificate-design` | `ManageVolunteerCertificateDesign` | design-page `canAccess` |
| GET | `/admin/volunteer-registrations` | `…index` | `ListVolunteerRegistrations` | `registrations.view` and `volunteering.view` |
| GET | `/admin/volunteer-registrations/{record}` | `…view` | `ViewVolunteerRegistration` | same |
| GET | `/admin/volunteer-hours` | `…index` | `ListVolunteerHours` | Navigation `roles.view`. Policy `volunteer_hours.view` |
| GET | `/admin/volunteer-hours/{record}` | `…view` | `ViewVolunteerHour` | same |
| GET | `/admin/volunteer-teams` | `…index` | `ListVolunteerTeams` | `volunteering.view` |
| GET | `/admin/volunteer-teams/create` | `…create` | `CreateVolunteerTeam` | policy `volunteering.create`, further limited in `canCreate()` |
| GET | `/admin/volunteer-teams/{record}` | `…view` | `ViewVolunteerTeam` | policy `view` |
| GET | `/admin/volunteer-teams/{record}/edit` | `…edit` | `EditVolunteerTeam` | policy `update` |

**Opportunities — list:** search, status filter, delete, bulk delete, create. View cover image and dates.

**Opportunities — view relations:** registrations (approve, reject, issue volunteer certificate), hours (approve hours, reject hours), certificates (same certificate actions as programs).

**Volunteer registrations:** filters on status and opportunity. Actions: approve, reject, issue certificate, bulk delete.

**Volunteer hours:** approve hours, reject hours. Policy create is `volunteer_hours.create`. Navigation permission does not match that policy (see flag below).

**Teams:** list is scoped with `forFilamentAssignmentAccess(auth()->user())` inside `getEloquentQuery()`. Relations: members, team notifications. Create is not only the policy; `canCreate()` adds another check.

**Logic outside a Model or Service:** `VolunteerTeamResource::getEloquentQuery()` applies the assignment scope in the resource. `VolunteerHourResource` opens the menu with `roles.view` while the policy uses `volunteer_hours.view`.

## Content

Navigation group: المحتوى.

| Method | URI | Route name | Page | Permission to open |
| --- | --- | --- | --- | --- |
| GET | `/admin/news` | `…news.index` | `ListNews` | `view_news` or `manage_news` |
| GET | `/admin/news/create` | `…create` | `CreateNews` | `manage_news` |
| GET | `/admin/news/{record}` | `…view` | `ViewNews` | `view_news` or `manage_news` |
| GET | `/admin/news/{record}/edit` | `…edit` | `EditNews` | `manage_news` |
| GET | `/admin/partners` | `…partners.index` | `ListPartners` | `manage_partners` |
| GET | `/admin/partners/create` | `…create` | `CreatePartner` | `manage_partners` |
| GET | `/admin/partners/{record}` | `…view` | `ViewPartner` | `manage_partners` |
| GET | `/admin/partners/{record}/edit` | `…edit` | `EditPartner` | `manage_partners` |
| GET | `/admin/media-photos` | `…media-photos.index` | `ListMediaPhotos` | `manage_media` |
| GET | `/admin/media-photos/create` | `…create` | `CreateMediaPhoto` | `manage_media` |
| GET | `/admin/media-photos/{record}/edit` | `…edit` | `EditMediaPhoto` | `manage_media` |
| GET | `/admin/regulations` | `…regulations.index` | `ListRegulations` | `manage_regulations` |
| GET | `/admin/regulations/create` | `…create` | `CreateRegulation` | `manage_regulations` |
| GET | `/admin/regulations/{record}/edit` | `…edit` | `EditRegulation` | `manage_regulations` |

**News:** list create (إضافة خبر جديد). Create page: publish now, schedule. Edit: preview public page, delete, full content editor, featured image, publish now, schedule, move to draft. Custom Blades: `filament.news.edit.image-preview`, `filament.news.edit.dates-card`.

**Partners:** add, view, edit, delete.

**Media photos:** add, edit, delete, bulk delete. Columns: title, section, album, order, published. No view route.

**Regulations:** add, edit, delete. No view route.

## Governance and privacy

Navigation group: الحوكمة. All of these use the model policy. There is no extra `requiredNavigationPermissions()` list.

| Resource | Routes | Permission | Actions beyond CRUD |
| --- | --- | --- | --- |
| Board members | index, create, edit | `manage_governance` | add member, edit, delete, bulk delete |
| General assembly members | index, create, edit | `manage_governance` | add, edit, delete |
| Governance committees | index, create, edit | `manage_governance` | members relation (add / edit / delete members) |
| Governance documents | index, create, edit | `manage_governance` | add document |
| Investment decision years | index, create, edit | `manage_governance` | items relation; published flag, PDF attachment, empty-state message |
| Privacy policy versions | index, create, view, edit | `privacy_policy.view`; create `privacy_policy.create`; update/delete draft `privacy_policy.update_draft`; publish `privacy_policy.publish`; archive `privacy_policy.archive` | publish, clone as new draft, acknowledgements relation |
| Candidate-pool consent versions | index, create, view, edit | same privacy-policy style permissions on that model | publish, clone draft, status filter |
| Privacy requests | index, view | `privacy_requests.view` | assign to me, start review, approve, partial approve, reject, complete access, generate export, retry export, apply correction, create deletion plan, approve deletion plan, execute deletion |
| Retention policies | index, create, edit | `retention_policies.view`; create/update `retention_policies.create` / `update_draft` / `manage` | preview, activate |
| Retention exceptions | index, create | `retention_exceptions.manage` | revoke. No edit route |
| Retention runs | index only | `retention_runs.view` | read-only list |

## Access control

Navigation group: إدارة الوصول / المستخدمون.

| Method | URI | Route name | Page | Permission |
| --- | --- | --- | --- | --- |
| GET | `/admin/roles` | `…roles.index` | `ListRoles` | `roles.view`. `canCreate`, `canEdit`, and `canDelete` are hard-coded `false` |
| GET | `/admin/permissions` | `…permissions.index` | `ListPermissions` | `roles.view`. Create, edit, and delete hard-coded `false` |
| GET | `/admin/staff-permissions` | `filament.admin.pages.staff-permissions` | `StaffPermissionMatrix` | `isAdmin()` only. View: `filament.pages.staff-permission-matrix` |

**Matrix actions:** edit the staff permission grid in that Blade. No Filament resource CRUD.

**Logic outside a Model or Service:** role and permission resources refuse writes in the resource class itself, not only in a policy.

## Support, notifications, and security

Navigation group: الأمان والامتثال, plus notification pages.

| Method | URI | Route name | Page | View | Permission |
| --- | --- | --- | --- | --- | --- |
| GET | `/admin/support-inbox` | `filament.admin.pages.support-inbox` | `SupportInbox` | `filament.pages.support-inbox` | `isAdmin()` or `support_tickets.view` or `support_tickets.reply` |
| GET | `/admin/support-tickets` | `…support-tickets.index` | `ListSupportTickets` | Filament table | `SupportTicketResource::canAccess()` (admin or support permissions) |
| GET | `/admin/support-tickets/{record}` | `…view` | `ViewSupportTicket` | Filament view | policy `view`; reply permissions on actions |
| GET | `/admin/in-app-notification-center` | `filament.admin.pages.in-app-notification-center` | `InAppNotificationCenter` | Filament page | `view_notifications` |
| GET | `/admin/send-in-app-notification` | `filament.admin.pages.send-in-app-notification` | `SendInAppNotification` | Filament page | `Gate::allows('accessSendInAppNotificationPage')` (`send_notifications` or volunteer team leader). Hidden from the sidebar |
| GET | `/admin/audit-logs` | `…audit-logs.index` | `ListAuditLogs` | Filament table | `audit_logs.view`. Index only |
| GET | `/admin/security-logs` | `…security-logs.index` | `ListSecurityLogs` | Filament table | `security_logs.view`. Sensitive metadata: `security_logs.view_sensitive_metadata`. Index only |
| GET | `/admin/error-page-stats` | `filament.admin.pages.error-page-stats` | `ErrorPageStatsPage` | `filament.pages.error-page-stats` | `canAccessFilamentAdmin()` |

**Support inbox:** filter tabs (all, unread, open, in progress, closed), search, select a ticket (`?selected=`), reply, internal note, close with reason, mail-status indicator. Queries and tab counts live on `SupportInbox`, which also calls `SupportTicketService` and `SupportUnreadService`.

**Support tickets table:** open conversation. Status changes need `support_tickets.manage_status` or admin. Assign needs `support_tickets.assign` or admin. Internal notes need `support_tickets.internal_notes` or admin. Delete is admin-only in the policy.

**Notification center:** send alert, mark selected read. Widget on the dashboard links here. Inbox row actions (from `InboxNotificationRecordActions`): open the related record, approve or reject a program / path / volunteer registration, mark read, mark unread, view details.

**Send notification:** send action. Not in the sidebar.

**Audit and security logs:** list, search, filters. No create, edit, or delete.

**Error stats:** date, status, and URL filters; refresh; prune old rows. The Blade computes the daily-chart max and the Arabic status labels (`403` through `505`). The page class queries `ErrorPageVisit` and calls `ErrorPageVisitRecorder` for prune.

## Prep-officer gate (staff, not under `/admin`)

These routes are the attendance gate, not the Filament panel. Layout: `layouts.gate`.

| Method | URI | Route name | Controller | Middleware | View |
| --- | --- | --- | --- | --- | --- |
| GET | `/gate/{program:slug}` | `gate.login` | `GateAttendanceController@login` | web | `gate.login` |
| GET | `/gate/{program:slug}/access/{token}` | `gate.access` | `GateAttendanceController@access` | `throttle:20,1`, token must be 64 hex chars | redirect into the portal |
| GET | `/gate/{program:slug}/portal` | `gate.portal` | `GateAttendanceController@portal` | `gate.attendance` (`EnsureGateAttendanceAccess`) | `gate.portal` |
| GET | `/gate/{program:slug}/scan` | `gate.scan` | `GateAttendanceController@scan` | `gate.attendance` | `gate.scan` |
| POST | `/gate/{program:slug}/scan` | `gate.scan.store` | `GateAttendanceController@mark` | `gate.attendance`, `throttle:60,1` | JSON / redirect |
| POST | `/gate/{program:slug}/registrations/{registration}/attendance` | `gate.attendance.toggle` | `GateAttendanceController@toggleAttendance` | `gate.attendance`, `throttle:60,1` | JSON |
| GET | `/gate/{program:slug}/live-session` | `gate.live-session.status` | `GateAttendanceController@liveSessionStatus` | `gate.attendance`, `throttle:120,1` | JSON |
| POST | `/gate/{program:slug}/live-session/start` | `gate.live-session.start` | `GateAttendanceController@startLiveSession` | `gate.attendance`, `throttle:30,1` | JSON |
| POST | `/gate/{program:slug}/live-session/end` | `gate.live-session.end` | `GateAttendanceController@endLiveSession` | `gate.attendance`, `throttle:30,1` | JSON |
| POST | `/gate/{program:slug}/logout` | `gate.logout` | `GateAttendanceController@logout` | `gate.attendance` | redirect |

**Portal actions:** manual attendance list (`gate.partials.manual-list`), live session panel (`gate.partials.live-session`), pagination, QR scan, start and end the live session, logout. Access is the signed checker link, not a Spatie permission on the route.

## Business logic that is not in a Model or Service

Each item below is a calculation, query, or authorization decision that lives in a controller, Filament page, resource, or Blade.

| Location | What it does |
| --- | --- |
| `PlatformStatsWidget::getStats()` | Counts users, pending registrations (three tables), and certificates issued this calendar month |
| `ErrorPageStatsPage` | Filters and aggregates `ErrorPageVisit` on the page |
| `error-page-stats.blade.php` | Computes the chart maximum and maps HTTP status codes to Arabic labels |
| `SupportInbox` | Builds the ticket query, unread/open/closed tabs, and reply/note/close state on the page (services are used for send and unread, not for the whole screen) |
| `Login::getCredentialsFromFormData()` | Normalizes the email before the auth attempt |
| `BeneficiaryCvPdfController` | Portal-user check and PDF filename sanitizing |
| `BeneficiaryIdentityRevealController` | Rate limit and denial audit before the identity service runs |
| `VolunteerTeamResource::getEloquentQuery()` | Restricts teams with `forFilamentAssignmentAccess` |
| `VolunteerTeamResource::canCreate()` | Extra create gate beyond the policy |
| `RoleResource` / `PermissionResource` | Create, edit, and delete forced off |
| `ProgramRegistrationResource` / `PathRegistrationResource` / `VolunteerHourResource` / `CertificateResource` | Menu permission (`roles.view`, and for certificates also `certificates.view`) differs from the model policy |
| `RegistersNavigationByPermission` | Non-empty permission list replaces `canViewAny()` and skips the policy |
| `ViewTrainingProgram`, `ViewLearningPath`, `ViewUser` | Settings tab visibility computed on the page |
| `HasTrainingEntityPublicationActions` / inline edit concerns | Publish-now and inline field saves are wired in Filament concerns |
| `InboxNotificationRecordActions` | Approve and reject registrations from the notification center |
| `TrainingProgramResource::resolveTrainingProgramImagePublicUrl()` | Image URL helper on the resource |
| `UserResource::beneficiaryCvPdfUrl()` | Builds the CV route on the resource |
| `gate/portal.blade.php` and partials | Attendance UI state (present, live session, list) rendered in the Blade; marking itself is the controller |

Filament table `formatStateUsing()` closures that only call `Enum::label()` are display formatting, not a separate business rule.

## Verification checklist

Use this against the rebuilt staff UI. Check the box only when the action works for a user who has the permission and fails for a user who does not.

### Shell

- [ ] `/admin` loads in Arabic, dark theme, with the main-site button and the notifications bell
- [ ] Login normalizes email case; logout returns to the login screen
- [ ] OTP and operational-account middleware still wrap every panel page
- [ ] First-login notification-preferences modal still appears once
- [ ] `/admin/profile` saves profile, password, and email change (request, resend, verify, cancel)
- [ ] Dashboard stats show only with `statistics.view`
- [ ] Dashboard notification widget opens the notification center

### Users and trainees

- [ ] User list, create, view, and edit honor `users.view` and the beneficiary update permissions
- [ ] Role changes require `permissions.assign` or `manage_roles`
- [ ] Filters for active and email notifications still work
- [ ] Volunteer-team membership action is still available from the user list
- [ ] User view still has training registrations, volunteer registrations, technical log, and notes
- [ ] Profile list, create, edit, gender and membership filters, bulk delete
- [ ] Profile Excel export requires `exports.beneficiaries.basic`
- [ ] Candidate pool list: view profile and download CV, with no create or edit
- [ ] CV PDF and CV file download enforce `downloadCv` and portal users only
- [ ] Identity reveal is rate-limited, audited on denial, and returns JSON

### Training

- [ ] Program list, create, view, edit, delete, inline edit, and publish now
- [ ] Program view: registrations approve/reject, broadcasts (draft, send, retry, copy), attendance modes, prep days, checkers and links, grades, notes
- [ ] Certificate relation: issue eligible, exceptional issue, download, verify, regenerate, revoke, ZIP, Excel, email, bulk actions
- [ ] Certificate designer for a program
- [ ] Ownership transfer and registrants export
- [ ] Path list, create, view, edit, delete
- [ ] Path relations: attach/create/detach programs, registrations, attendance, grades, certificates, editors, notes
- [ ] Path certificate designer
- [ ] Program registration list and view: approve, reject, attendance and score, complete, issue certificate
- [ ] Path registration list and view: approve, reject, complete
- [ ] Menu visibility matches today’s gates (`programs.view`, `paths.view`, and the `roles.view` quirk on registration resources) unless that quirk is intentionally changed

### Certificates

- [ ] Certificate index and view: verify link and PDF download
- [ ] Issuing still requires `certificates.issue`
- [ ] No standalone create or delete of a certificate from this resource

### Volunteering

- [ ] Opportunity list, create, view, edit, delete, status filter
- [ ] Registrations, hours, and certificates on the opportunity
- [ ] Volunteer registration approve, reject, and issue certificate
- [ ] Hour approve and reject
- [ ] Team list is limited by assignment access; members and team notifications still edit
- [ ] Volunteer certificate designer

### Content

- [ ] News list, create, view, edit, delete, preview, publish now, schedule, move to draft, content editor, image preview
- [ ] Partners list, create, view, edit, delete
- [ ] Media photos list, create, edit, delete, bulk delete
- [ ] Regulations list, create, edit, delete

### Governance and privacy

- [ ] Board, general assembly, committees (including members), governance documents, investment years (including items)
- [ ] Privacy policy: draft, publish, archive, clone, acknowledgements
- [ ] Candidate-pool consent: draft, publish, clone
- [ ] Privacy request workflow from assign through deletion execution
- [ ] Retention policies: preview and activate
- [ ] Retention exceptions: create and revoke
- [ ] Retention runs: read-only

### Access

- [ ] Roles and permissions are readable with `roles.view` and cannot be created, edited, or deleted in the UI
- [ ] Staff permission matrix is admin-only and still edits the grid

### Support, notifications, security

- [ ] Support inbox: tabs, search, reply, internal note, close with reason
- [ ] Support ticket list and view, with assign, status, and notes permissions
- [ ] Notification center: list, mark read, send
- [ ] Send-notification page works for `send_notifications` and team leaders and stays out of the sidebar
- [ ] Notification rows can approve or reject program, path, and volunteer registrations
- [ ] Audit log and security log are read-only; sensitive security metadata stays behind its permission
- [ ] Error-page stats: filters, chart, refresh, prune

### Gate

- [ ] Checker link still opens the portal
- [ ] Manual list, QR scan, live session start and end, and logout still work
- [ ] Throttles on access, scan, toggle, and live session still apply
