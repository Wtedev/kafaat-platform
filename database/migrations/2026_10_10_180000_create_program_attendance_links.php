<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_attendance_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token', 80)->unique();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();
        });

        Schema::create('program_attendance_marks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_attendance_link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_registration_id')->constrained()->cascadeOnDelete();
            $table->timestamp('attended_at');
            $table->string('source');
            $table->timestamps();

            $table->unique(['program_attendance_link_id', 'program_registration_id'], 'attendance_link_registration_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_attendance_marks');
        Schema::dropIfExists('program_attendance_links');
    }
};
