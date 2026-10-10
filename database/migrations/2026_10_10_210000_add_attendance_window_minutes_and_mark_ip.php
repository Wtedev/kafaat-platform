<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_attendance_links', function (Blueprint $table): void {
            $table->unsignedSmallInteger('open_minutes')->default(15)->after('name');
        });

        Schema::table('program_attendance_marks', function (Blueprint $table): void {
            $table->string('ip_address', 45)->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('program_attendance_marks', function (Blueprint $table): void {
            $table->dropColumn('ip_address');
        });

        Schema::table('program_attendance_links', function (Blueprint $table): void {
            $table->dropColumn('open_minutes');
        });
    }
};
