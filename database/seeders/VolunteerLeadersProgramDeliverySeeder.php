<?php

namespace Database\Seeders;

use App\Enums\ProgramDeliveryMode;
use App\Models\TrainingProgram;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Sets durable hybrid delivery + venue for «قادة التطوع». Safe to re-run.
 */
class VolunteerLeadersProgramDeliverySeeder extends Seeder
{
    public const TITLE_NEEDLE = 'قادة التطوع';

    public const DELIVERY_MODE = ProgramDeliveryMode::Hybrid;

    public const VENUE = 'بريدة - بيت الثقافة';

    public function run(): void
    {
        if (! Schema::hasTable('training_programs')) {
            $this->command?->warn('VolunteerLeadersProgramDeliverySeeder: training_programs missing. Skipping.');

            return;
        }

        if (! Schema::hasColumn('training_programs', 'delivery_mode')
            || ! Schema::hasColumn('training_programs', 'venue')) {
            $this->command?->warn('VolunteerLeadersProgramDeliverySeeder: delivery columns missing. Skipping.');

            return;
        }

        $matched = TrainingProgram::query()
            ->where('title', 'like', '%'.self::TITLE_NEEDLE.'%')
            ->get(['id', 'title', 'delivery_mode', 'venue']);

        if ($matched->isEmpty()) {
            $this->command?->warn(
                'VolunteerLeadersProgramDeliverySeeder: no training program title matching «'.self::TITLE_NEEDLE.'».'
            );

            return;
        }

        $updated = 0;

        foreach ($matched as $program) {
            $modeOk = $program->delivery_mode === self::DELIVERY_MODE;
            $venueOk = trim((string) $program->venue) === self::VENUE;

            if ($modeOk && $venueOk) {
                continue;
            }

            $program->forceFill([
                'delivery_mode' => self::DELIVERY_MODE,
                'venue' => self::VENUE,
            ])->save();
            $updated++;
        }

        $this->command?->info(sprintf(
            'VolunteerLeadersProgramDeliverySeeder: matched %d program(s), updated %d (mode=%s venue=%s).',
            $matched->count(),
            $updated,
            self::DELIVERY_MODE->value,
            self::VENUE,
        ));
    }
}
