<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // البريد معطّل افتراضياً؛ إعادة عرض النافذة المنبثقة لمرة واحدة لكل مستخدم.
        DB::table('users')->update([
            'notify_email' => false,
            'notification_prefs_set_at' => null,
        ]);
    }

    public function down(): void
    {
        // لا يُستعاد التفضيل السابق تلقائياً.
    }
};
