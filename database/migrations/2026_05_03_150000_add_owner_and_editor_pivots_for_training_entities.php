<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->foreignId('owner_id')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('learning_paths', function (Blueprint $table) {
            $table->foreignId('owner_id')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::statement('UPDATE training_programs SET owner_id = created_by WHERE created_by IS NOT NULL');
        DB::statement('UPDATE learning_paths SET owner_id = created_by WHERE created_by IS NOT NULL');

        Schema::create('training_program_editors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['training_program_id', 'user_id']);
        });

        Schema::create('learning_path_editors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_path_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['learning_path_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_path_editors');
        Schema::dropIfExists('training_program_editors');

        Schema::table('learning_paths', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });

        Schema::table('training_programs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
