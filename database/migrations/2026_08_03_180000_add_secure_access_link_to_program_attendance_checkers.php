<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secure prep-officer links replace email + OTP invites.
 *
 * Legacy columns kept nullable for rollback safety / later drop:
 * - email
 * - invite_code_hash
 * - invite_code_expires_at
 * - invite_attempts
 * - verified_at
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_attendance_checkers', function (Blueprint $table): void {
            $table->dropUnique(['training_program_id', 'email']);
        });

        Schema::table('program_attendance_checkers', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->string('access_token_hash', 64)->nullable()->unique();
            $table->unsignedInteger('access_version')->default(0);
            $table->timestamp('last_used_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('program_attendance_checkers', function (Blueprint $table): void {
            $table->dropUnique(['access_token_hash']);
            $table->dropColumn(['access_token_hash', 'access_version', 'last_used_at']);
        });

        Schema::table('program_attendance_checkers', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
            $table->unique(['training_program_id', 'email']);
        });
    }
};
