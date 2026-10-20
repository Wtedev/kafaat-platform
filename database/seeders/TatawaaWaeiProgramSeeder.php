<?php

namespace Database\Seeders;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Models\TrainingProgram;
use App\Support\ProgramRegistrationQuestions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the normal «تطوع واعي» program and its extra registration questions.
 * The written name-and-id pledge is not a question; commitment is the checkbox only.
 * Safe to re-run.
 */
class TatawaaWaeiProgramSeeder extends Seeder
{
    public const SLUG = 'tatawaa-waei';

    public const COVER = 'images/programs/tatawaa-waei.svg';

    public const START_DATE = '2026-10-19';

    public const END_DATE = '2026-12-31';

    public const DESCRIPTION = 'تطوع واعي برنامج تمكين وتوعية رقمية مستدام، يحوّل المتطوعين من مشاركين مؤقتين إلى صناع محتوى محترفين ورواد تطوع. يمتد الالتزام حتى نهاية ديسمبر 2026م.';

    /**
     * @var list<array{title: string, facilitators: string}>
     */
    public const TOPICS = [
        [
            'title' => 'فرص تطوعية',
            'facilitators' => 'اكتشف فرصاً متنوعة تناسب اهتماماتك ومهاراتك.',
        ],
        [
            'title' => 'صناعة الأثر',
            'facilitators' => 'ساهم في خدمة المجتمع وكن جزءاً من التغيير.',
        ],
        [
            'title' => 'تنمية المهارات',
            'facilitators' => 'طوّر مهاراتك الشخصية والمهنية أثناء التطوع.',
        ],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('training_programs') || ! Schema::hasColumn('training_programs', 'registration_questions')) {
            $this->command?->warn('TatawaaWaeiProgramSeeder: registration questions column missing. Skipping.');

            return;
        }

        $program = TrainingProgram::query()->firstOrNew(['slug' => self::SLUG]);
        $program->allowCoverUpdate = true;
        $program->forceFill([
            'title' => 'تطوع واعي',
            'slug' => self::SLUG,
            'program_kind' => TrainingProgramKind::Workshop,
            'competency_track' => CompetencyTrack::Community,
            'delivery_mode' => ProgramDeliveryMode::Remote,
            'description' => self::DESCRIPTION,
            'session_topics_enabled' => true,
            'session_topics' => self::TOPICS,
            'image' => self::COVER,
            'start_date' => self::START_DATE,
            'end_date' => self::END_DATE,
            'registration_start' => self::START_DATE,
            'registration_end' => self::END_DATE,
            'status' => ProgramStatus::Published,
            'published_at' => $program->published_at ?? now(),
            'auto_accept_registrations' => false,
            'registration_questions' => ProgramRegistrationQuestions::forTatawaaWaei(),
        ])->save();

        $this->command?->info('TatawaaWaeiProgramSeeder: program «تطوع واعي» is ready.');
    }
}
