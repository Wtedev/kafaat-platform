<?php

namespace App\Console\Commands;

use App\Enums\OpportunityStatus;
use App\Enums\PathStatus;
use App\Enums\ProgramStatus;
use App\Models\LearningPath;
use App\Models\TrainingProgram;
use App\Models\VolunteerOpportunity;
use Illuminate\Console\Command;

class PublishScheduledTrainingCommand extends Command
{
    protected $signature = 'training:publish-scheduled';

    protected $description = 'ينشر البرامج والمسارات والفرص المجدولة التي حلّ موعد نشرها.';

    public function handle(): int
    {
        $now = now();
        $programs = 0;
        $paths = 0;
        $opportunities = 0;

        TrainingProgram::query()
            ->where('status', ProgramStatus::Draft)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$programs): void {
                foreach ($chunk as $program) {
                    $program->update(['status' => ProgramStatus::Published]);
                    $programs++;
                }
            });

        LearningPath::query()
            ->where('status', PathStatus::Draft)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$paths): void {
                foreach ($chunk as $path) {
                    $path->update(['status' => PathStatus::Published]);
                    $paths++;
                }
            });

        VolunteerOpportunity::query()
            ->where('status', OpportunityStatus::Draft)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$opportunities): void {
                foreach ($chunk as $opportunity) {
                    $opportunity->update(['status' => OpportunityStatus::Published]);
                    $opportunities++;
                }
            });

        $this->info("Published {$programs} program(s), {$paths} path(s), and {$opportunities} opportunity(ies).");

        return self::SUCCESS;
    }
}
