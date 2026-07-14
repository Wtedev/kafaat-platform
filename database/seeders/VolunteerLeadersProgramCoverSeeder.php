<?php

namespace Database\Seeders;

use App\Models\TrainingProgram;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Sets the durable public cover for «قادة التطوع» (Volunteer Leadership).
 *
 * Cover lives under public/images/programs/ (in git) so Railway redeploys keep the file
 * without relying on the ephemeral public disk volume. Safe to re-run.
 */
class VolunteerLeadersProgramCoverSeeder extends Seeder
{
    public const COVER_RELATIVE_PATH = 'images/programs/volunteer-leaders.png';

    public const TITLE_NEEDLE = 'قادة التطوع';

    public function run(): void
    {
        if (! Schema::hasTable('training_programs')) {
            $this->command?->warn('VolunteerLeadersProgramCoverSeeder: training_programs missing. Skipping.');

            return;
        }

        if (! $this->ensureCoverPublished()) {
            return;
        }

        $matched = TrainingProgram::query()
            ->where('title', 'like', '%'.self::TITLE_NEEDLE.'%')
            ->get(['id', 'title', 'image']);

        if ($matched->isEmpty()) {
            $this->command?->warn(
                'VolunteerLeadersProgramCoverSeeder: no training program title matching «'.self::TITLE_NEEDLE.'». Cover file is published; set image manually if needed.'
            );

            return;
        }

        $updated = 0;

        foreach ($matched as $program) {
            if ($program->image === self::COVER_RELATIVE_PATH) {
                continue;
            }

            $program->allowCoverUpdate = true;
            $program->forceFill(['image' => self::COVER_RELATIVE_PATH])->save();
            $updated++;
        }

        $this->command?->info(sprintf(
            'VolunteerLeadersProgramCoverSeeder: cover %s — matched %d program(s), updated %d.',
            self::COVER_RELATIVE_PATH,
            $matched->count(),
            $updated,
        ));
    }

    private function ensureCoverPublished(): bool
    {
        $dest = public_path(self::COVER_RELATIVE_PATH);
        $source = database_path('seeders/assets/programs/covers/volunteer-leaders.png');

        if (! File::exists($source)) {
            if (File::exists($dest) && File::size($dest) > 0) {
                return true;
            }

            $this->command?->warn('VolunteerLeadersProgramCoverSeeder: missing asset at seeders/assets/programs/covers/volunteer-leaders.png');

            return false;
        }

        File::ensureDirectoryExists(dirname($dest));
        File::copy($source, $dest);

        return File::exists($dest) && File::size($dest) > 0;
    }
}
