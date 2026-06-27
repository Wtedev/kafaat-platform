# Retention Access Control

## Permissions

| Permission | Purpose |
|------------|---------|
| `retention_policies.view` | List/view policies |
| `retention_policies.create` | Create drafts |
| `retention_policies.update_draft` | Edit drafts |
| `retention_policies.manage` | Legacy superset (create/update) |
| `retention_policies.preview` | Trigger preview runs |
| `retention_policies.activate` | Activate/deactivate policies |
| `retention_runs.view` | Read run history |
| `retention_runs.execute` | Manual/CLI execution (not auto-granted) |
| `retention_exceptions.manage` | Legal holds |

## Separation of duties (supported, not enforced by default)

| Role | Suggested permissions |
|------|----------------------|
| Privacy Officer | view, create, update_draft, preview |
| Senior Privacy Officer | activate |
| System Operator | execute (CLI) |
| Auditor | view, retention_runs.view |
| Security Admin | resource-type-wide exceptions (with activate permission) |

## Security log events

- `retention.execution_denied`
- `retention.verification_failed`
- `retention.concurrent_run_blocked`
- `retention.unauthorized_resource_attempt`

Sensitive permissions are excluded from broad admin roles via `RbacCatalog::permissionsExcludedFromBroadRoles()`.
