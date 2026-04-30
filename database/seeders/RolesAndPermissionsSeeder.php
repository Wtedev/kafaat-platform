<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.activate',

            // Roles
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',

            // Paths
            'paths.view',
            'paths.create',
            'paths.update',
            'paths.delete',
            'paths.publish',
            'paths.archive',

            // Courses
            'courses.view',
            'courses.create',
            'courses.update',
            'courses.delete',
            'courses.publish',
            'courses.hide',

            // Programs
            'programs.view',
            'programs.create',
            'programs.update',
            'programs.delete',
            'programs.publish',
            'programs.archive',

            // Volunteering
            'volunteering.view',
            'volunteering.create',
            'volunteering.update',
            'volunteering.delete',
            'volunteering.publish',
            'volunteering.archive',

            // Registrations
            'registrations.view',
            'registrations.approve',
            'registrations.reject',

            // Progress
            'progress.view',
            'progress.update',

            // Volunteer Hours
            'volunteer_hours.view',
            'volunteer_hours.create',
            'volunteer_hours.approve',
            'volunteer_hours.reject',

            // Certificates
            'certificates.view',
            'certificates.issue',
            'certificates.download',

            // Emails
            'emails.send',

            // Statistics
            'statistics.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ─── Admin: all permissions ───────────────────────────────────────────
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions($permissions);

        // ─── Staff: operational permissions (excludes role management and user deletion) ─
        $staffPermissions = [
            'users.view',
            'users.create',
            'users.update',
            'users.activate',

            'paths.view',
            'paths.create',
            'paths.update',
            'paths.publish',
            'paths.archive',

            'courses.view',
            'courses.create',
            'courses.update',
            'courses.publish',
            'courses.hide',

            'programs.view',
            'programs.create',
            'programs.update',
            'programs.publish',
            'programs.archive',

            'volunteering.view',
            'volunteering.create',
            'volunteering.update',
            'volunteering.publish',
            'volunteering.archive',

            'registrations.view',
            'registrations.approve',
            'registrations.reject',

            'progress.view',
            'progress.update',

            'volunteer_hours.view',
            'volunteer_hours.create',
            'volunteer_hours.approve',
            'volunteer_hours.reject',

            'certificates.view',
            'certificates.issue',
            'certificates.download',

            'emails.send',

            'statistics.view',
        ];

        $staff = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        $staff->syncPermissions($staffPermissions);

        // ─── Beneficiary: minimal permissions (self-service only) ─────────────
        $beneficiaryPermissions = [
            'paths.view',
            'courses.view',
            'programs.view',
            'volunteering.view',
            'registrations.view',
            'progress.view',
            'volunteer_hours.view',
            'volunteer_hours.create',
            'certificates.view',
            'certificates.download',
        ];

        $beneficiary = Role::firstOrCreate(['name' => 'beneficiary', 'guard_name' => 'web']);
        $beneficiary->syncPermissions($beneficiaryPermissions);
    }
}
