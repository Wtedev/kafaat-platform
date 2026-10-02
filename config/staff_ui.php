<?php

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

];
