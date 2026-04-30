<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            // Array of weekday numbers: 0 = Sunday, 1 = Monday, …, 6 = Saturday
            // Matches PHP/Carbon dayOfWeek values.
            $table->json('weekdays')->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->dropColumn('weekdays');
        });
    }
};
