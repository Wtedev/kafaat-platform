<?php

namespace Database\Seeders;

use App\Models\TrainingProgram;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Sets durable program + registration dates for «قادة التطوع». Safe to re-run.
 *
 * Program: 2026-08-03 → 2026-09-01 (~30 days inclusive)
 * Registration window: 2026-07-22 → 2026-08-05 (inclusive; covers first in-person block)
 */
class VolunteerLeadersProgramDatesSeeder extends Seeder
{
    public const TITLE_NEEDLE = 'قادة التطوع';

    public const START_DATE = '2026-08-03';

    public const END_DATE = '2026-09-01';

    public const REGISTRATION_START = '2026-07-22';

    /**
     * Inclusive last day. Prefer 2026-08-05 over 2026-08-03: end-of-day inclusive would
     * keep 08-03 open today, but closing tonight is too tight during in-person days 3–5 Aug.
     * Predeploy re-seed locks this window open through that date.
     */
    public const REGISTRATION_END = '2026-08-05';

    public function run(): void
    {
        if (! Schema::hasTable('training_programs')) {
            $this->command?->warn('VolunteerLeadersProgramDatesSeeder: training_programs missing. Skipping.');

            return;
        }

        $matched = TrainingProgram::query()
            ->where('title', 'like', '%'.self::TITLE_NEEDLE.'%')
            ->get(['id', 'title', 'start_date', 'end_date', 'registration_start', 'registration_end']);

        if ($matched->isEmpty()) {
            $this->command?->warn(
                'VolunteerLeadersProgramDatesSeeder: no training program title matching «'.self::TITLE_NEEDLE.'».'
            );

            return;
        }

        // Pass Y-m-d strings (not Carbon startOfDay): Asia/Riyadh midnight converts to the
        // previous UTC calendar day and PostgreSQL DATE columns would persist one day early.
        $updated = 0;

        foreach ($matched as $program) {
            $currentStart = $program->start_date?->toDateString();
            $currentEnd = $program->end_date?->toDateString();
            $currentRegStart = $program->registration_start?->toDateString();
            $currentRegEnd = $program->registration_end?->toDateString();

            if (
                $currentStart === self::START_DATE
                && $currentEnd === self::END_DATE
                && $currentRegStart === self::REGISTRATION_START
                && $currentRegEnd === self::REGISTRATION_END
            ) {
                continue;
            }

            $program->forceFill([
                'start_date' => self::START_DATE,
                'end_date' => self::END_DATE,
                'registration_start' => self::REGISTRATION_START,
                'registration_end' => self::REGISTRATION_END,
            ])->save();
            $updated++;
        }

        $this->command?->info(sprintf(
            'VolunteerLeadersProgramDatesSeeder: matched %d program(s), updated %d (start=%s end=%s reg=%s→%s).',
            $matched->count(),
            $updated,
            self::START_DATE,
            self::END_DATE,
            self::REGISTRATION_START,
            self::REGISTRATION_END,
        ));
    }
}
