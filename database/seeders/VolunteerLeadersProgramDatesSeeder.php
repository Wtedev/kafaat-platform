<?php

namespace Database\Seeders;

use App\Models\TrainingProgram;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Sets durable start/end dates for «قادة التطوع». Safe to re-run.
 */
class VolunteerLeadersProgramDatesSeeder extends Seeder
{
    public const TITLE_NEEDLE = 'قادة التطوع';

    public const START_DATE = '2025-08-03';

    public const END_DATE = '2025-09-03';

    public function run(): void
    {
        if (! Schema::hasTable('training_programs')) {
            $this->command?->warn('VolunteerLeadersProgramDatesSeeder: training_programs missing. Skipping.');

            return;
        }

        $matched = TrainingProgram::query()
            ->where('title', 'like', '%'.self::TITLE_NEEDLE.'%')
            ->get(['id', 'title', 'start_date', 'end_date']);

        if ($matched->isEmpty()) {
            $this->command?->warn(
                'VolunteerLeadersProgramDatesSeeder: no training program title matching «'.self::TITLE_NEEDLE.'».'
            );

            return;
        }

        $start = Carbon::parse(self::START_DATE)->startOfDay();
        $end = Carbon::parse(self::END_DATE)->startOfDay();
        $updated = 0;

        foreach ($matched as $program) {
            $currentStart = $program->start_date?->toDateString();
            $currentEnd = $program->end_date?->toDateString();

            if ($currentStart === self::START_DATE && $currentEnd === self::END_DATE) {
                continue;
            }

            $program->forceFill([
                'start_date' => $start,
                'end_date' => $end,
            ])->save();
            $updated++;
        }

        $this->command?->info(sprintf(
            'VolunteerLeadersProgramDatesSeeder: matched %d program(s), updated %d (start=%s end=%s).',
            $matched->count(),
            $updated,
            self::START_DATE,
            self::END_DATE,
        ));
    }
}
