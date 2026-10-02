<?php

namespace Database\Seeders;

use App\Enums\TrainingProgramKind;
use App\Models\TrainingProgram;
use App\Support\ProgramAcceptanceConditions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * يضبط نوع «ملتقى تحليل البيانات» ونبذته المنشورة. آمن لإعادة التشغيل.
 */
class DataForumProgramSeeder extends Seeder
{
    public const SLUG = 'multaqa-tahlil-al-bayanat-2';

    public const DESCRIPTION = 'ملتقى تدريبي تطبيقي يفتح للمشاركين آفاق تحليل البيانات باستخدام لغة Python، ويمكنهم من تحويل البيانات إلى رؤى تدعم اتخاذ القرار وتصنع أثرًا في القطاع غير الربحي.<br><br>تُعقد الجلسات النظرية من الساعة 4:00 حتى 7:00 مساءً.<br><br><strong>محاور الملتقى</strong><ul><li>هندسة الأوامر.</li><li>تحليل البيانات باستخدام لغة Python.</li><li>أتمتة سير العمل.</li><li>بناء مهارات مخصّصة «Skills» في بيئة Claude.</li><li>بناء وكيل ذكي لتحليل البيانات.</li><li>هاكاثون تحليل البيانات في القطاع غير الربحي.</li></ul>';

    /**
     * @var list<array{title: string, facilitators: string}>
     */
    public const STAGES = [
        [
            'title' => 'التعلّم والتأسيس المعرفي — ثلاثة أيام',
            'facilitators' => 'جلسات نظرية للتعرّف على المفاهيم والأدوات الأساسية في محاور الملتقى.',
        ],
        [
            'title' => 'التطبيقات العملية — يومان',
            'facilitators' => 'تطبيقات تدريبية لتحويل المعرفة المكتسبة إلى مهارات عملية.',
        ],
        [
            'title' => 'تنفيذ المشاريع — أسبوع',
            'facilitators' => 'تطوير مشاريع تطبيقية في تحليل البيانات، بمشاركة فردية أو ضمن فرق ومجموعات.',
        ],
        [
            'title' => 'الإرشاد والمتابعة — بالتزامن مع أسبوع المشاريع',
            'facilitators' => 'دعم المشاركين ومتابعة تقدّم مشاريعهم، ومساعدتهم على معالجة التحديات وتحسين المخرجات.',
        ],
        [
            'title' => 'تقييم المشاريع وترشيح المتميز منها — يوم واحد',
            'facilitators' => 'تقييم المشاريع المقدّمة وترشيح المشاريع المتميزة وفق معايير التقييم المعتمدة للملتقى.',
        ],
        [
            'title' => 'إعلان الفائزين — يوم واحد',
            'facilitators' => 'إعلان المشاريع الفائزة وتكريم أصحابها بالجوائز المالية.',
        ],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('training_programs')) {
            $this->command?->warn('DataForumProgramSeeder: training_programs missing. Skipping.');

            return;
        }

        $programs = TrainingProgram::query()
            ->where('slug', self::SLUG)
            ->orWhere('title', 'like', '%ملتقى تحليل البيانات%')
            ->get();

        if ($programs->isEmpty()) {
            $this->command?->warn('DataForumProgramSeeder: no matching program.');

            return;
        }

        $updated = 0;

        foreach ($programs as $program) {
            $topics = is_array($program->session_topics) ? $program->session_topics : [];
            $conditions = self::acceptanceConditions($program);
            $dirty = $program->program_kind !== TrainingProgramKind::Forum
                || trim((string) $program->description) !== self::DESCRIPTION
                || ! $program->session_topics_enabled
                || $topics !== self::STAGES
                || (bool) $program->auto_accept_registrations
                || $program->acceptance_conditions !== $conditions;

            if (! $dirty) {
                continue;
            }

            $program->forceFill([
                'program_kind' => TrainingProgramKind::Forum,
                'description' => self::DESCRIPTION,
                'session_topics_enabled' => true,
                'session_topics' => self::STAGES,
                'auto_accept_registrations' => false,
                'acceptance_conditions' => self::acceptanceConditions($program),
            ])->save();
            $updated++;
        }

        $this->command?->info(sprintf(
            'DataForumProgramSeeder: matched %d, updated %d.',
            $programs->count(),
            $updated,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private static function acceptanceConditions(TrainingProgram $program): array
    {
        $existing = is_array($program->acceptance_conditions) ? $program->acceptance_conditions : [];

        return ProgramAcceptanceConditions::normalize(array_merge($existing, [
            'min_age' => 18,
            'max_age' => 38,
            'require_saudi_national' => true,
        ])) ?? [];
    }
}
