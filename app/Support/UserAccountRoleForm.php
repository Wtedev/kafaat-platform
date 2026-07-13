<?php

namespace App\Support;

use App\Models\User;
use App\Models\VolunteerTeam;
use App\Services\Rbac\RbacCatalog;
use App\Services\Rbac\StaffPermissionService;
use Illuminate\Validation\ValidationException;

/**
 * أنواع الحساب الأربعة: أدمن / موظف / مستفيد / فريق تطوعي.
 */
final class UserAccountRoleForm
{
    public const TYPE_ADMIN = RbacCatalog::ROLE_ADMIN;

    public const TYPE_STAFF = RbacCatalog::ROLE_STAFF;

    public const TYPE_BENEFICIARY = RbacCatalog::ROLE_BENEFICIARY;

    public const TYPE_VOLUNTEER = RbacCatalog::ROLE_VOLUNTEER;

    /**
     * الأدوار القابلة للتعيين من الواجهة (الأدمن لا يُعيَّن من هنا).
     *
     * @var array<string, array{spatie: string, role_type: string}>
     */
    public const PLATFORM_ROLES = [
        self::TYPE_STAFF => ['spatie' => self::TYPE_STAFF, 'role_type' => self::TYPE_STAFF],
        self::TYPE_BENEFICIARY => ['spatie' => self::TYPE_BENEFICIARY, 'role_type' => self::TYPE_BENEFICIARY],
        self::TYPE_VOLUNTEER => ['spatie' => self::TYPE_VOLUNTEER, 'role_type' => self::TYPE_VOLUNTEER],
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
        return $actor?->isAdmin() ?? false;
    }

    public static function actorCanAssignBeneficiaryRoles(?User $actor): bool
    {
        return $actor?->can('assign_beneficiary_roles') || ($actor?->isAdmin() ?? false);
    }

    public static function canActorEditRoleSection(?User $actor, ?User $target = null): bool
    {
        if ($actor === null) {
            return false;
        }

        if ($target?->isProtectedAdminUser()) {
            return false;
        }

        if (self::actorCanManageAllPlatformRoles($actor)) {
            return true;
        }

        if (! self::actorCanAssignBeneficiaryRoles($actor)) {
            return false;
        }

        return $target === null || $target->isPortalUser();
    }

    /**
     * @return array{spatie: string, role_type: string}
     */
    public static function resolvePlatformRole(string $platformRole): array
    {
        if (! isset(self::PLATFORM_ROLES[$platformRole])) {
            throw ValidationException::withMessages([
                'data.platform_role' => 'الدور المحدد غير صالح.',
            ]);
        }

        return self::PLATFORM_ROLES[$platformRole];
    }

    public static function assertActorMayAssign(?User $actor, string $platformRole): void
    {
        if ($actor === null) {
            throw ValidationException::withMessages([
                'data.platform_role' => 'غير مصرح.',
            ]);
        }

        if ($platformRole === self::TYPE_ADMIN) {
            throw ValidationException::withMessages([
                'data.platform_role' => 'لا يمكن تعيين حساب أدمن من الواجهة. يوجد أدمن واحد فقط.',
            ]);
        }

        if (self::actorCanManageAllPlatformRoles($actor)) {
            return;
        }

        if (in_array($platformRole, [self::TYPE_BENEFICIARY, self::TYPE_VOLUNTEER], true)
            && self::actorCanAssignBeneficiaryRoles($actor)) {
            return;
        }

        throw ValidationException::withMessages([
            'data.platform_role' => 'ليس لديك صلاحية تعيين هذا الدور.',
        ]);
    }

    public static function platformRoleFromUser(User $user): ?string
    {
        if ($user->isProtectedAdminUser() || $user->isAdmin()) {
            return self::TYPE_ADMIN;
        }

        $user->loadMissing('roles');
        $name = $user->roles->pluck('name')->first();

        if ($name === self::TYPE_STAFF || $user->role_type === self::TYPE_STAFF) {
            return self::TYPE_STAFF;
        }

        if ($name === self::TYPE_VOLUNTEER || $user->role_type === self::TYPE_VOLUNTEER) {
            return self::TYPE_VOLUNTEER;
        }

        $map = RbacCatalog::legacyRoleMigrationMap();
        if (is_string($name) && isset($map[$name])) {
            return $map[$name];
        }

        return self::TYPE_BENEFICIARY;
    }

    /**
     * @return array<string, string>
     */
    public static function platformRoleOptionsForActor(?User $actor): array
    {
        if ($actor === null) {
            return [];
        }

        if (self::actorCanManageAllPlatformRoles($actor)) {
            return self::platformRoleSelectOptionsAr();
        }

        if (self::actorCanAssignBeneficiaryRoles($actor)) {
            return [
                self::TYPE_BENEFICIARY => self::platformRoleLabelAr(self::TYPE_BENEFICIARY),
                self::TYPE_VOLUNTEER => self::platformRoleLabelAr(self::TYPE_VOLUNTEER),
            ];
        }

        return [];
    }

    /** @return list<string> */
    public static function staffSpatieRoleNames(): array
    {
        return RbacCatalog::staffRoleNames();
    }

    public static function applyRoleSideEffects(User $record, string $spatieRole): void
    {
        if ($spatieRole === self::TYPE_STAFF) {
            app(StaffPermissionService::class)->grantAllAssignable($record);
        } else {
            $record->syncPermissions([]);
        }

        if ($spatieRole === self::TYPE_VOLUNTEER) {
            VolunteerTeam::ensureMember($record);
        }
    }

    public static function tablePlatformRoleLabelAr(User $user): string
    {
        if ($user->isProtectedAdminUser() || $user->isAdmin()) {
            return self::platformRoleLabelAr(self::TYPE_ADMIN);
        }

        $key = self::platformRoleFromUser($user);

        return self::platformRoleLabelAr($key ?? self::TYPE_BENEFICIARY);
    }
}
