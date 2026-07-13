<?php

namespace App\Support;

use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Validation\ValidationException;

/**
 * قيود تعيين مستفيد / فريق تطوعي للموظفين المخوّلين.
 */
final class BeneficiaryRoleManagement
{
    /** @return list<string> */
    public static function allowedRoleTypesForManager(User $actor): array
    {
        if ($actor->isAdmin()) {
            return [RbacCatalog::ROLE_BENEFICIARY, RbacCatalog::ROLE_VOLUNTEER, 'trainee'];
        }

        if (! $actor->can('assign_beneficiary_roles')) {
            return [];
        }

        $types = [];
        if (StaffFilamentRoles::hasTrainingCoordinatorRole($actor)) {
            $types[] = RbacCatalog::ROLE_BENEFICIARY;
            $types[] = 'trainee';
        }
        if (StaffFilamentRoles::hasVolunteeringCoordinatorRole($actor)) {
            $types[] = RbacCatalog::ROLE_VOLUNTEER;
        }

        return array_values(array_unique($types));
    }

    /** @return list<string> */
    public static function allowedSpatieRoleNamesForManager(User $actor): array
    {
        if ($actor->isAdmin()) {
            return [RbacCatalog::ROLE_BENEFICIARY, RbacCatalog::ROLE_VOLUNTEER];
        }

        $roles = [];
        if (StaffFilamentRoles::hasTrainingCoordinatorRole($actor) && $actor->can('assign_beneficiary_roles')) {
            $roles[] = RbacCatalog::ROLE_BENEFICIARY;
        }
        if (StaffFilamentRoles::hasVolunteeringCoordinatorRole($actor) && $actor->can('assign_beneficiary_roles')) {
            $roles[] = RbacCatalog::ROLE_VOLUNTEER;
        }

        return $roles;
    }

    public static function validateManagerAssignment(User $actor, ?string $roleType, mixed $roleIds): void
    {
        if (FilamentAssignmentVisibility::bypasses($actor)) {
            return;
        }

        $allowedTypes = self::allowedRoleTypesForManager($actor);

        if ($allowedTypes === []) {
            if ($actor->can('assign_beneficiary_roles')) {
                throw ValidationException::withMessages([
                    'data.roles' => 'تعيين أدوار المستفيدين غير متاح لحسابك.',
                ]);
            }

            return;
        }

        if ($roleType !== null && $roleType !== '' && ! in_array($roleType, $allowedTypes, true)) {
            throw ValidationException::withMessages([
                'data.role_type' => 'نوع الحساب غير مسموح لصلاحياتك.',
            ]);
        }
    }
}
