<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Program attendance redesign (post PR #36):
 * - Force all program_prep_days to require attendance (column kept for compat).
 * - Collapse program_attendance statuses: late → present; delete absent/excused.
 * - Link live sessions to an optional program prep day + session date.
 * Does NOT modify path_attendance.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('program_prep_days') && Schema::hasColumn('program_prep_days', 'requires_attendance')) {
            DB::table('program_prep_days')->update(['requires_attendance' => true]);
        }

        if (Schema::hasTable('program_attendance') && Schema::hasColumn('program_attendance', 'status')) {
            DB::table('program_attendance')
                ->where('status', 'late')
                ->update(['status' => 'present']);

            DB::table('program_attendance')
                ->whereIn('status', ['absent', 'excused'])
                ->delete();

            $this->dedupeProgramAttendancePresentRows();
        }

        if (Schema::hasTable('attendance_live_sessions')) {
            Schema::table('attendance_live_sessions', function (Blueprint $table): void {
                if (! Schema::hasColumn('attendance_live_sessions', 'program_prep_day_id')) {
                    $table->foreignId('program_prep_day_id')
                        ->nullable()
                        ->after('attendable_id')
                        ->constrained('program_prep_days')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('attendance_live_sessions', 'session_date')) {
                    $table->date('session_date')->nullable()->after('program_prep_day_id');
                    $table->index(['attendable_type', 'attendable_id', 'session_date']);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance_live_sessions')) {
            Schema::table('attendance_live_sessions', function (Blueprint $table): void {
                if (Schema::hasColumn('attendance_live_sessions', 'program_prep_day_id')) {
                    $table->dropConstrainedForeignId('program_prep_day_id');
                }

                if (Schema::hasColumn('attendance_live_sessions', 'session_date')) {
                    $table->dropIndex(['attendable_type', 'attendable_id', 'session_date']);
                    $table->dropColumn('session_date');
                }
            });
        }

        // Status collapse and requires_attendance force-backfill are not reversed:
        // absent/excused rows were deleted intentionally; restoring them is lossy.
    }

    /**
     * Keep a single present row per (registration, date) if duplicates somehow exist.
     */
    private function dedupeProgramAttendancePresentRows(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                DELETE FROM program_attendance a
                USING program_attendance b
                WHERE a.id > b.id
                  AND a.program_registration_id = b.program_registration_id
                  AND a.training_date = b.training_date
            SQL);

            return;
        }

        $duplicates = DB::table('program_attendance')
            ->select('program_registration_id', 'training_date', DB::raw('MIN(id) as keep_id'))
            ->groupBy('program_registration_id', 'training_date')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('program_attendance')
                ->where('program_registration_id', $dup->program_registration_id)
                ->whereDate('training_date', $dup->training_date)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }
    }
};
