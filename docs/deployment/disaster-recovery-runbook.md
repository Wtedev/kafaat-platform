# Disaster Recovery Runbook

## RPO / RTO

| Metric | Target | Owner |
|--------|--------|-------|
| RPO | Define per org policy | Technical Lead |
| RTO | Define per org policy | Technical Lead |

Application does not enforce these values.

## Scenarios

### Database loss

1. Fail over to latest backup.
2. Reconcile privacy state (anonymizations, retention, exceptions).
3. Invalidate all sessions if breach suspected.

### Private storage loss

1. Restore files from backup.
2. Re-run export purge for expired files.
3. Verify CV download authorization.

### Key compromise

1. Rotate APP_KEY / IDENTITY_LOOKUP_KEY per key rotation plan.
2. Force password reset for admins.
3. Invalidate sessions.
4. Review audit/security logs.

## Contacts (roles)

- System Operator — infrastructure restore
- Privacy Officer — reconciliation approval
- Security Officer — incident classification
