# Privacy Operations Runbook

## Daily

- Review failed privacy export jobs (`failed_jobs` + audit `privacy_export.failed`).
- Confirm scheduler ran (`privacy:retention-status`).

## Weekly

- Review retention preview/execute runs in Filament.
- Verify no draft policies accidentally activated.

## On privacy request

1. Assign Privacy Officer.
2. Verify identity for sensitive actions.
3. Document in privacy request events (no PII in internal notes beyond need).

## On account deletion approval

1. Confirm deletion plan.
2. Execute only with `privacy_requests.execute` permission.
3. Verify anonymization — **irreversible**.

## Export operations

- Requires queue worker.
- Monitor `privacy_export.*` audit events.

See retention docs under `docs/privacy/`.
