<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table): void {
            $table->json('registration_questions')->nullable()->after('approval_message');
        });

        Schema::table('program_registrations', function (Blueprint $table): void {
            $table->json('registration_answers')->nullable()->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('training_programs', function (Blueprint $table): void {
            $table->dropColumn('registration_questions');
        });

        Schema::table('program_registrations', function (Blueprint $table): void {
            $table->dropColumn('registration_answers');
        });
    }
};
