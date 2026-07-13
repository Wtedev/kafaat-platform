<?php

namespace App\Support;

use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Database\Eloquent\Builder;

/**
 * تبويبات قائمة المستخدمين: أدمن+موظفون / مستفيدون / فريق تطوعي.
 */
final class UserDirectoryTabs
{
    public const TAB_SYSTEM = 'system';

    public const TAB_TRAINEES = 'trainees';

    public const TAB_VOLUNTEERS = 'volunteers';

    /** @return array<string, array{label: string}> */
    public static function tabDefinitions(): array
    {
        return [
            self::TAB_SYSTEM => ['label' => 'الموظفون والأدمن'],
            self::TAB_TRAINEES => ['label' => 'المستفيدون'],
            self::TAB_VOLUNTEERS => ['label' => 'الفريق التطوعي'],
        ];
    }

    public static function isValidTab(string $tab): bool
    {
        return array_key_exists($tab, self::tabDefinitions());
    }

    public static function actorCanViewTab(?User $actor, string $tab): bool
    {
        if ($actor === null) {
            return false;
        }

        if ($actor->isAdmin()) {
            return self::isValidTab($tab);
        }

        $allowedRoles = BeneficiaryRoleManagement::allowedSpatieRoleNamesForManager($actor);

        return match ($tab) {
            self::TAB_SYSTEM => false,
            self::TAB_TRAINEES => in_array(RbacCatalog::ROLE_BENEFICIARY, $allowedRoles, true),
            self::TAB_VOLUNTEERS => in_array(RbacCatalog::ROLE_VOLUNTEER, $allowedRoles, true),
            default => false,
        };
    }

    /** @return list<string> */
    public static function visibleTabKeysFor(?User $actor): array
    {
        if ($actor === null) {
            return [];
        }

        $keys = [];
        foreach (array_keys(self::tabDefinitions()) as $key) {
            if (self::actorCanViewTab($actor, $key)) {
                $keys[] = $key;
            }
        }

        if ($keys === [] && $actor->can('users.view')) {
            return [self::TAB_TRAINEES];
        }

        return $keys;
    }

    public static function defaultPlatformRoleForTab(string $tab): ?string
    {
        return match ($tab) {
            self::TAB_SYSTEM => UserAccountRoleForm::TYPE_STAFF,
            self::TAB_TRAINEES => UserAccountRoleForm::TYPE_BENEFICIARY,
            self::TAB_VOLUNTEERS => UserAccountRoleForm::TYPE_VOLUNTEER,
            default => null,
        };
    }

    public static function applyTabScope(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            self::TAB_SYSTEM => self::scopeSystemStaff($query),
            self::TAB_TRAINEES => self::scopeTrainees($query),
            self::TAB_VOLUNTEERS => self::scopeVolunteers($query),
            default => $query,
        };
    }

    public static function scopeSystemStaff(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('role_type', RbacCatalog::ROLE_ADMIN)
                ->orWhere('role_type', RbacCatalog::ROLE_STAFF)
                ->orWhereHas('roles', fn (Builder $r) => $r->whereIn('name', [
                    RbacCatalog::ROLE_ADMIN,
                    RbacCatalog::ROLE_STAFF,
                ]));
        });
    }

    public static function scopeTrainees(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $q): void {
                $q->whereHas('roles', fn (Builder $r) => $r->whereIn('name', [
                    RbacCatalog::ROLE_BENEFICIARY,
                    'trainee',
                ]))
                    ->orWhereIn('role_type', [RbacCatalog::ROLE_BENEFICIARY, 'trainee']);
            })
            ->whereDoesntHave('roles', fn (Builder $r) => $r->where('name', RbacCatalog::ROLE_VOLUNTEER))
            ->where('role_type', '!=', RbacCatalog::ROLE_ADMIN)
            ->where('role_type', '!=', RbacCatalog::ROLE_STAFF)
            ->where('role_type', '!=', RbacCatalog::ROLE_VOLUNTEER)
            ->whereDoesntHave('roles', fn (Builder $r) => $r->whereIn('name', [
                RbacCatalog::ROLE_ADMIN,
                RbacCatalog::ROLE_STAFF,
            ]));
    }

    public static function scopeVolunteers(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereHas('roles', fn (Builder $r) => $r->where('name', RbacCatalog::ROLE_VOLUNTEER))
                ->orWhere('role_type', RbacCatalog::ROLE_VOLUNTEER);
        });
    }
}
