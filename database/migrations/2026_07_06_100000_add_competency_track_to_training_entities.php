<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->string('competency_track', 32)->nullable()->after('program_kind');
        });

        Schema::table('learning_paths', function (Blueprint $table) {
            $table->string('competency_track', 32)->nullable()->after('path_kind');
        });
    }

    public function down(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->dropColumn('competency_track');
        });

        Schema::table('learning_paths', function (Blueprint $table) {
            $table->dropColumn('competency_track');
        });
    }
};
