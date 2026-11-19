<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Narrow a broadcast to accepted trainees who have not answered a survey
 * ({"survey_missing": "post"}) or missed an attendance day ({"absent_link": 12}).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_broadcasts', function (Blueprint $table): void {
            $table->json('audience_filter')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('program_broadcasts', function (Blueprint $table): void {
            $table->dropColumn('audience_filter');
        });
    }
};
