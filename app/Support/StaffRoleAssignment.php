<?php

namespace App\Support;

use App\Services\Rbac\RbacCatalog;

/**
 * أسماء أدوار الموظفين «الحساسة» التي لا يجوز لمديري المستفيدين تعيينها.
 */
final class StaffRoleAssignment
{
    /**
     * @return list<string>
     */
    public static function privilegedStaffSpatieRoleNames(): array
    {
        return [
            'admin',
            ...RbacCatalog::staffRoleNames(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function beneficiarySpatieRoleNames(): array
    {
        return ['trainee', 'beneficiary', 'volunteer'];
    }
}
