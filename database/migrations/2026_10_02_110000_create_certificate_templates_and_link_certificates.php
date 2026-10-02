<?php

use App\Services\Certificates\CertificateTemplateBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->string('background_path')->nullable();
            $table->string('background_disk')->default('public');
            $table->decimal('page_width_mm', 8, 2)->default(297);
            $table->decimal('page_height_mm', 8, 2)->default(210);
            $table->json('elements')->nullable();
            $table->json('eligibility');
            $table->string('status')->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id'], 'certificate_templates_owner_unique');
        });

        $this->convertJsonToJsonb('certificate_templates', ['elements', 'eligibility']);

        Schema::table('certificates', function (Blueprint $table): void {
            $table->foreignId('certificate_template_id')
                ->nullable()
                ->after('certificateable_id')
                ->constrained('certificate_templates')
                ->nullOnDelete();
            $table->unsignedInteger('template_version')->nullable()->after('certificate_template_id');
            $table->json('data_snapshot')->nullable()->after('template_version');
            $table->string('pdf_status')->default('pending')->after('file_path');
            $table->text('pdf_error')->nullable()->after('pdf_status');
        });

        $this->convertJsonToJsonb('certificates', ['data_snapshot']);

        DB::table('certificates')
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->update(['pdf_status' => 'generated']);

        app(CertificateTemplateBackfill::class)->backfillTrainingPrograms();
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('certificate_template_id');
            $table->dropColumn(['template_version', 'data_snapshot', 'pdf_status', 'pdf_error']);
        });

        Schema::dropIfExists('certificate_templates');
    }

    /**
     * @param  list<string>  $columns
     */
    private function convertJsonToJsonb(string $table, array $columns): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($columns as $column) {
            DB::statement(sprintf(
                'ALTER TABLE %s ALTER COLUMN %s TYPE jsonb USING %s::jsonb',
                $table,
                $column,
                $column,
            ));
        }
    }
};
