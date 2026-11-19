<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programs no longer pick weekdays: every day between the start and end date is a
 * program day, and attendance days are the days that have an attendance link.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('training_programs', 'weekdays')) {
            Schema::table('training_programs', function (Blueprint $table): void {
                $table->dropColumn('weekdays');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('training_programs', 'weekdays')) {
            Schema::table('training_programs', function (Blueprint $table): void {
                $table->json('weekdays')->nullable();
            });
        }
    }
};
