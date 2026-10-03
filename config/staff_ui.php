<?php

use App\Models\User;

return [

    /*
    | When true, staff/admin routes show the maintenance page.
    | Super Admin (role `admin` or `super_admin`) still gets through.
    | Modules listed in ready_modules stay open for every staff user.
    */
    'maintenance' => (bool) env('STAFF_UI_MAINTENANCE', false),

    /*
    | Module names attached to staff route groups, for example:
    | shell, users, training, certificates, volunteering,
    | content, governance, access, support.
    */
    'ready_modules' => [],

    /*
    | Top-bar tools kept in the layout and switched off until each one is built.
    | Set a flag to true to show it again.
    */
    'topbar' => [
        'search' => false,
        'help' => false,
        'settings' => false,
    ],

    /*
    | Sidebar entries for the live staff shell. Preview mode keeps its own menu.
    */
    'nav' => [
        [
            'label' => 'لوحة التحكم',
            'icon' => 'layout-dashboard',
            'route' => 'filament.admin.pages.dashboard',
            'key' => 'dashboard',
        ],
        [
            'label' => 'المستخدمين',
            'icon' => 'users',
            'route' => 'staff-ui.users.index',
            'key' => 'users',
            'ability' => 'viewAny',
            'model' => User::class,
        ],
    ],

];
