<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('path_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('path_registration_id')->constrained('path_registrations')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('status')->default('absent');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['path_registration_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('path_attendance');
    }
};
