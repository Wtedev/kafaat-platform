<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_course_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('path_course_id')->constrained()->cascadeOnDelete();
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->decimal('score', 5, 2)->nullable();
            $table->string('status')->default('not_started');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'path_course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_course_progress');
    }
};
