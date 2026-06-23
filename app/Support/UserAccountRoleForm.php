<?php

namespace App\Support;

use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use App\Support\BeneficiaryRoleManagement;
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
        'technical_admin' => ['spatie' => 'technical_admin', 'role_type' => self::TYPE_STAFF],
        'training_management' => ['spatie' => 'training_management', 'role_type' => self::TYPE_STAFF],
        'volunteer_management' => ['spatie' => 'volunteer_management', 'role_type' => self::TYPE_STAFF],
        'programs_management' => ['spatie' => 'programs_management', 'role_type' => self::TYPE_STAFF],
        'media_management' => ['spatie' => 'media_management', 'role_type' => self::TYPE_STAFF],
        'public_relations' => ['spatie' => 'public_relations', 'role_type' => self::TYPE_STAFF],
        'visual_identity' => ['spatie' => 'visual_identity', 'role_type' => self::TYPE_STAFF],
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
        return RbacCatalog::roleArabicLabel(
            self::PLATFORM_ROLES[$platformRole]['spatie'] ?? $platformRole
        );
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

        $allowedSpatie = BeneficiaryRoleManagement::allowedSpatieRoleNamesForManager($actor);
        if ($allowedSpatie === []) {
            return [];
        }

        $options = [];
        foreach (array_keys(self::PLATFORM_ROLES) as $key) {
            $spatie = self::PLATFORM_ROLES[$key]['spatie'];
            if (in_array($spatie, $allowedSpatie, true)) {
                $options[$key] = self::platformRoleLabelAr($key);
            }
        }

        return $options;
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

        foreach (RbacCatalog::legacyRoleMigrationMap() as $legacy => $platformSpatie) {
            if (! in_array($legacy, $roleNames, true)) {
                continue;
            }

            foreach (self::PLATFORM_ROLES as $key => $config) {
                if ($config['spatie'] === $platformSpatie) {
                    return $key;
                }
            }
        }

        if ($user->role_type === self::TYPE_STAFF) {
            return null;
        }

        return 'trainee';
    }

    public static function tablePlatformRoleLabelAr(User $user): string
    {
        if ($user->role_type === 'admin' || $user->hasRole('admin')) {
            return RbacCatalog::roleArabicLabel('admin');
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
        return RbacCatalog::staffRoleNames();
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
            ...RbacCatalog::staffRoleNames(),
            ...array_keys(RbacCatalog::legacyRoleMigrationMap()),
            ...self::beneficiarySpatieRoleNames(),
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

        $allowedSpatie = BeneficiaryRoleManagement::allowedSpatieRoleNamesForManager($actor);
        $resolved = self::PLATFORM_ROLES[$platformRole]['spatie'] ?? null;

        if ($resolved !== null && in_array($resolved, $allowedSpatie, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'data.platform_role' => 'تعيين هذا الدور غير متاح لحسابك.',
        ]);
    }
}
