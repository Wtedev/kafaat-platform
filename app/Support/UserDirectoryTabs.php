<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * تبويبات قائمة المستخدمين: مسؤولو النظام / المستفيدون / الفريق التطوعي.
 */
final class UserDirectoryTabs
{
    public const TAB_SYSTEM = 'system';

    public const TAB_TRAINEES = 'trainees';

    public const TAB_VOLUNTEERS = 'volunteers';

    /**
     * @return array<string, array{label: string}>
     */
    public static function tabDefinitions(): array
    {
        return [
            self::TAB_SYSTEM => ['label' => 'مسؤولو النظام'],
            self::TAB_TRAINEES => ['label' => 'المستفيدون (متدرب)'],
            self::TAB_VOLUNTEERS => ['label' => 'الفريق التطوعي (متطوع)'],
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

        if ($actor->can('manage_roles')) {
            return self::isValidTab($tab);
        }

        $allowedRoles = BeneficiaryRoleManagement::allowedSpatieRoleNamesForManager($actor);

        return match ($tab) {
            self::TAB_SYSTEM => false,
            self::TAB_TRAINEES => in_array('trainee', $allowedRoles, true)
                || in_array('beneficiary', $allowedRoles, true),
            self::TAB_VOLUNTEERS => in_array('volunteer', $allowedRoles, true),
            default => false,
        };
    }

    /**
     * @return list<string>
     */
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
            self::TAB_TRAINEES => 'trainee',
            self::TAB_VOLUNTEERS => 'volunteer',
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
        $staffRoles = UserAccountRoleForm::staffSpatieRoleNames();

        return $query->where(function (Builder $q) use ($staffRoles): void {
            $q->where('role_type', 'admin')
                ->orWhereHas('roles', fn (Builder $r) => $r->where('name', 'admin'))
                ->orWhere('role_type', 'staff')
                ->orWhereHas('roles', fn (Builder $r) => $r->whereIn('name', $staffRoles));
        });
    }

    public static function scopeTrainees(Builder $query): Builder
    {
        $staffRoles = UserAccountRoleForm::staffSpatieRoleNames();

        return $query
            ->where(function (Builder $q): void {
                $q->whereHas('roles', fn (Builder $r) => $r->where('name', 'trainee'))
                    ->orWhereIn('role_type', ['beneficiary', 'trainee']);
            })
            ->whereDoesntHave('roles', fn (Builder $r) => $r->where('name', 'volunteer'))
            ->where('role_type', '!=', 'admin')
            ->where('role_type', '!=', 'staff')
            ->whereDoesntHave('roles', fn (Builder $r) => $r->where('name', 'admin'))
            ->whereDoesntHave('roles', fn (Builder $r) => $r->whereIn('name', $staffRoles));
    }

    public static function scopeVolunteers(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereHas('roles', fn (Builder $r) => $r->where('name', 'volunteer'))
                ->orWhere('role_type', 'volunteer');
        });
    }
}
