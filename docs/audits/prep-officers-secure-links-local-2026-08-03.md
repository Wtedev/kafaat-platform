# Prep officers secure links — local verification (2026-08-03)

Worktree: `/Users/mymac/projects/kafaat_attendance_checkers_secure`  
Branch: `fix/attendance-checkers-secure-links` @ `7a05a68` (origin/main + local changes)  
Status: Draft PR open — real app PNG screenshots captured locally (tokens redacted). No merge/deploy.

## Old flow deprecated

| Removed from product flow | Status |
|---|---|
| Email on create | stopped (column nullable) |
| 6-digit OTP + 15-min expiry | stopped |
| Resend code | removed |
| Email+code login screen | replaced with “use your link” |
| `AttendanceCheckerInviteCode` | deleted |
| `ProgramAttendanceCheckerInviteService` | replaced by `ProgramAttendanceCheckerAccessService` |
| Manual `KAFAAT-P…-R…` text entry on gate | removed (list toggle instead) |

Legacy DB columns kept for safe later drop: `email`, `invite_code_hash`, `invite_code_expires_at`, `invite_attempts`, `verified_at`.

## Token strategy

1. `bin2hex(random_bytes(32))` → 64-char hex plain token  
2. Store **SHA-256 hash only** in `access_token_hash`  
3. URL: `/gate/{slug}/access/{token}` (throttled)  
4. On success: `session()->regenerate()`, store checker id + program id + `access_version`, redirect to clean `/gate/{slug}/portal`  
5. Never log token/URL in AuditLogger

## Invalidation

- Regenerate link → new hash + `access_version++` → old token + old sessions fail  
- Deactivate → `is_active=false` + `access_version++`  
- Cross-program session blocked by program id match

## Tables / files

- Migration: `2026_08_03_180000_add_secure_access_link_to_program_attendance_checkers.php`  
- Model/service/middleware/controller/Filament relation manager/gate views/routes as implemented in this worktree  
- Tests: `tests/Feature/Gate/PrepOfficerSecureLinkTest.php` (+ gate/pass/operational updates)

## Verification

- Targeted gate/prep/portal/Filament attendance: **pass** (PG)  
- Full suite PG (`kafaat_testing`): **688 passed**, 27 skipped, then operational gate redirect fix → OperationalAccountAccessTest **22/22 pass**  
- Pint: fixed dirty files  
- `npm run build`: OK  

## Screenshots

### Real app PNGs (`docs/audits/screenshots/prep-officers-secure-links/real/`)

1. `01-filament-checkers-tab.png` — مسؤولو التحضير tab  
2. `02-create-link-copy-modal.png` — create/copy link modal (token redacted)  
3. `03-manual-prep-list.png` — manual prep list  
4. `04-search-arabic-name-parts.png` — search by Arabic name part  
5. `05-qr-in-person-day.png` — QR on in-person day  
6. `06-remote-day-no-qr.png` — remote day (no QR)  
7. `07-mobile-view.png` — mobile portal  

### HTML fixtures (placeholder UX)

Also under `docs/audits/screenshots/prep-officers-secure-links/*.html`.

## Regenerate invalidation (local)

Verified against running `php artisan serve` + isolated screenshot SQLite:

- Old access URL after regenerate → redirect to gate login  
- Old portal session after regenerate → redirect to gate login  
- New access URL → portal 200  
