# Retention Resource Catalog

Resources are defined centrally in `App\Services\Privacy\Retention\RetentionResourceCatalog`. Administrators select **codes** from Filament — never table names, models, or SQL.

## Schedulable (operational)

| Code | Triggers | Actions | Files |
|------|----------|---------|-------|
| `privacy_export_files` | `expired_at` | delete | yes |
| `email_verification_codes` | `expired_at` | delete | no |
| `password_reset_tokens` | `created_at` | delete | no |
| `sessions` | `last_activity_at` | delete | no |
| `in_app_notifications` | `created_at` | delete | no |
| `user_activity_logs` | `created_at`, `account_anonymized_at` | delete, anonymize | no |
| `audit_logs` | `created_at` | delete, anonymize | no (protected actions excluded) |
| `security_logs` | `created_at` | delete, anonymize | no (critical severity excluded) |
| `email_logs` | `created_at` | delete, anonymize | no |

## Restricted (no scheduled delete by default)

| Code | Default |
|------|---------|
| `certificates` | retain_restricted |
| `attendance_records` | retain_restricted |
| `program_registrations` | retain_restricted |
| `path_registrations` | retain_restricted |
| `volunteer_registrations` | retain_restricted |
| `candidate_pool_consent_events` | retain_restricted |
| `privacy_policy_acknowledgements` | retain_restricted |

## Handler mapping

Each schedulable resource has a dedicated handler under `App\Services\Privacy\Retention\Handlers\`.

## Account deletion integration

`config/privacy_deletion.php` and `RetentionPolicyResolver` continue to govern **account deletion planning**. The retention engine governs **scheduled time-based disposal**.
