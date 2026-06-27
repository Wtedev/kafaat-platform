# Retention Exceptions (Legal Holds)

## Table: `retention_exceptions`

Exceptions pause retention actions for matching resources. They do **not** modify underlying data or delete policies.

## Scopes

| Scope | Effect |
|-------|--------|
| `single_resource` | Blocks one `resource_id` |
| `user_all_resources` | Blocks all resources of type for a user |
| `resource_type_all` | Blocks entire resource type (requires elevated permission) |

## Reason codes

- `active_dispute`
- `regulatory_requirement`
- `security_investigation`
- `financial_record`
- `certificate_verification`
- `management_hold`

## Rules

- Requires `retention_exceptions.manage`
- Approved at creation; revocation does not trigger immediate deletion
- Open-ended holds require `review_at`
- Status: `active`, `expired`, `revoked`
- No hard delete of exception records

## Filament

Managed via **استثناءات الاحتفاظ** in the governance group.
