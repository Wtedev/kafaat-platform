<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * هجرة آمنة للإنتاج: تضيف أعمدة التنبيهات إن وُجدت نسخة قديمة بدونها (تفادي 500 عند إنشاء برنامج/مسار).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'notify_email')) {
                    $table->boolean('notify_email')->default(false);
                }
                if (! Schema::hasColumn('users', 'notification_prefs_set_at')) {
                    $table->timestamp('notification_prefs_set_at')->nullable();
                }
                if (! Schema::hasColumn('users', 'notification_settings')) {
                    $table->json('notification_settings')->nullable();
                }
            });
        }

        if (Schema::hasTable('training_programs')) {
            Schema::table('training_programs', function (Blueprint $table): void {
                if (! Schema::hasColumn('training_programs', 'notify_on_publish')) {
                    $table->boolean('notify_on_publish')->default(true);
                }
                if (! Schema::hasColumn('training_programs', 'notify_milestones')) {
                    $table->boolean('notify_milestones')->default(false);
                }
                if (! Schema::hasColumn('training_programs', 'notify_registrants_on_update')) {
                    $table->boolean('notify_registrants_on_update')->default(false);
                }
            });
        }

        if (Schema::hasTable('learning_paths') && ! Schema::hasColumn('learning_paths', 'notify_on_publish')) {
            Schema::table('learning_paths', function (Blueprint $table): void {
                $table->boolean('notify_on_publish')->default(true);
            });
        }

        if (Schema::hasTable('news') && ! Schema::hasColumn('news', 'notify_audience_on_publish')) {
            Schema::table('news', function (Blueprint $table): void {
                $table->boolean('notify_audience_on_publish')->default(true);
            });
        }

        if (Schema::hasTable('volunteer_opportunities')) {
            Schema::table('volunteer_opportunities', function (Blueprint $table): void {
                if (! Schema::hasColumn('volunteer_opportunities', 'notify_on_publish')) {
                    $table->boolean('notify_on_publish')->default(true);
                }
                if (! Schema::hasColumn('volunteer_opportunities', 'notify_registrants_on_update')) {
                    $table->boolean('notify_registrants_on_update')->default(false);
                }
            });
        }
    }

    public function down(): void
    {
        // لا حذف — قد تكون الأعمدة من هجرات سابقة.
    }
};
