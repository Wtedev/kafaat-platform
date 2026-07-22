<?php

namespace Database\Seeders;

use App\Models\TrainingProgram;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Clears «مقدمو البرنامج» for قادة التطوع (public section removed). Safe to re-run.
 */
class VolunteerLeadersProgramPresentersSeeder extends Seeder
{
    public const TITLE_NEEDLE = 'قادة التطوع';

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
            if ($program->program_presenters === null) {
                continue;
            }

            $program->forceFill(['program_presenters' => null])->save();
            $updated++;
        }

        $this->command?->info(sprintf(
            'VolunteerLeadersProgramPresentersSeeder: matched %d program(s), cleared presenters on %d.',
            $matched->count(),
            $updated,
        ));
    }
}
