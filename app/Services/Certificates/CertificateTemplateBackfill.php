<?php

namespace App\Services\Certificates;

use App\Models\LearningPath;
use App\Models\TrainingProgram;
use App\Models\VolunteerOpportunity;
use Illuminate\Support\Facades\DB;

/**
 * ينشئ قالب المتوسط 75 لكل برنامج موجود، ويربط الشهادات الصادرة به.
 */
class CertificateTemplateBackfill
{
    public const DATA_FORUM_SLUG = 'multaqa-tahlil-al-bayanat-2';

    public function backfillTrainingPrograms(): int
    {
        $type = (new TrainingProgram)->getMorphClass();
        $now = now();
        $created = 0;

        DB::table('training_programs')
            ->select(['id', 'created_by', 'slug'])
            ->orderBy('id')
            ->chunkById(200, function ($programs) use ($type, $now, &$created): void {
                foreach ($programs as $program) {
                    $eligibility = $this->programEligibilityJson((string) $program->slug);
                    $existingId = DB::table('certificate_templates')
                        ->where('owner_type', $type)
                        ->where('owner_id', $program->id)
                        ->value('id');

                    if ($existingId === null) {
                        $existingId = DB::table('certificate_templates')->insertGetId([
                            'owner_type' => $type,
                            'owner_id' => $program->id,
                            'background_path' => null,
                            'background_disk' => 'public',
                            'page_width_mm' => 297,
                            'page_height_mm' => 210,
                            'elements' => '[]',
                            'eligibility' => $eligibility,
                            'status' => 'ready',
                            'version' => 1,
                            'created_by' => $program->created_by,
                            'updated_by' => $program->created_by,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $created++;
                    }

                    DB::table('certificates')
                        ->where('certificateable_type', $type)
                        ->where('certificateable_id', $program->id)
                        ->whereNull('certificate_template_id')
                        ->update([
                            'certificate_template_id' => $existingId,
                            'template_version' => 1,
                        ]);
                }
            });

        return $created;
    }

    /**
     * البرامج الحالية: متوسط الحضور والدرجة 75.
     * ملتقى البيانات: حضور كل أيام البرنامج، كما في نص الشهادة الظاهر على main.
     */
    private function programEligibilityJson(string $slug): string
    {
        if ($slug === self::DATA_FORUM_SLUG) {
            return json_encode([
                'mode' => 'attendance_only',
                'min_attendance' => 100,
                'min_score' => null,
                'min_average' => null,
                'require_completed_status' => true,
                'require_activity_ended' => false,
            ], JSON_UNESCAPED_UNICODE);
        }

        return json_encode([
            'mode' => 'average',
            'min_attendance' => null,
            'min_score' => null,
            'min_average' => 75,
            'require_completed_status' => true,
            'require_activity_ended' => false,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function enableAutoIssueForTrainingPrograms(): void
    {
        $type = (new TrainingProgram)->getMorphClass();

        DB::table('certificate_templates')
            ->where('owner_type', $type)
            ->update(['auto_issue' => false]);
    }

    public function backfillLearningPaths(): int
    {
        $type = (new LearningPath)->getMorphClass();
        $eligibility = json_encode([
            'mode' => 'completed_all_courses',
            'min_attendance' => null,
            'min_score' => null,
            'min_average' => null,
            'min_approved_hours' => null,
            'require_completed_status' => true,
            'require_activity_ended' => false,
        ], JSON_UNESCAPED_UNICODE);

        return $this->backfillOwners('learning_paths', $type, $eligibility, false);
    }

    public function backfillVolunteerOpportunities(): int
    {
        $type = (new VolunteerOpportunity)->getMorphClass();
        $created = 0;
        $now = now();

        DB::table('volunteer_opportunities')
            ->select(['id', 'created_by', 'hours_expected'])
            ->orderBy('id')
            ->chunkById(200, function ($opportunities) use ($type, $now, &$created): void {
                foreach ($opportunities as $opportunity) {
                    $hours = round((float) $opportunity->hours_expected, 2);
                    $autoIssue = false;
                    $eligibility = json_encode([
                        'mode' => 'min_approved_hours',
                        'min_attendance' => null,
                        'min_score' => null,
                        'min_average' => null,
                        'min_approved_hours' => $hours,
                        'require_completed_status' => true,
                        'require_activity_ended' => false,
                    ], JSON_UNESCAPED_UNICODE);

                    if ($this->insertTemplate($type, (int) $opportunity->id, $eligibility, $autoIssue, $opportunity->created_by, $now)) {
                        $created++;
                    }
                }
            });

        return $created;
    }

    private function backfillOwners(string $table, string $type, string $eligibility, bool $autoIssue): int
    {
        $created = 0;
        $now = now();

        DB::table($table)
            ->select(['id', 'created_by'])
            ->orderBy('id')
            ->chunkById(200, function ($owners) use ($type, $eligibility, $autoIssue, $now, &$created): void {
                foreach ($owners as $owner) {
                    if ($this->insertTemplate($type, (int) $owner->id, $eligibility, $autoIssue, $owner->created_by, $now)) {
                        $created++;
                    }
                }
            });

        return $created;
    }

    private function insertTemplate(string $type, int $ownerId, string $eligibility, bool $autoIssue, mixed $createdBy, mixed $now): bool
    {
        $existingId = DB::table('certificate_templates')
            ->where('owner_type', $type)
            ->where('owner_id', $ownerId)
            ->value('id');

        $created = false;
        if ($existingId === null) {
            $existingId = DB::table('certificate_templates')->insertGetId([
                'owner_type' => $type,
                'owner_id' => $ownerId,
                'background_path' => null,
                'background_disk' => 'public',
                'page_width_mm' => 297,
                'page_height_mm' => 210,
                'elements' => '[]',
                'eligibility' => $eligibility,
                'auto_issue' => $autoIssue,
                'status' => 'ready',
                'version' => 1,
                'created_by' => $createdBy,
                'updated_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $created = true;
        }

        DB::table('certificates')
            ->where('certificateable_type', $type)
            ->where('certificateable_id', $ownerId)
            ->whereNull('certificate_template_id')
            ->update([
                'certificate_template_id' => $existingId,
                'template_version' => 1,
            ]);

        return $created;
    }
}
