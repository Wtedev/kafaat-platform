<?php

use App\Services\Certificates\CertificateTemplateBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table): void {
            $table->boolean('auto_issue')->default(false)->after('eligibility');
        });

        $backfill = app(CertificateTemplateBackfill::class);
        $backfill->enableAutoIssueForTrainingPrograms();
        $backfill->backfillLearningPaths();
        $backfill->backfillVolunteerOpportunities();
    }

    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table): void {
            $table->dropColumn('auto_issue');
        });
    }
};
