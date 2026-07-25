# Phase 2C-2 Pre-Push Report — Durable Storage (Option B code)

| Field | Value |
|-------|-------|
| **Date** | 2026-07-25 |
| **Scope** | Phase **2C-1** (rescue) + **2C-2** (Composer S3 + `s3` disk hardening + tests) **ONLY** |
| **Repo** | `/Users/mymac/projects/kafaat_platform` |
| **Branch** | `fix/durable-storage` (from `main` @ `b000b1f`) |
| **Option** | **B** — Volume (public media, later) + S3 (private docs, later env) |
| **Constraint** | **No Commit · No Push · No S3 bucket · No Volume · No Redeploy · No Worker/Scheduler · No Phases 3–9** |
| **2C-1** | **Pass** — `docs/audits/phase-2c1-rescue-inventory-2026-07-25.md` |
| **2C-2** | **Pass** (SQLite suite green; PG note below) |
| **Awaiting** | User approval for **2C-3+** (bucket / volume / env / commit / push / redeploy) |

---

## 1. Phase 2C-1 result (prerequisite)

| Gate | Result |
|------|--------|
| Production SSH inspect | **Pass** |
| Public `/app/storage/app/public` | **196 files · 87M** — rescued encrypted off-container |
| Private `/app/storage/app/private-documents` | **MISSING · 0 files** |
| DB `user_documents` / `privacy_export_files` | **0 / 0** |
| Empty private rescue safe? | **Yes** (documented) |

Rescue (outside repo): `/Users/mymac/Backups/kafaat-platform-phase-2c1-2026-07-25/`  
Details: `docs/audits/phase-2c1-rescue-inventory-2026-07-25.md`

---

## 2. Files changed (working tree — **not committed**)

### Exact commit file list (when user approves commit)

| Path | Change |
|------|--------|
| `composer.json` | Require `league/flysystem-aws-s3-v3` `^3.35` |
| `composer.lock` | Lock S3 adapter + transitive deps (no full `composer update`) |
| `config/filesystems.php` | `s3` disk: `visibility=private`, `throw=true` |
| `tests/Feature/Storage/DurablePrivateStorageTest.php` | **New** — 11 tests for Option B private disk |
| `docs/audits/phase-2c1-rescue-inventory-2026-07-25.md` | 2C-1 inventory (counts/sizes only) |
| `docs/audits/phase-2c2-prepush-2026-07-25.md` | This report |

### Intentionally **not** in the storage commit (leave uncommitted / separate)

- Unrelated prior audit docs still untracked on disk (`phase-1-*`, `production-*-audit*`, `railway-infra-prepush-review.md`, `phase-2-durable-storage-preimpl-…`) — **preserved, not mixed into storage commit unless user asks**
- No feature services (`CertificatePdfService`, Filament, CV/export controllers) edited
- `PUBLIC_DISK_DRIVER` remains default **`local`** (not converted to S3)

### `config/filesystems.php` delta (s3 only)

- Added `'visibility' => 'private'`
- Changed `'throw' => false` → `'throw' => true` (align with `private_documents` sensitive disk)
- Comment clarifying Option B private-bucket use via `PRIVATE_DOCUMENTS_DISK=s3`
- **`public` disk unchanged** (still `PUBLIC_DISK_DRIVER` / local volume path)

---

## 3. Package version

| Package | Version |
|---------|---------|
| `league/flysystem-aws-s3-v3` | **3.35.2** (`^3.35` in `composer.json`) |
| Compatible with | Laravel **13.7** / Flysystem **3.35.x** |
| Install method | `composer require league/flysystem-aws-s3-v3` (targeted; not a full project `composer update`) |
| Adapter class present | `League\Flysystem\AwsS3V3\AwsS3V3Adapter` — **OK** |

Transitive adds (lock): `aws/aws-sdk-php`, `aws/aws-crt-php`, `mtdowling/jmespath.php`, related Flysystem/Guzzle bumps required by the adapter.

---

## 4. Test results

| Check | Result |
|-------|--------|
| `composer validate` | **Pass** — `./composer.json is valid` |
| `php artisan test` (SQLite default) | **Pass** — **414 passed**, 27 skipped, 1235 assertions (~43s) |
| Durable suite (`DurablePrivateStorageTest`) | **11/11 Pass** (SQLite + PostgreSQL) |
| PostgreSQL suite (`DB_CONNECTION=pgsql` via `.env.testing`) | **413 passed**, 27 skipped, **1 failed** (see §4.1) |
| `npm run build` | **Pass** |
| `composer audit` | **33 advisories / 15 packages** (see §5) |
| `php artisan config:cache` then `config:clear` | **Pass** |

### 4.1 PostgreSQL note (pre-existing — not introduced by 2C-2)

