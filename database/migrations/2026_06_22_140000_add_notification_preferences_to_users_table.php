<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // التنبيهات داخل الموقع مفعّلة دائماً؛ هذا الحقل يتحكم بنسخة البريد الإلكتروني.
            $table->boolean('notify_email')->default(false)->after('is_active');
            // null = لم يضبط المستخدم تفضيلاته بعد (تظهر النافذة العائمة لأول مرة).
            $table->timestamp('notification_prefs_set_at')->nullable()->after('notify_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['notify_email', 'notification_prefs_set_at']);
        });
    }
};
