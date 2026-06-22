<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('notification_settings')->nullable()->after('notification_prefs_set_at');
        });

        Schema::table('news', function (Blueprint $table): void {
            $table->boolean('notify_audience_on_publish')->default(true)->after('published_notification_sent_at');
        });

        Schema::table('training_programs', function (Blueprint $table): void {
            $table->boolean('notify_on_publish')->default(true)->after('published_at');
            $table->boolean('notify_milestones')->default(false)->after('notify_on_publish');
            $table->boolean('notify_registrants_on_update')->default(false)->after('notify_milestones');
        });

        Schema::table('learning_paths', function (Blueprint $table): void {
            $table->boolean('notify_on_publish')->default(true)->after('published_at');
        });

        Schema::table('volunteer_opportunities', function (Blueprint $table): void {
            $table->boolean('notify_on_publish')->default(true)->after('published_at');
            $table->boolean('notify_registrants_on_update')->default(false)->after('notify_on_publish');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('notification_settings');
        });

        Schema::table('news', function (Blueprint $table): void {
            $table->dropColumn('notify_audience_on_publish');
        });

        Schema::table('training_programs', function (Blueprint $table): void {
            $table->dropColumn(['notify_on_publish', 'notify_milestones', 'notify_registrants_on_update']);
        });

        Schema::table('learning_paths', function (Blueprint $table): void {
            $table->dropColumn('notify_on_publish');
        });

        Schema::table('volunteer_opportunities', function (Blueprint $table): void {
            $table->dropColumn(['notify_on_publish', 'notify_registrants_on_update']);
        });
    }
};
