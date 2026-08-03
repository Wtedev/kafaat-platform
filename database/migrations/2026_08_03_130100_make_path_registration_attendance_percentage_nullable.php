<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror nullable attendance_percentage semantics on path_registrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('path_registrations', function (Blueprint $table) {
            $table->decimal('attendance_percentage', 5, 2)->nullable()->default(null)->change();
        });

        DB::table('path_registrations')
            ->where('attendance_percentage', 0)
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('path_attendance')
                    ->whereColumn(
                        'path_attendance.path_registration_id',
                        'path_registrations.id',
                    );
            })
            ->update(['attendance_percentage' => null]);
    }

    public function down(): void
    {
        DB::table('path_registrations')
            ->whereNull('attendance_percentage')
            ->update(['attendance_percentage' => 0]);

        Schema::table('path_registrations', function (Blueprint $table) {
            $table->decimal('attendance_percentage', 5, 2)->nullable(false)->default(0)->change();
        });
    }
};
