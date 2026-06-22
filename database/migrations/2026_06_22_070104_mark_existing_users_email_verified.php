<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * تأشير جميع المستخدمين المنشأين قبل تفعيل ميزة التحقق من البريد
     * على أنهم موثّقون حتى لا يُحجب وصولهم.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // لا يمكن التراجع بأمان: نترك الحقل كما هو
    }
};
