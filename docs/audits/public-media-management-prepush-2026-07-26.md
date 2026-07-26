# Public Media Management — Pre-Push

**Date:** 2026-07-26  
**Branch:** `feat/public-media-management` (from `origin/main` @ `26d19b99fe1ebb52449ea9bcb897c2cf2303e2bd`)  
**Scope:** Staff-managed public covers for News / TrainingProgram / VolunteerOpportunity + safe portal/staff profile photos on the durable public disk.  
**Status:** Implemented locally — **no Commit / no Push / no Railway mutation** (awaiting approval).  
**Pre-Push correction:** dimension cap **4000×4000** + News delete/replace lifecycle hardening + extra tests (applied before Commit).

**Secrets policy:** No keys, passphrases, credentials, or production PII in this report.

**Forbidden honored:** No Railway deploy · No Volume/S3 changes · No `APP_KEY` / `IDENTITY_LOOKUP_KEY` change · No migrate:fresh · No production image deletion · No Scheduler/Mail start · No Commit/Push.

---

## Executive summary

| Item | Result |
|------|--------|
| Base SHA | `26d19b99fe1ebb52449ea9bcb897c2cf2303e2bd` |
| New migrations | **None** (columns already existed) |
| Public media disk | `public` (local → Railway Volume `/app/storage/app/public`) |
| Private docs / CV | unchanged (`s3`) — not touched |
| Max new-image dimensions | **4000×4000** (was briefly 8000; corrected pre-commit) |
| Profile avatar | **Public disk** — public Avatar, **not** a private document |
| Targeted media tests | **Pass** |
| Full SQLite suite | **445 passed**, 27 skipped |
| Full PostgreSQL suite | **445 passed**, 27 skipped |
| Pint / `npm run build` / `composer validate` | **Pass** |
| Commit / Push | **Not done** |
| Verdict | **GO for Commit + Push** of the listed files only |

---

## 1. Preflight / architecture (before change)

### 1.1 Storage posture (production context)

| Layer | State |
|-------|--------|
| Public disk | local on Railway Volume `/app/storage/app/public` · `PUBLIC_STORAGE_PERSISTENT=1` |
| Private documents / CV | `s3` (must not mix with marketing images) |
| Phase B | complete · `APP_PREVIOUS_KEYS` absent |

### 1.2 Existing schema (no migration needed)

| Entity | Column(s) | Prior Filament/UI |
|--------|-----------|-------------------|
| News | `news.image` + `news_images.path` | Staff gallery upload on `news/images/` via `NewsImageSyncService` |
| TrainingProgram | `training_programs.image` | **Locked** (`allowCoverUpdate=false`) · read-only Placeholder · git/seeder paths under `images/programs/` |
| VolunteerOpportunity | `volunteer_opportunities.image` | Staff upload on `volunteer-opportunities/images/` |
| Profile | `profiles.avatar` | Portal multipart upload → `avatars/` on `public` |
| Staff | `users.staff_photo` | Filament `StaffProfilePage` → `staff-photos/` on `public` |

### 1.3 Gaps found

1. Program covers intentionally non-editable from staff UI.  
2. Replace order for portal avatar deleted old file **before** successful store/update.  
3. TrainingProgram / VolunteerOpportunity delete did not purge owned public files.  
4. Validation uneven (2MB avatar vs larger covers; GIF sometimes historically allowed).  
5. No shared lifecycle helper for owned-path replace/remove.  
6. News purge during model `deleting` could skip files because the same news’ rows still referenced the path (fixed in Pre-Push correction).

---

## 2. Storage contract (after change)

| Media type | Disk | DB value | Write directory | Notes |
|------------|------|----------|-----------------|-------|
| News gallery / primary | `public` | relative path | `news/images/` (kept) | Compatible with existing rows |
| Program cover (new staff uploads) | `public` | relative path | `programs/covers/` | Git `images/programs/*` still readable; **not** rewritten |
| Volunteer cover | `public` | relative path | `volunteer-opportunities/images/` (kept) | Compatible |
| Learning path covers | `public` | relative path | `learning-paths/images/` | Unchanged helper path |
| Portal profile avatar | `public` | relative path | `avatars/` | See §2.1 — **public Avatar** |
| Staff photo | `public` | relative path | `staff-photos/` | Self-service staff page |
| CV / privacy exports | private/`s3` | relative path | unchanged | Explicitly excluded; no private streaming work |

**Filenames:** UUID + real extension (`jpg`/`jpeg`/`png`/`webp`) — new uploads do not intentionally share paths.  
**URLs:** built via `PublicDiskPath::url` / `urlOrPlaceholder` — never store absolute URL as the durable path for new uploads.

### 2.1 Profile avatar — public vs private decision

