<?php

namespace Tests\Concerns;

use Database\Seeders\RolesAndPermissionsSeeder;

trait SeedsRbacRoles
{
    protected function seedRbacRoles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }
}
