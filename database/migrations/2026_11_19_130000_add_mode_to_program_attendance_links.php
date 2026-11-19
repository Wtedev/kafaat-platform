<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_attendance_links', function (Blueprint $table): void {
            // remote: trainees open the link and enter their identity number.
            // qr: a prep officer opens the link and scans each trainee's attendance QR.
            $table->string('mode', 16)->default('remote');
        });
    }

    public function down(): void
    {
        Schema::table('program_attendance_links', function (Blueprint $table): void {
            $table->dropColumn('mode');
        });
    }
};
