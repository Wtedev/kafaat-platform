<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_live_sessions', function (Blueprint $table): void {
            $table->foreignId('opened_by_checker_id')
                ->nullable()
                ->after('created_by')
                ->constrained('program_attendance_checkers')
                ->nullOnDelete();
            $table->timestamp('closed_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_live_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('opened_by_checker_id');
            $table->dropColumn('closed_at');
        });
    }
};
