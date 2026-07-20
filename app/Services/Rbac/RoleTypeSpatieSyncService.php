<?php

namespace App\Services\Rbac;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

/**
 * Transition helper: Spatie is the primary role source; users.role_type remains dual-written.
 *
 * Sync directions:
 * - role_type → Spatie: migrate / repair Spatie from the legacy column
 * - Spatie → role_type: rollback companion (restore column from Spatie)
 *
 * Staff permissions are never granted here — only explicit direct permissions remain.
 */
final class RoleTypeSpatieSyncService
{
    public const DIRECTION_ROLE_TYPE_TO_SPATIE = 'role_type_to_spatie';

    public const DIRECTION_SPATIE_TO_ROLE_TYPE = 'spatie_to_role_type';

    public const LOG_CHANNEL = 'role_sync';

    /**
     * Priority when a user somehow has multiple application Spatie roles.
     *
     * @var list<string>
     */
    private const SPATIE_ROLE_PRIORITY = [
        RbacCatalog::ROLE_ADMIN,
        RbacCatalog::ROLE_STAFF,
        RbacCatalog::ROLE_VOLUNTEER,
        RbacCatalog::ROLE_BENEFICIARY,
    ];

    /**
     * Map users.role_type (incl. legacy) → canonical Spatie application role.
     */
    public function mapRoleTypeToSpatie(?string $roleType): ?string
    {
        $normalized = strtolower(trim((string) $roleType));

        return match ($normalized) {
            RbacCatalog::ROLE_ADMIN => RbacCatalog::ROLE_ADMIN,
            RbacCatalog::ROLE_STAFF => RbacCatalog::ROLE_STAFF,
            RbacCatalog::ROLE_BENEFICIARY, 'trainee' => RbacCatalog::ROLE_BENEFICIARY,
            RbacCatalog::ROLE_VOLUNTEER => RbacCatalog::ROLE_VOLUNTEER,
            default => null,
        };
    }

    /**
     * Map canonical Spatie role → users.role_type value for dual-write / rollback.
     */
    public function mapSpatieToRoleType(?string $spatieRole): ?string
    {
        return match ($spatieRole) {
            RbacCatalog::ROLE_ADMIN => RbacCatalog::ROLE_ADMIN,
            RbacCatalog::ROLE_STAFF => RbacCatalog::ROLE_STAFF,
            RbacCatalog::ROLE_BENEFICIARY => RbacCatalog::ROLE_BENEFICIARY,
            RbacCatalog::ROLE_VOLUNTEER => RbacCatalog::ROLE_VOLUNTEER,
            default => null,
        };
    }

