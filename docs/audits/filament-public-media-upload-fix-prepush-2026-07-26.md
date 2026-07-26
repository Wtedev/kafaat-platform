# Filament Public Media Upload Fix — Pre-Push

**Date:** 2026-07-26  
**Branch:** `fix/filament-public-media-upload` (from `origin/main` @ `3eeb3716f2265714fb08141b74051e9aca8139a8`)  
**Scope:** Unblock Filament FilePond uploads for News / TrainingProgram / VolunteerOpportunity covers; prevent durable orphans from early persist.  
**Status:** Implemented locally — **no Commit / no Push / no Railway mutation** (Pre-Push stop).  

**Secrets policy:** relative paths, SHAs, statuses only. No passwords, OTP values, APP_KEY, or production PII.

**Forbidden honored:** No Railway · No Volume/S3 changes · No `APP_KEY` / DB credential changes · No Filament/Livewire dependency-wide upgrade · No unnecessary migrations · No Mail/Scheduler start · No Commit/Push · No production image mutation · Portal Avatar untouched.

---

## Executive summary

| Item | Result |
|------|--------|
| Root cause | Filament `imageEditor()` / auto-crop Cropper path → `getCroppedCanvas is not a function`; News early `saveUploadedFiles()` in `afterStateUpdated` → durable orphans on cancel |
| Fix chosen | Remove Cropper/auto-crop from News + cover helper; keep standard FileUpload preview + validation; defer durable persist until Submit/Save |
| Portal Avatar | **Unchanged** (no shared Cropper path) |
| Targeted media/upload tests | **39 passed** |
| Full SQLite | **455 passed**, 27 skipped |
| Full PostgreSQL | **455 passed**, 27 skipped |
| Pint / `npm run build` / `composer validate` | **Pass** |
| Local Chrome FilePond | News upload reached **تم الرفع** with **no** `getCroppedCanvas` console error |
| Commit / Push | **Not done** |
| Verdict | **GO for Commit + Push** of the listed files only |

---

## 1. Root cause

### 1.1 Cropper / FilePond hang (API + field usage)

Production UAT (`docs/audits/public-media-management-production-uat-2026-07-26.md`) showed:

- Livewire `upload-file` = **200**
- FilePond UI appeared stuck / Cropper path failed with **`getCroppedCanvas is not a function`**
- Filament **v5.6.1** · Livewire **v4.2.4** (unchanged)

Field configuration on `main`:

| Surface | Problematic APIs |
|---------|------------------|
| `NewsFormSupport::newsImageUploadField()` | `imageEditor()`, `automaticallyCropImagesToAspectRatio()`, aspect options, `automaticallyResizeImagesMode('cover')` |
| `TrainingEntityFormSupport::coverImageUpload()` (Programs / Volunteer / Learning Paths) | `imageEditor()`, `imageResizeMode('cover')` |

Vendor evidence (`vendor/filament/forms/resources/js/components/file-upload.js`): `getCroppedCanvas` runs only inside the image-editor path when `hasImageEditor` is true. With `hasImageEditor: false`, `initEditor()` returns immediately — Cropper is never required for a successful FilePond complete.

**Classification:** field misuse of Filament Cropper/editor APIs on this Filament build (compatibility + unnecessary editor), not a storage/Volume failure.

### 1.2 Durable orphans

`NewsFormSupport` called `$component->saveUploadedFiles()` inside `afterStateUpdated` as soon as FilePond finished the temporary upload. That wrote owned UUID files under `news/images/` **before** Submit/Save. Cancelling the modal/create left orphans (15 UUID files cleaned in Production UAT).

`NewsImageSyncService::resolvePath()` already persists `TemporaryUploadedFile` / `livewire-file:` / `livewire-tmp/*` **on sync/save** — early `saveUploadedFiles()` was redundant and harmful.

---

## 2. Why remove Cropper (preferred simple fix)

Launch does **not** require browser crop. Preferred fix per phase brief:

