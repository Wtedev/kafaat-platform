<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->decimal('attendance_percentage', 5, 2)->default(0);
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['training_program_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_registrations');
    }
};
