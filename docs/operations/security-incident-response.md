# Security Incident Response

## Severity levels

| Level | Examples | Initial action |
|-------|----------|----------------|
| Critical | Auth bypass, data leak, key compromise | Isolate, preserve logs, notify Privacy Officer |
| High | Repeated IDOR attempts, export abuse | Rate limit review, block IP at edge |
| Medium | Failed job spike, scheduler stopped | Restore ops, root cause |
| Low | Single failed login | Monitor |

## Evidence preservation

- Audit logs (`audit_logs`)
- Security logs (`security_logs`)
- Request IDs (`X-Request-ID`)
- Do **not** copy PII into incident tickets.

## Steps

1. Detect (monitoring / report).
2. Triage (Security Officer).
3. Contain (disable account, pause queue, maintenance mode).
4. Eradicate (patch, rotate keys).
5. Recover (restore, reconciliation).
6. Post-incident review within 5 business days.

## Regulatory

Coordinate with Privacy Officer for breach notification decisions — **not automated by this application**.