**Decision: keep on `public` disk (Volume).**

This is a **public Avatar** for display in the portal UI (settings + competency hero), **not** a private identity/document asset.

**Why (code evidence):**

- Served as `/storage/...` via `Profile::avatarUrl()` → `PublicDiskPath::url()` with **no auth middleware** on the file URL.  
- Already stored under `avatars/` on `public` before this package.  
- CV / privacy exports remain on `s3` / private documents — unchanged.

**Mitigations:** opaque UUID filenames · user can only change own avatar via portal route · admin Profile resource still gated by existing ProfilePolicy.

**Out of scope this release:** private-disk avatars + authorized streaming controller.

---

## 3. Upload / replace / remove / delete / fallback

### Validation (shared)

- Types: JPEG / PNG / WebP only (SVG/GIF/executables rejected).  
- Max size: **5MB** (5120 KB).  
- Laravel `image` + `mimes` + `dimensions:max_width=4000,max_height=4000`.  
- Arabic validation messages on Filament + portal request.

### Lifecycle (`PublicMediaLifecycleService`)

1. **Upload / replace:** store new → persist DB → delete previous **only if owned**.  
2. **Failed DB update:** discard new owned path; keep previous.  
3. **Manual remove (avatar):** null DB → delete owned previous.  
4. **Record delete:** TrainingProgram / VolunteerOpportunity / News purge owned paths; never delete `images/*` git assets or placeholders.  
5. **Missing file:** `imagePublicUrl()` / placeholders — no 500.  
6. **No broad directory deletion:** neither `PublicMediaLifecycleService` nor `NewsImageSyncService` call `deleteDirectory`.

Owned prefixes include: `news/images/`, `programs/covers/`, `volunteer-opportunities/images/`, `avatars/`, `staff-photos/`, etc. Paths under `images/` are never deleted by the lifecycle service.

### 3.1 News lifecycle (verified)

| Step | Behavior |
|------|----------|
| **Create** | Gallery sync persists relative paths under `news/images/` (UUID filenames for new Filament uploads). |
| **Replace** | Sync updates primary column **before** deleting outgoing owned files so reference checks do not block cleanup. |
| **Remove** | `sync(..., allowEmpty: true)` clears gallery + primary column; owned files deleted one-by-one. |
| **Delete** | `News::deleting` → `purgeFilesForNews` with `ignoreNewsId` so this news’ own rows do not block deletion; other news sharing a path keep the file. |
| **Missing-file fallback** | `imagePublicUrl()` → news placeholder SVG; no 500. |
| **Shared path safety** | `deletePaths` skips a file if another `news_images` / `news.image` row still references it. New uploads use UUIDs so intentional sharing is not the design; the check is a safety net. |
| **Git assets** | `images/*` never owned → never deleted. |

---

## 4. Feature deltas

### News

- Hardened `NewsFormSupport` upload rules, UUID filenames, Arabic messages, **4000×4000** cap.  
- Sync delete path uses lifecycle ownership checks.  
- Pre-Push fix: `purgeFilesForNews(..., ignoreNewsId)` + sync updates primary column before file delete.  
- Public cards/details already used `imagePublicUrl()` — unchanged contract.

### Programs

- Cover field editable again (`programs/covers/`).  
- Create sets `allowCoverUpdate = true`.  
- View inline cover edit (`field = image`) + safe `handleRecordUpdate`.  
- Model lock retained for accidental mass-assignment without the flag.  
- TipTap description / registration / auto-accept untouched.

### Volunteer opportunities

- Existing upload field hardened via shared helper.  
- Replace cleanup on settings save; owned file delete on model delete.  
- Application/acceptance logic untouched.

### Profile / staff photo

- Portal: store-first replace; `remove_avatar` checkbox; 5MB + **4000×4000** rules.  
- Staff profile page: lifecycle on replace; validation aligned.  
- Admin Profile avatar field validation hardened (existing admin policy only).  
- Remains **public Avatar** on public disk (see §2.1).

### Permissions

- Unchanged role matrix.  
- Staff needs existing update permissions (`manage_news`, program update auth, `volunteering.update` + assignment).  
- Beneficiary cannot update News/Program/Volunteer (policy test).  
- Portal avatar updates only authenticated user’s profile.

---

## 5. Migrations

**None added.** Rollback check: N/A.

---

## 6. Tests & quality gates

