<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * attendance_percentage null = «—» (no due prep days / not yet calculable).
 * Do not treat stored 0 as a real percentage before due days exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_registrations', function (Blueprint $table) {
            $table->decimal('attendance_percentage', 5, 2)->nullable()->default(null)->change();
        });

        // Legacy default 0 before any due prep days is indistinguishable from a real 0%.
        // Clear to null; ProgramAttendance observers recalculate from prep days going forward.
        if (Schema::hasTable('program_prep_days')) {
            DB::table('program_registrations')
                ->where('attendance_percentage', 0)
                ->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('program_attendance')
                        ->whereColumn(
                            'program_attendance.program_registration_id',
                            'program_registrations.id',
                        );
                })
                ->update(['attendance_percentage' => null]);
        }
    }

    public function down(): void
    {
        DB::table('program_registrations')
            ->whereNull('attendance_percentage')
            ->update(['attendance_percentage' => 0]);

        Schema::table('program_registrations', function (Blueprint $table) {
            $table->decimal('attendance_percentage', 5, 2)->nullable(false)->default(0)->change();
        });
    }
};