| Test | Outcome |
|------|---------|
| `Tests\Feature\Operations\ErrorPageVisitsSystemTest::test_error_page_still_renders_when_database_insert_fails` | **Fail** on PG: drops `error_page_visits` mid-suite then `migrate --path=…` → `SQLSTATE[25P02]` aborted transaction |
| `DurablePrivateStorageTest` on PG | **11/11 Pass** |

This failure is unrelated to S3/filesystems changes (intentional table drop + migrate repair pattern incompatible with PG open transactions). **SQLite full suite remains the primary green gate for 2C-2.** Operator may track the PG test as a separate follow-up; it does **not** block approving the storage code commit list.

### 4.2 New tests cover

1. `PRIVATE_DOCUMENTS_DISK=s3` accepted (`PrivateDocumentsStorage::diskName`)
2. Public disk **not** used for CV / export objects
3. No public CV URL (`cvPublicUrl()` null; paths under `cv/`)
4. CV download via authorized portal controller when disk=`s3`
5. Export download via `PrivacyExportDownloadService` stream on `s3` (private headers)
6. `s3` config default `visibility=private` + `throw=true`
7. Safe failure when private object missing / invalid disk / health unreachable

---

## 5. Advisories delta

| Metric | Value |
|--------|------:|
| Advisories after 2C-2 require | **33** |
| Packages affected | **15** |

Packages (unchanged set vs post-require snapshot — **no new advisory attributed to `league/flysystem-aws-s3-v3`**):

`dompdf/dompdf`, `filament/*`, `laravel/framework`, `phpoffice/phpspreadsheet`, `setasign/fpdi`, `symfony/*` (html-sanitizer, http-foundation, http-kernel, mailer, mime, polyfill-intl-idn, routing)

**Delta vs pre-2C-2 intent:** Installing the S3 adapter did not introduce a new named advisory package for Flysystem/AWS SDK in this audit run. Pre-existing Filament/Dompdf/Symfony advisories remain (out of Phase 2C scope).

---

## 6. Breaking changes?

| Question | Answer |
|----------|--------|
| Breaking for local default? | **No** — default `PRIVATE_DOCUMENTS_DISK=private_documents` (local) unchanged in `.env.example` / phpunit |
| Breaking for public media? | **No** — `PUBLIC_DISK_DRIVER=local`; certificates still use `public` disk `path()` |
| Runtime if prod sets `PRIVATE_DOCUMENTS_DISK=s3` **without** AWS env / bucket? | Writes/health will fail closed (`throw=true` / health `private_disk_unreachable`) — expected until 2C-3 env |
| API / route changes? | **None** |
| DB migrations? | **None** |

---

## 7. Local works without AWS on local disk?

**Yes.**

- With `PRIVATE_DOCUMENTS_DISK=private_documents` (default): CVs/exports use local `storage/app/private-documents` — no AWS calls.
- Package is present but **idle** until disk `s3` is selected.
- Verified by existing + new tests using `Storage::fake('private_documents')` / default config, and full SQLite suite green.

---

## 8. Exact commit file list (copy/paste for later approval)

```text
composer.json
composer.lock
config/filesystems.php
tests/Feature/Storage/DurablePrivateStorageTest.php
docs/audits/phase-2c1-rescue-inventory-2026-07-25.md
docs/audits/phase-2c2-prepush-2026-07-25.md
```

Do **not** include unrelated untracked audit docs unless explicitly requested.

---

## 9. Explicit non-actions (honored)

| Action | Status |
|--------|--------|
| Git commit | **Not done** |
| Git push | **Not done** |
| Create S3 / Railway Bucket | **Not done** |
| Mount Railway Volume | **Not done** |
| Redeploy | **Not done** |
| Start Worker / Scheduler | **Not done** |
| Phases 3–9 | **Not started** |
| Change CertificatePdfService / feature files | **Not done** |

---

## 10. Recommended next steps after approval (2C-3+) — **do not run yet**

1. Commit exact file list on `fix/durable-storage` (message TBD by user).
2. Create **private** bucket (Block Public Access); set Web `PRIVATE_DOCUMENTS_DISK=s3` + `AWS_*` (names only in docs).
3. Attach public volume at `/app/storage/app/public`; set `PUBLIC_STORAGE_PERSISTENT=1`; keep `PUBLIC_DISK_DRIVER=local`.
4. Restore rescued public tree into volume (merge); verify count **196** / bytes **90099666**.
5. Redeploy Web only after env+volume ready; smoke A–D from Phase 2B report.
6. Defer Worker/Scheduler to Phase 3.

---

## 11. Verdict

| Item | Result |
|------|--------|
| **2C-1** | **Pass** |
| **2C-2** | **Pass** (awaiting Pre-Push approval) |
| **Stopped** | **Yes** — awaiting user approval before 2C-3+ / commit / push / infra |

*End of Phase 2C-2 Pre-Push. No further Railway or git mutations until approved.*