    /**
     * Primary application Spatie role for a user (admin > staff > volunteer > beneficiary).
     */
    public function primarySpatieRole(User $user): ?string
    {
        $user->loadMissing('roles');

        $names = $user->roles
            ->pluck('name')
            ->map(fn ($n) => (string) $n)
            ->all();

        foreach (self::SPATIE_ROLE_PRIORITY as $role) {
            if (in_array($role, $names, true)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     user_id: int,
     *     email: string,
     *     role_type: string|null,
     *     spatie_role: string|null,
     *     expected_spatie: string|null,
     *     expected_role_type: string|null,
     *     kind: string
     * }|null
     */
    public function driftForUser(User $user): ?array
    {
        $roleType = $user->role_type !== null ? (string) $user->role_type : null;
        $spatie = $this->primarySpatieRole($user);
        $expectedSpatie = $this->mapRoleTypeToSpatie($roleType);
        $expectedRoleType = $this->mapSpatieToRoleType($spatie);

        $kind = null;

        if ($expectedSpatie === null && $spatie === null) {
            $kind = 'no_role';
        } elseif ($expectedSpatie === null && $spatie !== null) {
            $kind = 'unknown_role_type';
        } elseif ($expectedSpatie !== null && $spatie === null) {
            $kind = 'missing_spatie';
        } elseif ($expectedSpatie !== $spatie) {
            $kind = 'mismatch';
        } elseif ($expectedRoleType !== null && $roleType !== $expectedRoleType) {
            // e.g. trainee vs beneficiary — column not normalized
            $kind = 'role_type_legacy';
        }

        if ($kind === null) {
            return null;
        }

        return [
            'user_id' => (int) $user->id,
            'email' => (string) $user->email,
            'role_type' => $roleType,
            'spatie_role' => $spatie,
            'expected_spatie' => $expectedSpatie,
            'expected_role_type' => $expectedRoleType,
            'kind' => $kind,
        ];
    }

    /**
     * @return array{
     *     scanned: int,
     *     drift_count: int,
     *     by_kind: array<string, int>,
     *     entries: list<array<string, mixed>>
     * }
     */
    public function reportDrift(?int $limit = null): array
    {
        $entries = [];
        $byKind = [];
        $scanned = 0;

        User::query()->orderBy('id')->chunkById(200, function (Collection $users) use (&$entries, &$byKind, &$scanned, $limit): bool {
            foreach ($users as $user) {
                $scanned++;
                $drift = $this->driftForUser($user);
                if ($drift === null) {
                    continue;
                }

                $byKind[$drift['kind']] = ($byKind[$drift['kind']] ?? 0) + 1;
                $entries[] = $drift;

                if ($limit !== null && count($entries) >= $limit) {
                    return false;
                }
            }

            return true;
        });

        return [
            'scanned' => $scanned,
            'drift_count' => count($entries),
            'by_kind' => $byKind,
            'entries' => $entries,
        ];
    }

    /**
     * Sync Spatie from role_type (primary migration). Dual-writes normalized role_type.
     * Does not grant staff permissions. Optionally demotes extra admins without expanding perms.
     *
     * @return array{
     *     mode: string,
     *     direction: string,
     *     scanned: int,
     *     changed: int,
     *     skipped: int,
     *     demoted_extra_admins: int,
     *     changes: list<array<string, mixed>>
     * }
     */
    public function syncFromRoleType(bool $dryRun = true, bool $enforceSingleAdmin = true): array
    {
        $mode = $dryRun ? 'dry_run' : 'apply';
        $changes = [];
        $scanned = 0;
        $changed = 0;
        $skipped = 0;

        $this->audit($mode, 'sync_start', [
            'direction' => self::DIRECTION_ROLE_TYPE_TO_SPATIE,
            'enforce_single_admin' => $enforceSingleAdmin,
        ]);

        User::query()->with(['roles', 'permissions'])->orderBy('id')->chunkById(100, function (Collection $users) use ($dryRun, $mode, &$changes, &$scanned, &$changed, &$skipped): void {
            foreach ($users as $user) {
                /** @var User $user */
                $scanned++;
                $target = $this->mapRoleTypeToSpatie($user->role_type);

                if ($target === null) {
                    $skipped++;
                    $this->audit($mode, 'skip_unknown_or_empty_role_type', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'role_type' => $user->role_type,
                        'spatie_role' => $this->primarySpatieRole($user),
                    ]);

                    continue;
                }

                $current = $this->primarySpatieRole($user);
                $normalizedRoleType = $this->mapSpatieToRoleType($target);
                $needsSpatie = $current !== $target || $user->roles->count() !== 1 || ! $user->hasRole($target);
                $needsRoleType = (string) $user->role_type !== (string) $normalizedRoleType;
                $needsAdminPermClear = $target === RbacCatalog::ROLE_ADMIN && $user->permissions->isNotEmpty();
                $needsPortalPermClear = in_array($target, [RbacCatalog::ROLE_BENEFICIARY, RbacCatalog::ROLE_VOLUNTEER], true)
                    && $user->permissions->isNotEmpty();

                if (! $needsSpatie && ! $needsRoleType && ! $needsAdminPermClear && ! $needsPortalPermClear) {
                    $skipped++;

                    continue;
                }

                $change = [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'from_spatie' => $current,
                    'to_spatie' => $target,
                    'from_role_type' => $user->role_type,
                    'to_role_type' => $normalizedRoleType,
                    'clear_direct_permissions' => $needsAdminPermClear || $needsPortalPermClear,
                    'preserve_staff_direct_permissions' => $target === RbacCatalog::ROLE_STAFF,
                ];

                $changes[] = $change;
                $changed++;

                $this->audit($mode, 'user_sync_planned', $change);

                if ($dryRun) {
                    continue;
                }

                $user->syncRoles([$target]);

                if ($needsAdminPermClear || $needsPortalPermClear) {
                    $user->syncPermissions([]);
                }

                if ($needsRoleType) {
                    $user->update(['role_type' => $normalizedRoleType]);
                }

                $this->audit($mode, 'user_sync_applied', $change);
            }
        });

        $demoted = 0;
        if ($enforceSingleAdmin) {
            $demoted = $this->enforceSingleAdminWithoutPermissionExpansion($dryRun, $mode);
        }

        if (! $dryRun) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $summary = [
            'mode' => $mode,
            'direction' => self::DIRECTION_ROLE_TYPE_TO_SPATIE,
            'scanned' => $scanned,
            'changed' => $changed,
            'skipped' => $skipped,
            'demoted_extra_admins' => $demoted,
            'changes' => $changes,
        ];

        $this->audit($mode, 'sync_complete', [
            'scanned' => $scanned,
            'changed' => $changed,
            'skipped' => $skipped,
            'demoted_extra_admins' => $demoted,
        ]);

        return $summary;
    }

    /**
     * Sync role_type from Spatie (rollback companion). Never touches permissions.
     *
     * @return array{
     *     mode: string,
     *     direction: string,
     *     scanned: int,
     *     changed: int,
     *     skipped: int,
     *     demoted_extra_admins: int,
     *     changes: list<array<string, mixed>>
     * }
     */
    public function syncToRoleType(bool $dryRun = true): array
    {
        $mode = $dryRun ? 'dry_run' : 'apply';
        $changes = [];
        $scanned = 0;
        $changed = 0;
        $skipped = 0;

        $this->audit($mode, 'sync_start', [
            'direction' => self::DIRECTION_SPATIE_TO_ROLE_TYPE,
        ]);

        User::query()->with('roles')->orderBy('id')->chunkById(100, function (Collection $users) use ($dryRun, $mode, &$changes, &$scanned, &$changed, &$skipped): void {
            foreach ($users as $user) {
                /** @var User $user */
                $scanned++;
                $spatie = $this->primarySpatieRole($user);
                $targetRoleType = $this->mapSpatieToRoleType($spatie);

                if ($targetRoleType === null) {
                    $skipped++;
                    $this->audit($mode, 'skip_no_spatie_application_role', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'role_type' => $user->role_type,
                    ]);

                    continue;
                }

                if ((string) $user->role_type === $targetRoleType) {
                    $skipped++;

                    continue;
                }

                $change = [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'from_role_type' => $user->role_type,
                    'to_role_type' => $targetRoleType,
                    'spatie_role' => $spatie,
                ];

                $changes[] = $change;
                $changed++;
                $this->audit($mode, 'role_type_sync_planned', $change);

                if ($dryRun) {
                    continue;
                }

                $user->update(['role_type' => $targetRoleType]);
                $this->audit($mode, 'role_type_sync_applied', $change);
            }
        });

        $summary = [
            'mode' => $mode,
            'direction' => self::DIRECTION_SPATIE_TO_ROLE_TYPE,
            'scanned' => $scanned,
            'changed' => $changed,
            'skipped' => $skipped,
            'demoted_extra_admins' => 0,
            'changes' => $changes,
        ];

        $this->audit($mode, 'sync_complete', [
            'scanned' => $scanned,
            'changed' => $changed,
            'skipped' => $skipped,
        ]);

        return $summary;
    }

