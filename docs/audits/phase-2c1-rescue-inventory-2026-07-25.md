# Phase 2C-1 — Production Storage Rescue Inventory

| Field | Value |
|-------|-------|
| **Date** | 2026-07-25 |
| **Scope** | Protect current production files **before** any volume mount / redeploy / env cutover |
| **Access** | Railway SSH to `kafaat-platform` (production) — authenticated |
| **Result** | **Pass** |
| **Constraint** | Counts / sizes / extensions / non-sensitive prefixes only — no CV names, no user data, no file contents |

> Secrets and passphrase for the encrypted rescue archive are **not** stored in git. Rescue location is outside the repository.

---

## 1. Access

| Check | Result |
|-------|--------|
| `railway whoami` | Authenticated |
| Linked service | `kafaat-platform` · environment `production` |
| SSH | Registered agent key; host key accepted; remote commands succeeded |
| Inspected paths | `/app/storage/app/public`, `/app/storage/app/private-documents`, `/app/storage/app/private` |

---

## 2. Inventory summary (counts / sizes only)

### `/app/storage/app/public`

| Metric | Value |
|--------|-------|
| Exists | **Yes** |
| File count | **196** |
| Directory count | **49** |
| Total size | **87M** (`90099666` bytes) |

**Extensions**

| Extension | Count |
|-----------|------:|
| `jpg` | 125 |
| `pdf` | 57 |
| `png` | 13 |
| `gitignore` | 1 |

**Non-sensitive relative path prefixes (top-level)**

| Prefix | Files | Approx size |
|--------|------:|-------------|
| `media/` | 113 | 27M |
| `governance-docs/` | 37 | 37M |
| `regulation-docs/` | 20 | 21M |
| `partners/` | 13 | 1.7M |
| `images/` | 7 | 556K |
| `news/` | 5 | 628K |
| `(root)` (e.g. `.gitignore`) | 1 | — |

**Deeper non-sensitive prefixes (depth ≤ 2)**

| Prefix | Files |
|--------|------:|
| `media/photos/` | 113 |
| `regulation-docs/files/` | 20 |
| `governance-docs/financial-reports/` | 11 |
| `governance-docs/general-assembly-minutes/` | 9 |
| `governance-docs/executive-reports/` | 8 |
| `images/board/` | 7 |
| `governance-docs/surveys/` | 6 |
| `news/images/` | 5 |
| `governance-docs/investment-decisions/` | 3 |
| `partners/` (flat logos) | 13 |

**Absent public prefixes (expected elsewhere / empty today)**

- `certificates/` — **0** files
- `avatars/` — **0** files
- `staff-photos/` — **0** files

### `/app/storage/app/private-documents`

| Metric | Value |
|--------|-------|
| Exists | **No (MISSING)** |
| File count | **0** |
| Total size | **0** |

### `/app/storage/app/private` (Laravel default local disk root — not CV disk)

| Metric | Value |
|--------|-------|
| Exists | Yes |
| File count | **1** (`.gitignore` only) |
| Total size | 8.0K |

### Database cross-check (counts only — no paths / no PII)

| Table | Count |
|-------|------:|
| `user_documents` | **0** |
| `privacy_export_files` | **0** |

**Judgment:** No private CV / privacy-export blobs on disk; no DB rows pointing at private documents. Empty private rescue is **safe**. Critical durable content to protect for volume cutover is **public media + public PDFs** under the prefixes above.

---

## 3. Rescue copy (outside container)

| Item | Detail |
|------|--------|
| Location (host, outside repo) | `/Users/mymac/Backups/kafaat-platform-phase-2c1-2026-07-25/` |
| Encrypted archive | `storage-app-public.tar.gz.enc` (AES-256-CBC, PBKDF2, 200000 iterations) |
| Encrypted size | ~75M |
| Plaintext tar after verify | **Removed** (encrypted copy retained) |
| Passphrase file | `PASSPHRASE.txt` (mode `600`, not in git) |
| Permissions | Directory and contents `go-rwx` |

**Contents of rescue set**

- Encrypted tarball of `/app/storage/app/public`
- `public-files.sha256` — 196 per-file SHA-256 digests (relative paths; local only)
- `archive.sha256` — digests of archive artifacts
- `inventory-meta.txt` — count/size snapshot matching this report

**Private documents:** nothing to archive (`private-documents` missing; DB counts zero).

---

## 4. Verification anchors (post-restore)

| Anchor | Value |
|--------|-------|
| Public file count | `196` |
| Public total bytes | `90099666` |
| Public total human | `87M` |
| Per-file digests | local `public-files.sha256` (196 lines) |
| Encrypted archive SHA-256 | `6bed635ff3a400124ffd42d0c4cbea4e394636331a090e84bc4a0adf412c426f` |
| Decrypt smoke test | Decrypt → `tar tz` listed **245** entries (files + dirs); success |

Post-volume restore check (later phase, not now):

1. Decrypt archive → extract into volume mount (merge; do not `rm -rf`).
2. Recompute `find … \| wc -l` and `du -sb`; match **196** / **90099666**.
3. Optionally `sha256sum -c` against rescued manifest.

---

## 5. Proceed / stop gate

| Gate | Status |
|------|--------|
| Production inspect succeeded | **Pass** |
| Rescue copy created outside container | **Pass** |
| Checksums / count+size recorded | **Pass** |
| Private critical files present? | **No** (disk missing + DB 0/0) |
| Safe to proceed to Phase 2C-2 code changes? | **Yes** — public rescued; private empty documented |

**2C-1 result: Pass**

---

## 6. Explicit non-actions (honored)

- No Railway volume create / mount
- No redeploy
- No S3 bucket create
- No Worker / Scheduler start
- No Phases 3–9
- No commit / push

---

*End of Phase 2C-1.*
