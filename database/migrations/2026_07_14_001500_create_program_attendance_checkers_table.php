<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_attendance_checkers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_program_id')->constrained('training_programs')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('invite_code_hash')->nullable();
            $table->timestamp('invite_code_expires_at')->nullable();
            $table->unsignedTinyInteger('invite_attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['training_program_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_attendance_checkers');
    }
};
