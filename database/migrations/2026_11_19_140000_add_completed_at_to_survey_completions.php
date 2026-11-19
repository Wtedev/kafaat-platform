<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a trainee submitted an anonymous survey. The answers themselves stay without
 * a time or a name, so this only tells staff when the person filled it in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_completions', function (Blueprint $table): void {
            $table->timestamp('completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('survey_completions', function (Blueprint $table): void {
            $table->dropColumn('completed_at');
        });
    }
};
