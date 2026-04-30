<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_attendance', function (Blueprint $table) {
            $table->id();

            $table->foreignId('program_registration_id')
                ->constrained('program_registrations')
                ->cascadeOnDelete();

            $table->date('training_date');

            // AttendanceStatus: present | absent | excused
            $table->string('status', 20)->default('absent');

            $table->text('notes')->nullable();

            $table->timestamps();

            // One row per registrant per training day
            $table->unique(['program_registration_id', 'training_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_attendance');
    }
};
