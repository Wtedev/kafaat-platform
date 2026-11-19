<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Learning paths were removed from the platform (only test data existed).
 *
 * Programs that were inside a path go back to draft so they never appear
 * publicly as open standalone programs by surprise. Rows that point at paths
 * from shared tables (certificates, notifications, activity log, live
 * attendance sessions, retention policies, permissions) are deleted, then the
 * path tables and the program columns are dropped.
 */
return new class extends Migration
{
    private const PATH_MODEL = 'App\\Models\\LearningPath';

    public function up(): void
    {
        if (Schema::hasColumn('training_programs', 'learning_path_id')) {
            DB::table('training_programs')
                ->whereNotNull('learning_path_id')
                ->update(['status' => 'draft']);

            Schema::table('training_programs', function (Blueprint $table): void {
                $table->dropForeign(['learning_path_id']);
            });

            Schema::table('training_programs', function (Blueprint $table): void {
                $table->dropColumn('learning_path_id');
            });
        }

        if (Schema::hasColumn('training_programs', 'path_sort_order')) {
            Schema::table('training_programs', function (Blueprint $table): void {
                $table->dropColumn('path_sort_order');
            });
        }

        if (Schema::hasTable('certificates')) {
            DB::table('certificates')->where('certificateable_type', self::PATH_MODEL)->delete();
        }

        if (Schema::hasTable('attendance_live_sessions')) {
            DB::table('attendance_live_sessions')->where('attendable_type', self::PATH_MODEL)->delete();
        }

        if (Schema::hasTable('in_app_notifications')) {
            DB::table('in_app_notifications')
                ->whereIn('type', ['learning_path_launched', 'staff_new_path_registration'])
                ->delete();
        }

        if (Schema::hasTable('user_activity_logs')) {
            DB::table('user_activity_logs')->where('action', 'path_registration')->delete();
        }

        if (Schema::hasTable('retention_policies')) {
            DB::table('retention_policies')->where('resource_type', 'path_registrations')->delete();
        }

        if (Schema::hasTable('retention_exceptions')) {
            DB::table('retention_exceptions')->where('resource_type', 'path_registrations')->delete();
        }

        if (Schema::hasTable('permissions')) {
            $permissionIds = DB::table('permissions')->where('name', 'like', 'paths.%')->pluck('id');

            if ($permissionIds->isNotEmpty()) {
                if (Schema::hasTable('role_has_permissions')) {
                    DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
                }

                if (Schema::hasTable('model_has_permissions')) {
                    DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
                }

                DB::table('permissions')->whereIn('id', $permissionIds)->delete();

                app(PermissionRegistrar::class)->forgetCachedPermissions();
            }
        }

        Schema::dropIfExists('path_attendance');
        Schema::dropIfExists('path_registrations');
        Schema::dropIfExists('learning_path_editors');
        Schema::dropIfExists('learning_paths');
    }

    public function down(): void
    {
        throw new RuntimeException('Learning paths were removed; restore from a database backup if needed.');
    }
};
