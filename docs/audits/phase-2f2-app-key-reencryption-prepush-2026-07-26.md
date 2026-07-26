# Phase 2F-2 — APP_KEY Re-encryption Pre-Push Report

**Date:** 2026-07-26  
**Scope:** Build Artisan re-encrypt command + automated tests + local restore drill from Phase 2E encrypted dump.  
**Explicitly not done:** No Commit · No Push · No PR · No Railway Variables changes · No production `APP_KEY` / `APP_PREVIOUS_KEYS` mutation · No Redeploy · No production DB writes · No `migrate:fresh` on production · No `IDENTITY_LOOKUP_KEY` change · No encrypted/rescue backup deletion.

**Secrets policy:** Variable names, lengths, SHA-256 12-hex fingerprints, aggregate counts only. No passphrase, keys, plaintext, or PII.

---

## 1. Git preflight

| Item | Value |
|------|-------|
| Branch | `fix/app-key-reencryption` (from `origin/main`) |
| Base SHA | `aabab057a3cf14c9adddb2dd559d12d923143ea2` |
| Tracks | `origin/main` @ same SHA |
| Working tree | Unrelated prior audit docs remain **untracked** (not in commit-ready set) |
| AGENTS.md | Not present |

---

## 2. Command design

| Item | Detail |
|------|--------|
| Signature | `php artisan security:reencrypt-app-data` |
| Service | `App\Services\Security\AppDataReencryptionService` |
| Options | `--dry-run` · `--force` (required in production) · `--chunk=100` (scan/transaction batch size) |
| Targets | `users.identity_number_ciphertext` · `privacy_correction_payloads.encrypted_value` |
| Decrypt | Current Encrypter = `APP_KEY` + `APP_PREVIOUS_KEYS` |
| Encrypt | Current `APP_KEY` only (`Crypt::encryptString`) |
| Post-write verify | Decrypt with **current-key-only** Encrypter (no previous keys) |
| Writes | Query Builder `DB::table(...)->update([...])` — only ciphertext column + `updated_at` |
| Not touched | `identity_number_lookup_hash` · `security_logs.identifier_hash` · sessions · cookies · `IDENTITY_LOOKUP_KEY` |
| Transactions | Per chunk; per-record failures inside a chunk are counted without aborting sibling successes; outer DB failure rolls back the chunk |
| Fail-closed | `DecryptException` / verify failure → no write for that row; process continues; **non-zero exit** if `failed > 0` |
| Output | Counts only: `scanned`, `eligible`, `reencrypted`, `skipped_null`, `failed` (+ mode/chunk). No plaintext/keys |
| Re-run safety | AES-CBC IV randomness ⇒ ciphertext bytes may change on re-run; functionally safe (no plaintext loss). Documented in service PHPDoc |
| Production guard | Refuses `APP_ENV=production` without `--force` |

---

## 3. Automated tests

**File:** `tests/Feature/Security/ReencryptAppDataCommandTest.php`  
**Result:** **10 passed** (62 assertions)

| Coverage | Result |
|----------|--------|
| Dry-run no writes | Pass |
| Old ciphertext via `APP_PREVIOUS_KEYS` re-encrypts | Pass |
| New ciphertext not decryptable with old key alone | Pass |
| New ciphertext decrypts without previous keys | Pass |
| Already-current ciphertext safely processable | Pass |
| Null/empty skipped | Pass |
| Corrupt ciphertext fail-closed + non-zero exit | Pass |
| No plaintext/keys in output | Pass |
| Lookup hash unchanged | Pass |
| Privacy payloads supported | Pass |
| Batch failure does not corrupt completed work + safe resume | Pass |
| Rejects production without `--force` | Pass |
| Production `--force --dry-run` writes nothing | Pass |

---

## 4. Local Restore Drill

| Item | Detail |
|------|--------|
| Source | `~/Backups/kafaat-postgres-phase-2e-2026-07-26/kafaat-railway.dump.enc` |
| Passphrase | `~/.kafaat-secrets/…` (mode 600; never printed) |
| Isolated DB | `kafaat_phase2f_appkey_drill` (created, used, **dropped**) |
| Outbound | Mail=`array`, Queue=`sync`, Broadcast=`null`, Session=`array` |
| Keys | Ephemeral temp dir only; wiped after drill. Never written to project/Git |
| Prod key used for decrypt (ephemeral) | sha12=`7af213dae1db` len=51 |
| Temp new key | sha12=`3d2356928ff5` len=51 |
| `IDENTITY_LOOKUP_KEY` (unchanged) | sha12=`5409e934cc94` len=51 |
| Encrypted dump after drill | **Retained** |

### Counts (no PII)

| Metric | Count |
|--------|------:|
| `users` total | 29 |
| Identity ciphertext eligible | **6** |
| Identity lookup hashes | **6** |
| Privacy `encrypted_value` | **0** |

### Steps

