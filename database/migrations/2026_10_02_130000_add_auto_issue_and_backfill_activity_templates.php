<?php

use App\Services\Certificates\CertificateTemplateBackfill;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $backfill = app(CertificateTemplateBackfill::class);
        $backfill->backfillLearningPaths();
        $backfill->backfillVolunteerOpportunities();
    }

    public function down(): void
    {
        //
    }
};
