<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table): void {
            $table->json('acceptance_conditions')->nullable()->after('auto_accept_registrations');
        });
    }

    public function down(): void
    {
        Schema::table('training_programs', function (Blueprint $table): void {
            $table->dropColumn('acceptance_conditions');
        });
    }
};
