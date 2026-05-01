<?php

namespace App\Filament\Concerns;

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
        if ($user === null) {
            return false;
        }

        foreach ($permissions as $permission) {
            if (! $user->can($permission)) {
                return false;
            }
        }

        return true;
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
