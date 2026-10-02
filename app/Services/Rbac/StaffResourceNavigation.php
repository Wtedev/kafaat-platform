<?php

namespace App\Services\Rbac;

final class StaffResourceNavigation
{
    /**
     * Menu gate for program and path registrations, and for volunteer hours.
     * This is `roles.view`, which is not the model policy permission.
     *
     * @return list<string>
     */
    public static function registrationMenu(): array
    {
        return ['roles.view'];
    }

    /**
     * Menu gate for the certificate resource.
     * Requires both `certificates.view` and `roles.view`.
     *
     * @return list<string>
     */
    public static function certificatesMenu(): array
    {
        return ['certificates.view', 'roles.view'];
    }

    /**
     * @return list<string>
     */
    public static function rolesMenu(): array
    {
        return ['roles.view'];
    }
}
