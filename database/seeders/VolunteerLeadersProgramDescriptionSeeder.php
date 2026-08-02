<?php

namespace Database\Seeders;

use App\Models\TrainingProgram;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Sets the durable public description for «قادة التطوع». Safe to re-run.
 */
class VolunteerLeadersProgramDescriptionSeeder extends Seeder
{
    public const TITLE_NEEDLE = 'قادة التطوع';

    /**
     * Marker used for idempotency checks (must appear in DESCRIPTION).
     */
    public const DESCRIPTION_MARKER = 'موضحة في تفاصيل البرنامج';

    /**
     * Canonical public description.
     */
    public const DESCRIPTION = <<<'HTML'
<p>برنامج تأهيلي لإعداد وتطوير قادة العمل التطوعي، من خلال تنمية المهارات القيادية والإدارية والتطبيق العملي، بما يسهم في بناء قيادات قادرة على قيادة المبادرات وإحداث أثر مجتمعي مستدام.</p>
<p></p>
<p><strong>الفئة المستهدفة:</strong></p>
<p>القادة الشباب، وأعضاء الفرق التطوعية، ومسؤولو التطوع في الجهات الحكومية والمنظمات غير الربحية.</p>
<p></p>
<p><strong>مميزات البرنامج:</strong></p>
<p>شهادة معتمدة</p>
<p></p>
<p><strong>أسلوب التنفيذ:</strong></p>
<p>يتضمن عددًا من الأيام الحضورية وعن بعد، موضحة في تفاصيل البرنامج.</p>
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
