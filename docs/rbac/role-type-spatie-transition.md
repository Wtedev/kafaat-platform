# Transition: Spatie primary, `role_type` compatibility

> Phase for roadmap item 2.1 — Spatie is the source of truth for application roles; `users.role_type` is dual-written and must **not** be dropped yet.

## Conversion rules

| `users.role_type` | Spatie role | Notes |
| --- | --- | --- |
| `admin` | `admin` | Clears direct permissions (admin gets all via role). At most one protected admin (`ADMIN_EMAIL` or oldest). Extra admins → `staff` **without** granting blanket permissions. |
| `staff` | `staff` | **Never** grants permissions — only keeps existing direct permissions. |
| `beneficiary` | `beneficiary` | Clears direct permissions (portal read via role). |
| `volunteer` | `volunteer` | Same as beneficiary. |
| `trainee` (legacy) | `beneficiary` | Dual-write normalizes column to `beneficiary`. |
| empty / unknown | — | Skipped; appears in drift as `no_role` / `unknown_role_type`. |

## Dual-write (compatibility)

- **Reads** (`isAdmin`, `isStaff`, `isPortalUser`, `canAccessPanel`, …): Spatie first, then `role_type` fallback.
- **Writes** (Filament create/edit, registration, seeders, sync commands): set Spatie role **and** `role_type` to the same canonical value.
- Column stays fillable; no migration drops it.

## Commands

```bash
# 1) Inspect drift (exit 1 if any drift)
php artisan roles:report-drift
php artisan roles:report-drift --limit=0 --json

# 2) Dry run: role_type → Spatie (default)
php artisan roles:sync-from-role-type

# 3) Apply + audit log
php artisan roles:sync-from-role-type --apply

# 4) Verify drift = 0 (users with empty/unknown role_type may remain)
php artisan roles:report-drift
```

Audit channel: `storage/logs/role-sync.log` (config key `role_sync`). Each line includes `mode=dry_run|apply`.

Skip single-admin enforcement:

```bash
php artisan roles:sync-from-role-type --apply --no-enforce-single-admin
```

## Rollback

Dual-write kept, so either direction is reversible:

```bash
# Restore column from Spatie (permissions untouched)
php artisan roles:sync-to-role-type          # dry-run
php artisan roles:sync-to-role-type --apply

# Or re-apply Spatie from column again
php artisan roles:sync-from-role-type --apply
```

To undo an apply without DB restore: use `roles:sync-to-role-type --apply` if Spatie is correct, or restore from backup / reverse the planned changes listed in `role-sync.log`.

## Transition phases (safe rollout)

| Phase | Action | Status |
| --- | --- | --- |
| 1 | Spatie-first reads + dual-write on Filament/registration/seeders | ✅ In production code |
| 2 | `roles:report-drift` in deploy checklist; fix drift with `roles:sync-from-role-type --apply` | Operational |
| 3 | Stop new code from querying `users.role_type` directly (use User helpers / scopes) | Ongoing |
| 4 | Backfill `role_type` from Spatie for legacy rows (`roles:sync-to-role-type`) | On demand |
| 5 | Drop `users.role_type` column (migration) | **Not started** — requires drift = 0 + product sign-off |

Do **not** drop the column until phase 5 is explicitly approved.

1. After `--apply`, `roles:report-drift` shows `drift: 0` for users with known role types.
2. Login, `/portal`, and Filament `/admin` still work (helpers still accept either source).
3. Staff permissions unchanged by sync (no `grantAllAssignable`).
4. At most one protected admin when enforcement is enabled.

## Inventory (usage hotspots)

| Area | What |
| --- | --- |
| `app/Models/User.php` | Helpers + `canAccessPanel` + scopes (Spatie-first reads) |
| `app/Services/Rbac/StaffPermissionService.php` | Four-role migrate + `enforceSingleAdmin` (grants perms on demote — separate from this sync) |
| `app/Support/UserAccountRoleForm.php` + Filament `UserResource` | Dual-write on create/edit |
| `app/Services/Auth/UserRegistrationService.php` | Sets beneficiary Spatie + `role_type` |
| Middleware / portal / Filament | Via User helpers |
| Seeders | Dual-write already |
| Inbox / exports / policies queries | Still filter `role_type` in SQL (compat) |

## Files

- `app/Services/Rbac/RoleTypeSpatieSyncService.php`
- `app/Console/Commands/RolesReportDriftCommand.php`
- `app/Console/Commands/RolesSyncFromRoleTypeCommand.php`
- `app/Console/Commands/RolesSyncToRoleTypeCommand.php`
- `tests/Feature/Rbac/RoleTypeSpatieSyncTest.php`
- `config/logging.php` (`role_sync` channel)
