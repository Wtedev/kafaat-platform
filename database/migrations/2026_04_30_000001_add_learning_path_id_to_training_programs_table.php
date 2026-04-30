<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->foreignId('learning_path_id')
                ->nullable()
                ->after('updated_by')
                ->constrained('learning_paths')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->dropForeign(['learning_path_id']);
            $table->dropColumn('learning_path_id');
        });
    }
};
