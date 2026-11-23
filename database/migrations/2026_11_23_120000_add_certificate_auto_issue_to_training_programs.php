<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table): void {
            $table->boolean('certificate_auto_issue')->default(false);
            // {"attendance_link_ids": [int], "survey_types": ["pre"|"post"|"satisfaction"]}
            $table->json('certificate_auto_criteria')->nullable();
            $table->foreignId('certificate_auto_enabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('certificate_auto_enabled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('training_programs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('certificate_auto_enabled_by');
            $table->dropColumn(['certificate_auto_issue', 'certificate_auto_criteria', 'certificate_auto_enabled_at']);
        });
    }
};