1. Remove `imageEditor` + automatic crop/resize from News/Programs/Volunteer (via shared cover helper).
2. Keep: image preview, JPEG/PNG/WebP, max 5MB, max 4000×4000, UUID filename, relative path, Arabic validation.
3. Do **not** add a new Cropper library or upgrade Filament solely for this bug.

Removal is sufficient: Filament JS skips Cropper when `hasImageEditor` is false; standard FilePond upload completes without `getCroppedCanvas`.

---

## 3. Orphan-prevention logic (after change)

| Stage | Behavior |
|-------|----------|
| FilePond select/upload | Livewire **temporary** upload only (`livewire-tmp` / temp disk) |
| Cancel / leave form without Save | No new owned path under `news/images/`, `programs/covers/`, `volunteer-opportunities/images/` |
| Submit/Save | Filament `storeFiles()` and/or `NewsImageSyncService` / `PublicMediaLifecycleService` persist → DB relative path → delete previous **owned** path only |
| Failed DB save | `discardFailedUpload` keeps old path; drops new owned file (existing lifecycle tests) |
| Delete record | Owned cover purged; shared/git-backed paths protected (existing tests) |
| Broad deletes | **None** — no `deleteDirectory`, no wipe of `images/*` |

Local Chrome cancel/upload attempts left **no new durable files** under `storage/app/public/news/images/` (still the pre-existing 6 files); temps remain under `storage/app/private/livewire-tmp` only.

---

## 4. Full diff (application + tests)

### 4.1 `app/Support/NewsFormSupport.php`

- Removed: `imageEditor`, aspect auto-crop options, `automaticallyResizeImagesMode`, `afterStateUpdated` → `saveUploadedFiles()`.
- Kept: `image()`, disk `public`, `directory('news/images')`, `storeFiles()`, preview, `panelAspectRatio('5:3')`, UUID naming, 5MB / 4000×4000 / mimes + Arabic messages.
- Helper text: recommend ٥:٣; no mandatory crop.

### 4.2 `app/Filament/Support/TrainingEntityFormSupport.php` (`coverImageUpload`)

- Removed: `imageResizeMode('cover')`, `imageEditor()`.
- Comment documents Filament 5.6 Cropper hang.
- Used by Training Programs, Volunteer Opportunities, Learning Paths.

### 4.3 `app/Filament/Resources/NewsResource.php`

- Docs/helper text: 4MB → 5MB; remove “قص” wording; note no browser crop editor.

### 4.4 Tests added/updated

- `tests/Unit/Support/PublicMediaUploadFieldConfigTest.php` — asserts `hasImageEditor() === false`, no auto-crop ratio, empty `afterStateUpdated`, no early `$component->saveUploadedFiles()` in News source.
- `tests/Unit/Services/News/NewsImageSyncServiceTest.php` — TemporaryUploadedFile persists only on sync (nothing durable before sync).
- `tests/Feature/Filament/PublicMediaFilamentUploadFixTest.php` — Create Program/Volunteer with cover; program HTML `hasImageEditor: false`; News create + sync path; field-level editor disabled for News/covers.

**Not changed:** Portal Avatar forms, Volume/S3 config, migrations, `PublicMediaLifecycleService` core (already correct).

---

## 5. News / Program / Volunteer results

| Entity | Config | PHP / Livewire | Local browser (Chrome) |
|--------|--------|----------------|------------------------|
| News gallery/primary | No editor/crop; no early persist | Field + sync tests Pass | JPG → FilePond **تم الرفع**; preview shown; no Cropper UI; no `getCroppedCanvas` in console (successful run) |
| Program cover | Shared helper without editor | Create + HTML `hasImageEditor: false` Pass | Same field path as helper; desktop + mobile viewport exercised in automation attempts |
| Volunteer cover | Same helper | Create with cover Pass | Same field path |
| Replace / remove / delete / shared-path / failed-save | Existing `PublicMediaManagementTest` + lifecycle | **Pass** (suite) | Deferred to Production UAT after deploy |
| Portal Avatar | Untouched | Existing tests Pass | Not re-broken by this change |
| CV / private docs | Still `s3` / private | Untouched | — |

