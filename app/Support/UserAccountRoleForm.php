<?php

namespace App\Support;

use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Validation\ValidationException;

/**
 * خيارات نوع الحساب والدور في نموذج المستخدم (موظف / مستفيد).
 */
final class UserAccountRoleForm
{
    public const TYPE_STAFF = 'staff';

    public const TYPE_BENEFICIARY = 'beneficiary';

    /**
     * @return array<string, string>
     */
    public static function accountTypeOptionsAr(): array
    {
        return [
            self::TYPE_STAFF => 'موظف',
            self::TYPE_BENEFICIARY => 'مستفيد',
        ];
    }

    /**
     * @return list<string>
     */
    public static function staffSpatieRoleNames(): array
    {
        return ['media_employee', 'pr_employee', 'training_manager', 'volunteering_manager'];
    }

    /**
     * @return list<string>
     */
    public static function beneficiarySpatieRoleNames(): array
    {
        return ['trainee', 'volunteer'];
    }

    /**
     * @return array<string, string>
     */
    public static function staffRoleSelectOptionsAr(): array
    {
        $out = [];
        foreach (self::staffSpatieRoleNames() as $name) {
            $out[$name] = RbacCatalog::roleArabicLabel($name);
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public static function beneficiaryRoleSelectOptionsAr(): array
    {
        $out = [];
        foreach (self::beneficiarySpatieRoleNames() as $name) {
            $out[$name] = RbacCatalog::roleArabicLabel($name);
        }

        return $out;
    }

    /**
     * قيمة «نوع الحساب» في النموذج (موظف / مستفيد فقط) من سجل المستخدم.
     */
    public static function formAccountTypeFromUser(User $user): string
    {
        if ($user->role_type === self::TYPE_STAFF) {
            return self::TYPE_STAFF;
        }

        return self::TYPE_BENEFICIARY;
    }

    /**
     * اسم دور Spatie الواحد المعروض في الحقل «الدور».
     */
    public static function resolvedSpatieRoleFromUser(User $user): ?string
    {
        if ($user->role_type === self::TYPE_STAFF) {
            foreach (self::staffSpatieRoleNames() as $name) {
                if ($user->hasRole($name)) {
                    return $name;
                }
            }

            return null;
        }

        foreach (self::beneficiarySpatieRoleNames() as $name) {
            if ($user->hasRole($name)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * تسمية عربية لأول دور Spatie يظهر في الجدول.
     */
    public static function tablePrimaryRoleLabelAr(User $user): string
    {
        $names = $user->relationLoaded('roles')
            ? $user->roles->pluck('name')->all()
            : $user->roles()->pluck('name')->all();

        $priority = [
            'admin',
            'media_pr',
            ...self::staffSpatieRoleNames(),
            'staff',
            ...self::beneficiarySpatieRoleNames(),
            'beneficiary',
        ];

        foreach ($priority as $name) {
            if (in_array($name, $names, true)) {
                return RbacCatalog::roleArabicLabel($name);
            }
        }

        return '—';
    }

    /**
     * عرض «نوع الحساب» في الجدول (موظف / مستفيد / مدير النظام).
     */
    public static function tableAccountTypeLabelAr(User $user): string
    {
        if ($user->role_type === 'admin' || $user->hasRole('admin')) {
            return 'مدير النظام';
        }

        if ($user->role_type === self::TYPE_STAFF) {
            return 'موظف';
        }

        return 'مستفيد';
    }

    /**
     * @throws ValidationException
     */
    public static function assertValidCombination(string $accountType, string $spatieRole): void
    {
        $allowed = match ($accountType) {
            self::TYPE_STAFF => self::staffSpatieRoleNames(),
            self::TYPE_BENEFICIARY => self::beneficiarySpatieRoleNames(),
            default => [],
        };

        if (! in_array($spatieRole, $allowed, true)) {
            throw ValidationException::withMessages([
                'data.assigned_role' => 'الدور المحدد لا يتوافق مع نوع الحساب.',
            ]);
        }
    }
}
