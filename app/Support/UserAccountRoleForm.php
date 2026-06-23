<?php

namespace App\Support;

use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Validation\ValidationException;

/**
 * أدوار المنصة الموحّدة في واجهة المستخدم (حقل واحد بدل نوع حساب + دور Spatie).
 */
final class UserAccountRoleForm
{
    public const TYPE_STAFF = 'staff';

    public const TYPE_BENEFICIARY = 'beneficiary';

    /**
     * الأدوار القابلة للتعيين من الواجهة (مفتاح واحد → دور Spatie + role_type).
     *
     * @var array<string, array{spatie: string, role_type: string}>
     */
    public const PLATFORM_ROLES = [
        'public_relations' => ['spatie' => 'public_relations', 'role_type' => self::TYPE_STAFF],
        'media' => ['spatie' => 'media', 'role_type' => self::TYPE_STAFF],
        'training_enablement_manager' => ['spatie' => 'training_enablement_manager', 'role_type' => self::TYPE_STAFF],
        'programs_activities_manager' => ['spatie' => 'programs_activities_manager', 'role_type' => self::TYPE_STAFF],
        'volunteering_manager' => ['spatie' => 'volunteering_manager', 'role_type' => self::TYPE_STAFF],
        'trainee' => ['spatie' => 'trainee', 'role_type' => self::TYPE_BENEFICIARY],
        'volunteer' => ['spatie' => 'volunteer', 'role_type' => self::TYPE_BENEFICIARY],
    ];

    /**
     * @return array<string, string>
     */
    public static function platformRoleSelectOptionsAr(): array
    {
        $out = [];
        foreach (array_keys(self::PLATFORM_ROLES) as $key) {
            $out[$key] = self::platformRoleLabelAr($key);
        }

        return $out;
    }

    public static function platformRoleLabelAr(string $platformRole): string
    {
        return match ($platformRole) {
            'public_relations' => 'علاقات عامة',
            'media' => 'إعلام',
            'training_enablement_manager' => 'مسؤول التدريب',
            'programs_activities_manager' => 'مسؤول البرامج والأنشطة',
            'volunteering_manager' => 'مسؤول التطوع',
            'trainee' => 'متدرب',
            'volunteer' => 'متطوع',
            default => RbacCatalog::roleArabicLabel($platformRole),
        };
    }

    public static function actorCanManageAllPlatformRoles(?User $actor): bool
    {
        return $actor?->can('manage_roles') ?? false;
    }

    public static function actorCanAssignBeneficiaryRoles(?User $actor): bool
    {
        return $actor?->can('assign_beneficiary_roles') ?? false;
    }

    public static function canActorEditRoleSection(?User $actor, ?User $target = null): bool
    {
        if ($actor === null) {
            return false;
        }

        if (self::actorCanManageAllPlatformRoles($actor)) {
            if ($target?->isProtectedAdminUser()) {
                return false;
            }

            return true;
        }

        if (! self::actorCanAssignBeneficiaryRoles($actor)) {
            return false;
        }

        if ($target === null) {
            return true;
        }

        return $target->isPortalUser() && ! $target->isProtectedAdminUser();
    }

    /**
     * @return array<string, string>
     */
    public static function platformRoleOptionsForActor(?User $actor): array
    {
        if (self::actorCanManageAllPlatformRoles($actor)) {
            return self::platformRoleSelectOptionsAr();
        }

        if (self::actorCanAssignBeneficiaryRoles($actor)) {
            return [
                'trainee' => self::platformRoleLabelAr('trainee'),
                'volunteer' => self::platformRoleLabelAr('volunteer'),
            ];
        }

        return [];
    }

    /**
     * @return array{spatie: string, role_type: string}
     */
    public static function resolvePlatformRole(string $platformRole): array
    {
        $config = self::PLATFORM_ROLES[$platformRole] ?? null;
        if ($config === null) {
            throw ValidationException::withMessages([
                'data.platform_role' => 'الدور المحدد غير صالح.',
            ]);
        }

        return $config;
    }

    public static function platformRoleFromUser(User $user): ?string
    {
        if ($user->isProtectedAdminUser()) {
            return null;
        }

        $roleNames = $user->relationLoaded('roles')
            ? $user->roles->pluck('name')->all()
            : $user->roles()->pluck('name')->all();

        foreach (array_keys(self::PLATFORM_ROLES) as $key) {
            $spatie = self::PLATFORM_ROLES[$key]['spatie'];
            if (in_array($spatie, $roleNames, true)) {
                return $key;
            }
        }

        $legacyMap = [
            'media_pr' => 'media',
            'media_employee' => 'media',
            'pr_employee' => 'public_relations',
            'training_manager' => 'training_enablement_manager',
            'volunteer_manager' => 'volunteering_manager',
        ];

        foreach ($legacyMap as $legacy => $platform) {
            if (in_array($legacy, $roleNames, true)) {
                return $platform;
            }
        }

        if ($user->role_type === self::TYPE_STAFF) {
            return null;
        }

        return 'trainee';
    }

    /**
     * تسمية عربية موحّدة للجدول (دور واحد بدل نوع حساب + دور).
     */
    public static function tablePlatformRoleLabelAr(User $user): string
    {
        if ($user->role_type === 'admin' || $user->hasRole('admin')) {
            return 'مسؤول النظام';
        }

        $platform = self::platformRoleFromUser($user);
        if ($platform !== null) {
            return self::platformRoleLabelAr($platform);
        }

        return self::tablePrimaryRoleLabelAr($user);
    }

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
        return [
            'public_relations',
            'media',
            'media_employee',
            'pr_employee',
            'training_enablement_manager',
            'training_manager',
            'programs_activities_manager',
            'volunteering_manager',
            'volunteer_manager',
        ];
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

    public static function formAccountTypeFromUser(User $user): string
    {
        if ($user->role_type === self::TYPE_STAFF) {
            return self::TYPE_STAFF;
        }

        return self::TYPE_BENEFICIARY;
    }

    public static function resolvedSpatieRoleFromUser(User $user): ?string
    {
        $platform = self::platformRoleFromUser($user);
        if ($platform !== null) {
            return self::PLATFORM_ROLES[$platform]['spatie'] ?? null;
        }

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

    public static function tablePrimaryRoleLabelAr(User $user): string
    {
        $names = $user->relationLoaded('roles')
            ? $user->roles->pluck('name')->all()
            : $user->roles()->pluck('name')->all();

        $priority = [
            'admin',
            'media_pr',
            'public_relations',
            'media',
            'media_employee',
            'pr_employee',
            'training_enablement_manager',
            'training_manager',
            'programs_activities_manager',
            'volunteering_manager',
            'volunteer_manager',
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

    /**
     * @throws ValidationException
     */
    public static function assertActorMayAssign(?User $actor, string $platformRole): void
    {
        if ($actor === null) {
            throw ValidationException::withMessages([
                'data.platform_role' => 'غير مصرح بتعيين الدور.',
            ]);
        }

        if (self::actorCanManageAllPlatformRoles($actor)) {
            return;
        }

        if (self::actorCanAssignBeneficiaryRoles($actor)
            && in_array($platformRole, ['trainee', 'volunteer'], true)) {
            return;
        }

        throw ValidationException::withMessages([
            'data.platform_role' => 'تعيين هذا الدور غير متاح لحسابك.',
        ]);
    }
}
