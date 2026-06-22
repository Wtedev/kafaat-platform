<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governance_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            // Type values: organizational_structure, investment_decisions,
            // general_assembly_minutes, surveys, executive_reports, financial_reports
            $table->string('type');
            $table->string('file_path')->nullable();
            $table->string('file_url')->nullable();
            $table->string('cover_image')->nullable();
            $table->date('document_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_documents');
    }
};
