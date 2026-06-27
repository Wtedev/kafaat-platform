# Dependency Audit — Phase 07

Run: `composer audit`, `npm audit`

## Policy

- **Critical/High:** must remediate or document mitigation + owner before production.
- **Medium:** review exploitability in this codebase; patch when vendor release available.
- **Low:** track in backlog.

## Composer (2026-06-27 snapshot)

| Package | Advisory | Severity | Exposure | Action |
|---------|----------|----------|----------|--------|
| symfony/routing | CVE-2026-45065 | Medium | Route generation — review signed URLs usage | Monitor Laravel/Symfony updates |

No automatic `composer update` performed in Phase 07.

## NPM

Frontend toolchain: Vite + Tailwind. Run `npm audit --audit-level=high` in CI (non-blocking with `|| true` until policy formalized).

## Abandoned packages

Review `composer outdated --direct` periodically; no mass updates in hardening phase.
