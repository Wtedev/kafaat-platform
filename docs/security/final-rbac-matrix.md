# Final RBAC Matrix (Summary)

Source: `App\Services\Rbac\RbacCatalog` and Filament policies.

## Principles

- Deny by default; explicit permissions required.
- Sensitive permissions excluded from broad admin roles (`admin`, `technical_admin`).
- No `Gate::before` global bypass.

## Role capabilities

| Capability | trainee | staff (varies) | privacy_officer | admin |
|------------|---------|----------------|-----------------|-------|
| Portal access | ✓ | — | — | — |
| Filament access | — | partial | ✓ | ✓ |
| View beneficiaries (masked) | — | ✓ | ✓ | ✓ |
| View full identity | — | permission | — | excluded |
| Download CV | — | permission | — | excluded |
| Export beneficiaries | — | permission | — | partial |
| Privacy requests review | — | — | ✓ | excluded |
| Account deletion execute | — | permission | — | excluded |
| Retention policy activate | — | — | excluded | excluded |
| Retention execute | — | — | excluded | excluded |
| Audit logs view | — | permission | ✓ | partial |
| Security logs view | — | permission | — | partial |

## Sensitive permissions (never auto-granted to broad admin)

- `beneficiaries.identity.view_full`
- `beneficiaries.identity.search_exact`
- `security_logs.view_sensitive_metadata`
- `users.delete`
- `privacy_requests.execute`
- `privacy_requests.approve` / `reject`
- `retention_policies.manage` / `activate`
- `retention_runs.execute`
- `retention_exceptions.manage`

## Separation of duties (supported, requires organizational assignment)

| Function | Suggested permission holder |
|----------|----------------------------|
| Draft retention policies | Privacy Officer |
| Activate retention policies | Senior Privacy Officer |
| Execute retention runs | System Operator |
| View runs / audit | Auditor |
| Legal holds (resource-type-wide) | Security Admin + activate permission |

See also: `docs/security/retention-access-control.md`.
