<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('survey_template_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('survey_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('type');
            $table->text('prompt');
            $table->string('scale_min_label')->nullable();
            $table->string('scale_max_label')->nullable();
            $table->json('options')->nullable();
            $table->boolean('required')->default(true);
            $table->timestamps();
        });

        Schema::create('program_surveys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('public_token', 80)->unique();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->foreignId('source_template_id')->nullable()->constrained('survey_templates')->nullOnDelete();
            $table->timestamps();

            $table->unique(['training_program_id', 'type']);
        });

        Schema::create('program_survey_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_survey_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('type');
            $table->text('prompt');
            $table->string('scale_min_label')->nullable();
            $table->string('scale_max_label')->nullable();
            $table->json('options')->nullable();
            $table->boolean('required')->default(true);
            $table->timestamps();
        });

        Schema::create('survey_responses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('program_survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->nullable()->constrained('program_registrations')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['program_survey_id', 'registration_id']);
        });

        Schema::create('survey_answers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('survey_response_id');
            $table->foreign('survey_response_id')->references('id')->on('survey_responses')->cascadeOnDelete();
            $table->foreignId('program_survey_question_id')->constrained()->cascadeOnDelete();
            $table->json('value')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('survey_completions', function (Blueprint $table): void {
            $table->foreignId('program_survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('program_registrations')->cascadeOnDelete();

            $table->primary(['program_survey_id', 'registration_id']);
        });

        Schema::create('survey_access_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_survey_id')->constrained()->cascadeOnDelete();
            $table->string('national_id_hash', 64);
            $table->string('ip', 45)->nullable();
            $table->boolean('matched');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_access_attempts');
        Schema::dropIfExists('survey_completions');
        Schema::dropIfExists('survey_answers');
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('program_survey_questions');
        Schema::dropIfExists('program_surveys');
        Schema::dropIfExists('survey_template_questions');
        Schema::dropIfExists('survey_templates');
    }
};