| Gate | Result |
|------|--------|
| Pint (`--dirty`) | Pass |
| Targeted media + related | Pass (`PublicMediaLifecycleServiceTest`, `PublicMediaManagementTest`, cover lock, inline edit, NewsImageSync, ProfileBaseline, ViewPresenter) |
| Full SQLite `php artisan test` | **445 passed**, 27 skipped |
| Full PostgreSQL `composer test:pgsql` (`kafaat_testing`) | **445 passed**, 27 skipped |
| `npm run build` | Pass |
| `composer validate --no-check-publish` | Pass (`./composer.json is valid`) |
| Secrets / binary images in Git working tree for this change | None |
| N+1 | Card lists continue to use per-model `imagePublicUrl()` (same pattern as before); no new eager-load regressions introduced for galleries |

### Coverage highlights (`PublicMediaManagementTest`)

- Relative path persistence + public URL.  
- Replace deletes owned previous.  
- Failed-upload discard.  
- Git-backed program path not owned for deletion.  
- Missing file → placeholder.  
- Volunteer/program delete lifecycle.  
- Avatar upload / replace / remove / reject SVG / fake / oversized.  
- Reject image **>4000px**; accept within limit.  
- News delete removes owned primary + gallery under `news/images/`; keeps `images/*`.  
- Shared news path kept until last referencing row is gone.  
- News create/replace/remove/missing fallback; asserts **no** `deleteDirectory`.  
- Cannot hijack another user’s avatar via portal.  
- Beneficiary denied content update policies.  
- Public media on `public` vs CV bytes on `s3`.

---

## 7. Risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Volume fill from large uploads | Medium | 5MB + **4000px** caps |
| Orphans if Filament stores file then user abandons modal | Low | UUID under owned dirs; lifecycle on successful replace/delete |
| Accidental wipe of git covers | Low | `allowCoverUpdate` gate + never delete `images/` |
| Avatar publicly fetchable if URL known | Accepted | Opaque names; same as prior design (public Avatar) |
| Staff can edit beneficiary avatar in admin Profile resource | Existing | ProfilePolicy (`roles.view` / badges) — not expanded |

---

## 8. Manual / production test plan (later — do not run now)

1. Staff: upload news gallery image → appears on public news card/detail.  
2. Staff: set program cover via inline edit → public catalog/detail.  
3. Staff: set volunteer cover via settings → public card/detail.  
4. Replace each cover; confirm old owned file gone, new URL works.  
5. Remove cover / clear upload; placeholder shows; no 500.  
6. Reject SVG, renamed PDF→JPG, >5MB, and **>4000px** with Arabic errors.  
7. Portal: user changes own avatar; remove avatar; another user cannot change it.  
8. Redeploy web; confirm Volume persistence (`PUBLIC_STORAGE_PERSISTENT=1`).  
9. Spot-check orphan dirs under `storage/app/public/{news,programs,volunteer-opportunities,avatars}`.  
10. Confirm CV download still private/`s3` (not `/storage/...`).

---

## 9. Exact files proposed for Commit

**Include only:**

```
app/Services/Media/PublicMediaLifecycleService.php
app/Filament/Concerns/HasInlineEntityViewEditing.php
app/Filament/Pages/StaffProfilePage.php
app/Filament/Resources/ProfileResource/Schemas/ProfileAdminForm.php
app/Filament/Resources/TrainingProgramResource.php
app/Filament/Resources/TrainingProgramResource/Pages/CreateTrainingProgram.php
app/Filament/Resources/TrainingProgramResource/Pages/ViewTrainingProgram.php
app/Filament/Resources/VolunteerOpportunityResource/Pages/ViewVolunteerOpportunity.php
app/Filament/Support/TrainingEntityFormSupport.php
app/Filament/Support/TrainingProgramInlineEditSupport.php
app/Filament/Support/TrainingProgramViewPresenter.php
app/Http/Controllers/Portal/PortalProfileController.php
app/Http/Requests/Portal/UpdatePortalProfileRequest.php
app/Models/TrainingProgram.php
app/Models/VolunteerOpportunity.php
app/Services/News/NewsImageSyncService.php
app/Support/NewsFormSupport.php
resources/views/portal/partials/profile-form.blade.php
tests/Feature/Filament/TrainingProgramDescriptionInlineEditTest.php
tests/Feature/Media/PublicMediaManagementTest.php
tests/Unit/Filament/TrainingProgramViewPresenterTest.php
tests/Unit/Services/Media/PublicMediaLifecycleServiceTest.php
docs/audits/public-media-management-prepush-2026-07-26.md
```

**Exclude:** all other untracked `docs/audits/*` from prior phases.

---

## 10. GO / NO-GO

| Action | Verdict |
|--------|---------|
| **Commit** (listed files) | **GO** |
| **Push** branch + open PR | **GO** (after Commit approval) |
| Railway deploy / Volume / S3 / APP_KEY | **NO-GO** in this stage |
| Production manual media tests | **NO-GO** until after deploy approval |

**Stopped at Pre-Push.** Awaiting explicit approval to Commit and Push.
