<?php

namespace App\Filament\Concerns;

use App\Models\User;
use App\Services\Rbac\StaffNavigationAuthorization;

/**
 * يضبط ظهور عنصر القائمة الجانبية وصلاحية الوصول للمورد عبر Gate (صلاحيات Spatie).
 */
trait RegistersNavigationByPermission
{
    /**
     * يجب أن يمتلك المستخدم كل الصلاحيات المذكورة (AND).
     *
     * @return list<string>
     */
    protected static function requiredNavigationPermissions(): array
    {
        return [];
    }

    public static function canViewAny(): bool
    {
        $permissions = static::requiredNavigationPermissions();
        if ($permissions === []) {
            return parent::canViewAny();
        }

        $user = auth()->user();

        return StaffNavigationAuthorization::allowsAll($user instanceof User ? $user : null, $permissions);
    }

    public static function shouldRegisterNavigation(): bool
    {
        $permissions = static::requiredNavigationPermissions();
        if ($permissions === []) {
            return parent::shouldRegisterNavigation();
        }

        return static::canViewAny();
    }
}
