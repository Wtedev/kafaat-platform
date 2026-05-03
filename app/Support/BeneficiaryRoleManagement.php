<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * قيود أدوار المستفيدين (متدرب / متطوع) لمديري التدريب والتطوع.
 */
final class BeneficiaryRoleManagement
{
    /**
     * @return list<string>
     */
    public static function allowedRoleTypesForManager(User $actor): array
    {
        if (StaffFilamentRoles::isProgramsActivitiesManager($actor)) {
            return ['trainee', 'beneficiary', 'volunteer'];
        }

        if ($actor->hasAnyRole(StaffFilamentRoles::TRAINING_COORDINATOR)) {
            return ['trainee', 'beneficiary'];
        }

        if ($actor->hasAnyRole(StaffFilamentRoles::VOLUNTEERING_COORDINATOR)) {
            return ['volunteer'];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    public static function allowedSpatieRoleNamesForManager(User $actor): array
    {
        if (StaffFilamentRoles::isProgramsActivitiesManager($actor)) {
            return ['trainee', 'volunteer'];
        }

        if ($actor->hasAnyRole(StaffFilamentRoles::TRAINING_COORDINATOR)) {
            return ['trainee', 'beneficiary'];
        }

        if ($actor->hasAnyRole(StaffFilamentRoles::VOLUNTEERING_COORDINATOR)) {
            return ['volunteer'];
        }

        return [];
    }

    /**
     * @param  mixed  $roleIds  معرفات أدوار Spatie من النموذج
     *
     * @throws ValidationException
     */
    public static function validateManagerAssignment(User $actor, ?string $roleType, mixed $roleIds): void
    {
        if (FilamentAssignmentVisibility::bypasses($actor)) {
            return;
        }

        $allowedTypes = self::allowedRoleTypesForManager($actor);
        $allowedRoles = self::allowedSpatieRoleNamesForManager($actor);

        if ($allowedTypes === []) {
            if ($actor->can('assign_beneficiary_roles')) {
                throw ValidationException::withMessages([
                    'data.roles' => 'تعيين أدوار المستفيدين غير مرتبط بدور مدير تدريب أو مدير تطوع في حسابك.',
                ]);
            }

            return;
        }

        if ($roleType !== null && $roleType !== '' && ! in_array($roleType, $allowedTypes, true)) {
            throw ValidationException::withMessages([
                'data.role_type' => 'نوع الحساب غير مسموح لصلاحياتك.',
            ]);
        }

        if ($roleIds === null || $roleIds === [] || $roleIds === '') {
            return;
        }

        $ids = is_array($roleIds) ? $roleIds : [$roleIds];
        $ids = array_values(array_filter($ids, fn ($v) => $v !== null && $v !== ''));

        if ($ids === []) {
            return;
        }

        $names = Role::query()->whereIn('id', $ids)->pluck('name')->all();

        foreach ($names as $name) {
            if (! in_array($name, $allowedRoles, true)) {
                throw ValidationException::withMessages([
                    'data.roles' => 'أحد الأدوار المختارة غير مسموح لصلاحياتك.',
                ]);
            }
        }
    }
}