**Safari:** not automated in this environment (Chrome headless used). Production UAT plan includes Safari smoke.

---

## 6. Browser console result

| Check | Result |
|-------|--------|
| `getCroppedCanvas is not a function` | **Absent** on successful News upload run |
| FilePond UI after JPG | **تم الرفع** (complete) + preview (screenshot evidence) |
| Cropper modal / edit overlay | **Not shown** |
| Cancel without Save → durable `news/images/*` orphan | **None** (temps only in `livewire-tmp`) |

Note: FilePond status enum value `5` is `PROCESSING_COMPLETE` (not “stuck processing”). Production UAT’s `status=5` label as PROCESSING was misleading; the real blocker was Cropper `getCroppedCanvas` + orphan early-persist.

PHPUnit does **not** prove JavaScript; browser evidence above is required alongside field `hasImageEditor: false` assertions.

---

## 7. Tests & quality gates

| Gate | Result |
|------|--------|
| Pint (`--dirty`) | Pass (auto-fixed import order in feature test) |
| Targeted public-media / upload tests | **39 passed** |
| Full SQLite `php artisan test` | **455 passed**, 27 skipped |
| Full PostgreSQL `composer test:pgsql` (`kafaat_testing`) | **455 passed**, 27 skipped |
| `npm run build` | Pass |
| `composer validate --no-check-publish` | Pass |
| Binary test images / secrets in Git status | **None** for this change set |

---

## 8. Exact files for Commit

Include **only**:

1. `app/Support/NewsFormSupport.php`
2. `app/Filament/Support/TrainingEntityFormSupport.php`
3. `app/Filament/Resources/NewsResource.php`
4. `tests/Unit/Support/PublicMediaUploadFieldConfigTest.php`
5. `tests/Unit/Services/News/NewsImageSyncServiceTest.php`
6. `tests/Feature/Filament/PublicMediaFilamentUploadFixTest.php`
7. `docs/audits/filament-public-media-upload-fix-prepush-2026-07-26.md` (this report)

**Do not** commit unrelated untracked `docs/audits/*` from other phases, `.env`, or binary assets.

Suggested commit message (when approved):

```
fix: unblock Filament public media uploads without Cropper

Remove imageEditor/auto-crop and early saveUploadedFiles so FilePond
completes and Livewire temps stay temporary until Submit/Save.
```

---

## 9. GO / NO-GO

| Decision | |
|----------|--|
| **GO for Commit + Push** | Yes — scoped fix, gates green, orphan path corrected, Cropper path removed |
| Commit now? | **No** — stop at Pre-Push per brief |
| Push / Railway / production mutate? | **No** |

---

## 10. Production UAT plan (after publish)

Short checklist once this branch is on production Web:

1. Preflight: Web/Worker Online · Volume mounted · `news/images` count noted · no deploy of Volume/S3/APP_KEY changes.
2. **News** `/admin/news/create` (+ edit gallery): JPG → FilePond **تم الرفع** · console clean of `getCroppedCanvas` · Save → DB relative `news/images/<uuid>.…` · cancel without save → no new durable UUID orphans.
3. **Program** create + inline cover edit: same FilePond complete + relative `programs/covers/…`.
4. **Volunteer** cover edit: same under `volunteer-opportunities/images/`.
5. Replace / remove / delete record · missing placeholder · shared/git path not deleted.
6. Portal Avatar smoke (expect unchanged Pass).
7. CV still on private/s3.
8. Chrome + Safari; desktop + one mobile viewport.
9. Cleanup: anonymize UAT users; restore dir counts; record SHA + verdict in a post-deploy audit.

---

## 11. Stop

Pre-Push complete. **No Commit. No Push. No Railway.**