| Step | Check | Result |
|------|-------|--------|
| A | Old key only: decrypt 6/6 (10-digit length class) | **PASS** |
| B | New key + old in `APP_PREVIOUS_KEYS`: decrypt 6/6 | **PASS** |
| C | `--dry-run`: scanned 29, eligible 6, reencrypted 6, skipped_null 23, failed 0; ciphertext aggregate **unchanged** | **PASS** |
| D | Real run: reencrypted **6/6**, failed 0; ciphertext aggregate **changed** | **PASS** |
| E | Remove previous keys: 6/6 decrypt with new only; old key alone fails 6/6 | **PASS** |
| F | Lookup hash aggregate before=after (`2c622141…bf4da732`) | **PASS** |
| G | Second run: exit 0; 6/6 still decryptable; no corruption | **PASS** |

---

## 5. Quality gates

| Gate | Result |
|------|--------|
| Pint (changed files) | **Pass** |
| Targeted command tests | **10 passed** |
| Full SQLite suite | **424 passed**, 27 skipped, 0 failed |
| Full PostgreSQL suite (`kafaat_testing`) | **424 passed**, 27 skipped, 0 failed |
| `npm run build` | **Pass** |
| `composer validate` | **Pass** (`./composer.json is valid`) |
| Secrets/plaintext in commit-ready diff | **None found** |
| Production untouched | **Yes** — Web Online · deploy `100d85e2-…` · `APP_KEY` sha12=`7af213dae1db` · `APP_PREVIOUS_KEYS` **absent** · `IDENTITY_LOOKUP_KEY` sha12=`5409e934cc94` |

---

## 6. Future Phase A / B plan (document only — **not executed**)

### Phase A — Safe transition (Railway vars + redeploy)

1. Generate new Laravel key offline (`php artisan key:generate --show` on throwaway local process; do not write into production `.env` from chat).
2. Set on **both** Web (`kafaat-platform`) and Worker (`poetic-reprieve`) **before** relying on new encrypt:
   - `APP_PREVIOUS_KEYS` = **current** (exposed) key  
   - `APP_KEY` = **new** key  
3. Hygiene (value unchanged): set Worker `IDENTITY_LOOKUP_KEY` to the **same** Web value (sha12 must remain `5409e934cc94`). Do **not** rotate it.
4. Orderly redeploy Web then Worker (or both with identical vars).
5. Smoke: `/up`, `system:health`, admin login, one identity decrypt path (pass/fail only), harmless queue job.
6. Confirm existing ciphertext still decrypts (previous key path).
7. **Rollback A:** Restore prior `APP_KEY`, clear `APP_PREVIOUS_KEYS`, redeploy Web+Worker. Ciphertext untouched.

### Phase B — Re-encrypt then drop previous key

1. Merge/deploy this command to production code first (after commit/push/PR approval).
2. Maintenance-friendly window optional; `jobs` pending should be 0.
3. Production dry-run: `php artisan security:reencrypt-app-data --dry-run --force` → expect eligible **6** (plus any new privacy payloads).
4. Production write: `php artisan security:reencrypt-app-data --force` → prove **6/6** (failed 0).
5. Prove decrypt with new key alone (controlled probe / temporary unset previous in non-prod already proven).
6. Clear `APP_PREVIOUS_KEYS` on Web+Worker; redeploy.
7. Invalidate sessions / remember tokens (`TRUNCATE sessions` or delete; users re-login). Accept Livewire short-lived URL invalidation.
8. **Rollback B:** Prefer resume command if mid-batch; catastrophic → restore Phase 2E encrypted dump + prior key pair. Do not clear previous keys early.

**Do not execute Phase A or B in this phase.**

---

## 7. Exact files for commit

1. `app/Console/Commands/ReencryptAppDataCommand.php`
2. `app/Services/Security/AppDataReencryptionService.php`
3. `tests/Feature/Security/ReencryptAppDataCommandTest.php`
4. `docs/audits/phase-2f2-app-key-reencryption-prepush-2026-07-26.md` (this report)

**Do not stage** other untracked `docs/audits/*` WIP reports.

### Proposed commit message

```
Add APP_KEY ciphertext re-encryption Artisan command and tests.

Supports dry-run, chunked transactions, and APP_PREVIOUS_KEYS decrypt
so identity/privacy ciphertext can be safely rewritten before dropping
the previous application key.
```

---

## 8. Rollback plan (for this code change)

| Scenario | Action |
|----------|--------|
| After commit/push, before deploy | Revert commit / close PR — no production effect |
| After deploy, command unused | Redeploy previous release; command is inert unless invoked |
| After production re-encrypt (Phase B) | Resume command if partial; else restore Phase 2E encrypted dump + prior `APP_KEY`/`APP_PREVIOUS_KEYS` pair |
| Local drill leftovers | Already dropped `kafaat_phase2f_appkey_drill`; temp key files wiped; encrypted dump retained |

---

## 9. Verdict

| Question | Answer |
|----------|--------|
| Safe to **commit + push** this branch? | **GO** |
| Safe to change Railway / rotate production `APP_KEY` now? | **NO-GO** (await Phase A/B execution after merge) |
| Commit/Push/PR executed this phase? | **No** (stopped at report) |

### GO / NO-GO for commit + push only

# **GO**
