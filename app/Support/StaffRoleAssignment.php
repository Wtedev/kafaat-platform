<?php

namespace App\Support;

/**
 * أسماء أدوار الموظفين «الحساسة» التي لا يجوز لمديري المستفيدين تعيينها.
 */
final class StaffRoleAssignment
{
    /**
     * أدوار Spatie التي تعتبر حسابات موظفين مميزين (لا يعيّنها مدير التدريب/التطوع).
     *
     * @return list<string>
     */
    public static function privilegedStaffSpatieRoleNames(): array
    {
        return [
            'admin',
            'training_manager',
            'volunteering_manager',
            'media_pr',
            'media_employee',
            'pr_employee',
            'staff',
        ];
    }

    /**
     * أدوار المستفيدين التي يجوز لمديري التدريب/التطوع تعيينها فقط.
     *
     * @return list<string>
     */
    public static function beneficiarySpatieRoleNames(): array
    {
        return ['trainee', 'beneficiary', 'volunteer'];
    }
}
