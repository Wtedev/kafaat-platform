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
     * Marker used for idempotency checks (must appear in DESCRIPTION).
     */
    public const HYBRID_MARKER = '6 أيام حضورية';

    /**
     * Canonical public description — hybrid delivery details at the end (no التوازن sentence).
     */
    public const DESCRIPTION = <<<'HTML'
<p>برنامج تأهيلي لإعداد وتطوير قادة العمل التطوعي، من خلال تنمية المهارات القيادية والإدارية والتطبيق العملي، بما يسهم في بناء قيادات قادرة على قيادة المبادرات وإحداث أثر مجتمعي مستدام.</p>
<p></p>
<p><strong>الفئة المستهدفة:</strong></p>
<p>القادة الشباب، وأعضاء الفرق التطوعية، ومسؤولو التطوع في الجهات الحكومية والمنظمات غير الربحية.</p>
<p></p>
<p><strong>مميزات البرنامج:</strong></p>
<p>شهادة معتمدة</p>
<p><strong>شركاء البرنامج:</strong></p>
<ul>
<li><p>جمعية عضيد للخدمات التطوعية</p></li>
<li><p>مركز مسارات رائدة للتدريب والتطوير</p></li>
<li><p>الموارد البشرية والتنمية الإجتماعية</p></li>
<li><p>المركز الوطني لتنمية القطاع غير الربحي</p></li>
</ul>
<p></p>
<p>أسلوب التنفيذ — هايبرد (حضوري وعن بعد):</p>
<p>يُقدَّم البرنامج بنمط هايبرد يجمع بوضوح بين الحضوري وعن بعد: يتضمن 6 أيام حضورية للتدريب التفاعلي والتطبيق العملي المباشر، فيما تُنفَّذ بقية أيام البرنامج وجلساته عن بعد عبر المنصات الرقمية.</p>
HTML;

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
