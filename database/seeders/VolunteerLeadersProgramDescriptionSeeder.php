<?php

namespace Database\Seeders;

use App\Models\TrainingProgram;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Sets the durable public description for «قادة التطوع» (hybrid delivery). Safe to re-run.
 */
class VolunteerLeadersProgramDescriptionSeeder extends Seeder
{
    public const TITLE_NEEDLE = 'قادة التطوع';

    /**
     * Canonical public description — includes هايبرد (حضوري وعن بعد) details.
     */
    public const DESCRIPTION = <<<'TXT'
برنامج تأهيلي لإعداد وتطوير قادة العمل التطوعي، من خلال تنمية المهارات القيادية والإدارية والتطبيق العملي، بما يسهم في بناء قيادات قادرة على قيادة المبادرات وإحداث أثر مجتمعي مستدام.

أسلوب التنفيذ: هايبرد (حضوري وعن بعد)
يُقدَّم البرنامج بأسلوب هايبرد يجمع بين الحضور المباشر (حضوري) والتعلم عن بعد: ستة أيام حضورية يلتقي فيها المشاركون وجهاً لوجه، وبقية أيام البرنامج تُنفَّذ عن بعد عبر المنصات الرقمية. هذا النموذج يوازن بين التفاعل المباشر والمرونة في المشاركة خلال مدة البرنامج.

الفئة المستهدفة:
القادة الشباب، وأعضاء الفرق التطوعية، ومسؤولو التطوع في الجهات الحكومية والمنظمات غير الربحية.

مميزات البرنامج:
شهادة معتمدة

شركاء البرنامج:
جمعية عضيد للخدمات التطوعية
مركز مسارات رائدة للتدريب والتطوير
الموارد البشرية والتنمية الإجتماعية
المركز الوطني لتنمية القطاع غير الربحي
TXT;

    public function run(): void
    {
        if (! Schema::hasTable('training_programs')) {
            $this->command?->warn('VolunteerLeadersProgramDescriptionSeeder: training_programs missing. Skipping.');

            return;
        }

        $matched = TrainingProgram::query()
            ->where('title', 'like', '%'.self::TITLE_NEEDLE.'%')
            ->get(['id', 'title', 'description']);

        if ($matched->isEmpty()) {
            $this->command?->warn(
                'VolunteerLeadersProgramDescriptionSeeder: no training program title matching «'.self::TITLE_NEEDLE.'».'
            );

            return;
        }

        $canonical = trim(self::DESCRIPTION);
        $updated = 0;

        foreach ($matched as $program) {
            if (trim((string) $program->description) === $canonical) {
                continue;
            }

            $program->forceFill(['description' => $canonical])->save();
            $updated++;
        }

        $this->command?->info(sprintf(
            'VolunteerLeadersProgramDescriptionSeeder: matched %d program(s), updated %d.',
            $matched->count(),
            $updated,
        ));
    }
}
