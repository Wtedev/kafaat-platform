# Retention Execution Workflow

## Preview (`privacy:retention-preview`)

1. Load policy (or all active schedulable policies)
2. Compute cutoff: `reference_time - (retention_period_days + grace_period_days)`
3. Query eligible records via handler
4. Exclude active exceptions
5. Create `retention_runs` with `mode=preview`
6. Optionally create `retention_run_items` for file/resumable resources
7. **No data mutation**

## Execute (`privacy:apply-retention`)

Same eligibility logic as preview. Additional guards:

- Policy must be `active` with approved period
- Distributed lock per policy UUID
- Batch via `chunkById` / query `chunk`
- Idempotent run items (completed items skipped on resume)
- Partial failures recorded with sanitized `failure_code` (no stack traces)

## Resume

```bash
php artisan privacy:apply-retention --resume=<run-uuid>
```

Re-processes pending/failed items only.

## Manual Filament execute

Small batches only (`PRIVACY_RETENTION_MANUAL_MAX_ITEMS`). Large runs must use CLI/scheduler.

## Audit events

- `retention_run.started|completed|completed_with_failures|failed|resumed`
- `retention_item.failed` (failure code only)
