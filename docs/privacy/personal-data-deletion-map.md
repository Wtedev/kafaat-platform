# Personal Data Deletion Map

Production map for approved account anonymization. No hard delete of `users` rows.

| Resource | Relationship | Delete | Anonymize | Retain restricted | Decision |
| -------- | ------------- | ------ | --------- | ----------------- | -------- |
| users | primary account | — | yes | — | Anonymize operational fields; row kept |
| profiles | 1:1 user | partial | yes | — | Clear PII; remove avatar/CV refs |
| user_documents / CV | user files | yes | — | — | Delete files + mark deleted |
| avatars | profile | yes | — | — | Delete from public disk |
| privacy_export_files | user exports | yes | — | — | Delete file + mark deleted |
| sessions | auth | yes | — | — | Invalidate |
| password_reset_tokens | auth | yes | — | — | Delete |
| email_verification_codes | auth | yes | — | — | Delete |
| in_app_notifications | user inbox | yes | — | — | Delete |
| user_activity_logs | portal activity | yes | — | — | Delete on anonymization |
| candidate_pool_preferences | consent state | — | yes | — | Withdraw + restrict |
| candidate_pool_consent_events | audit trail | — | — | yes | Keep; redact IP/UA |
| privacy_policy_acknowledgements | compliance | — | — | yes | Keep; redact IP/UA |
| program/path/volunteer registrations | training | — | — | yes | Keep until admin retention period |
| attendance | via registrations | — | — | yes | Keep until admin retention period |
| certificates | verification | — | — | yes | Keep snapshot; link to anonymized user |
| audit_logs | compliance | — | — | yes | Keep; redact IP/UA on target rows |
| security_logs | security | — | — | yes | Detach user_id; redact identifiers |
| email_logs | mail audit | — | — | yes | No user_id FK; retain by policy |

Administrative periods for certificates, registrations, and attendance are **not invented**. Enabled retention policies use `retain_restricted` with `retention_period_days = null` until approved.
