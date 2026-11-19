<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->nullableMorphs('subject');
            $table->string('description', 500)->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['training_program_id', 'created_at']);
            $table->index(['training_program_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_activities');
    }
};
