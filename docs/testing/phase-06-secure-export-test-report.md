# Phase 06 Secure Export — Test Report

## New tests

`tests/Feature/PrivacyPhase06/PersonalDataExportTest.php` (9 tests)

## Coverage

- Export request + password + audit/activity
- Duplicate active request block
- Job dispatch + ZIP generation on private disk
- Allowlist in account.json
- Owner download + headers + audit
- IDOR block
- Purge command + dry-run
- Anonymized account stops generation

## Full suite ×3

| Run | Passed |
| --- | --- |
| 1 | 185 |
| 2 | 185 |
| 3 | 185 |

## Random order

| Seed | Result |
| --- | --- |
| 6201 | OK |
| 6202 | OK |
| 6203 | OK |

## Privacy permutations

Phase04→05→06, Phase06→Baseline — Pass