    /**
     * Keep at most one protected admin. Demotes extras to staff without granting permissions.
     */
    public function enforceSingleAdminWithoutPermissionExpansion(bool $dryRun, string $mode = 'apply'): int
    {
        $adminEmail = config('app.admin_email');

        $primary = null;
        if (is_string($adminEmail) && $adminEmail !== '') {
            $primary = User::query()->where('email', $adminEmail)->first();
        }

        if ($primary === null) {
            $primary = User::query()
                ->where(function ($q): void {
                    $q->where('role_type', RbacCatalog::ROLE_ADMIN)
                        ->orWhereHas('roles', fn ($r) => $r->where('name', RbacCatalog::ROLE_ADMIN));
                })
                ->orderBy('id')
                ->first();
        }

        if ($primary === null) {
            return 0;
        }

        $extras = User::query()
            ->whereKeyNot($primary->id)
            ->where(function ($q): void {
                $q->where('role_type', RbacCatalog::ROLE_ADMIN)
                    ->orWhereHas('roles', fn ($r) => $r->where('name', RbacCatalog::ROLE_ADMIN));
            })
            ->get();

        $demoted = 0;

        foreach ($extras as $user) {
            $change = [
                'user_id' => $user->id,
                'email' => $user->email,
                'action' => 'demote_extra_admin_to_staff',
                'primary_admin_id' => $primary->id,
                'note' => 'no_permission_grant',
            ];
            $this->audit($mode, 'demote_extra_admin', $change);
            $demoted++;

            if ($dryRun) {
                continue;
            }

            $user->syncRoles([RbacCatalog::ROLE_STAFF]);
            $user->update(['role_type' => RbacCatalog::ROLE_STAFF]);
            // Intentionally do NOT grantAllAssignable — preserve existing direct perms only.
        }

        if (! $dryRun) {
            $primaryNeedsRole = ! $primary->hasRole(RbacCatalog::ROLE_ADMIN)
                || (string) $primary->role_type !== RbacCatalog::ROLE_ADMIN;

            if ($primaryNeedsRole || $primary->permissions()->exists()) {
                $primary->syncRoles([RbacCatalog::ROLE_ADMIN]);
                $primary->syncPermissions([]);
                $primary->update([
                    'role_type' => RbacCatalog::ROLE_ADMIN,
                    'is_active' => true,
                ]);
            }
        }

        return $demoted;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function audit(string $mode, string $event, array $context = []): void
    {
        Log::channel(self::LOG_CHANNEL)->info('[role_sync] '.$event, array_merge([
            'mode' => $mode,
        ], $context));
    }
}
