# Account Deletion Workflow

## Principle

Account deletion is **anonymization**, not hard delete. The `users` row remains to preserve certificate verification and restricted retention evidence.

## User journey

1. Beneficiary submits account deletion request (`POST /portal/account-deletion`) with password re-verification.
2. Privacy officer reviews in Filament (`PrivacyRequestResource`).
3. Officer creates and approves a `DataDeletionPlan` generated from retention policies.
4. Executor with `privacy_requests.execute` re-verifies and runs `PersonalDataDeletionService`.
5. Account becomes `anonymized`; login blocked; operational lists exclude the account.

## States

### Privacy request

`submitted` → `under_review` → `approved` → `processing` → `completed`

Also: `identity_verification_required`, `rejected`, `cancelled`, `failed`

### Account status

`active`, `inactive`, `deletion_pending`, `deletion_processing`, `anonymized`

## Execution order

1. Authentication data invalidation
2. Candidate pool withdrawal
3. Notifications deletion
4. User documents / CV file deletion
5. Privacy export file deletion
6. Activity log deletion
7. Restricted retention markers (registrations, attendance, certificates)
8. Policy/consent history redaction
9. Audit/security log redaction
10. Profile anonymization
11. Account anonymization

## Direct deletion blocked

- `$user->delete()` / `forceDelete()`
- `User::query()->delete()`
- Filament Delete/BulkDelete actions
- `UserPolicy::delete()` always false
- FK cascades changed to `RESTRICT` on user_id

## Demo seeder purge

`CleanDemoDataSeeder` uses `DemoEnvironmentUserPurge` only when:

- `APP_DEMO_DATA_PURGE_ENABLED=true`
- non-production
- console only

This is **not** part of the production deletion workflow.

## Backups

Anonymization does not erase data from backup archives. Operational restore must run privacy reconciliation before returning anonymized accounts to active use.
