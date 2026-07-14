<?php

namespace Database\Seeders;

use App\Models\TrainingProgram;
use App\Support\TrainingProgramExtrasSupport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Sets durable «مقدمو البرنامج» for قادة التطوع. Safe to re-run.
 */
class VolunteerLeadersProgramPresentersSeeder extends Seeder
{
    public const TITLE_NEEDLE = 'قادة التطوع';

    /**
     * @var list<array{name: string, role: string}>
     */
    public const PRESENTERS = [
        ['name' => 'أحمد الرفاعي', 'role' => ''],
        ['name' => 'د. محمد النصار', 'role' => ''],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('training_programs')) {
            $this->command?->warn('VolunteerLeadersProgramPresentersSeeder: training_programs missing. Skipping.');

            return;
        }

        if (! Schema::hasColumn('training_programs', 'program_presenters')) {
            $this->command?->warn('VolunteerLeadersProgramPresentersSeeder: program_presenters column missing. Skipping.');

            return;
        }

        $canonical = TrainingProgramExtrasSupport::normalizeProgramPresenters(self::PRESENTERS);

        $matched = TrainingProgram::query()
            ->where('title', 'like', '%'.self::TITLE_NEEDLE.'%')
            ->get(['id', 'title', 'program_presenters']);

        if ($matched->isEmpty()) {
            $this->command?->warn(
                'VolunteerLeadersProgramPresentersSeeder: no training program title matching «'.self::TITLE_NEEDLE.'».'
            );

            return;
        }

        $updated = 0;

        foreach ($matched as $program) {
            $current = TrainingProgramExtrasSupport::normalizeProgramPresenters(
                is_array($program->program_presenters) ? $program->program_presenters : null,
            );

            if ($current === $canonical) {
                continue;
            }

            $program->forceFill(['program_presenters' => $canonical])->save();
            $updated++;
        }

        $this->command?->info(sprintf(
            'VolunteerLeadersProgramPresentersSeeder: matched %d program(s), updated %d.',
            $matched->count(),
            $updated,
        ));
    }
}
