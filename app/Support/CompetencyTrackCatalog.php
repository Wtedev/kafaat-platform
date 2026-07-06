<?php

namespace App\Support;

use App\Enums\CompetencyTrack;
use App\Models\LearningPath;
use App\Models\TrainingProgram;
use Illuminate\Support\Collection;

class CompetencyTrackCatalog
{
    /**
     * @return list<string>
     */
    public static function order(): array
    {
        return config('competency_tracks.order', []);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function tracks(): array
    {
        return config('competency_tracks.tracks', []);
    }

    public static function trackConfig(CompetencyTrack|string $track): ?array
    {
        $key = $track instanceof CompetencyTrack ? $track->value : $track;

        return self::tracks()[$key] ?? null;
    }

    /**
     * @return Collection<string, int>
     */
    public static function publishedProgramCounts(): Collection
    {
        $counts = TrainingProgram::published()
            ->whereNotNull('competency_track')
            ->selectRaw('competency_track, COUNT(*) as aggregate')
            ->groupBy('competency_track')
            ->pluck('aggregate', 'competency_track');

        return collect(self::order())->mapWithKeys(
            fn (string $key): array => [$key => (int) ($counts[$key] ?? 0)],
        );
    }

    /**
     * @return Collection<string, int>
     */
    public static function publishedPathCounts(): Collection
    {
        $counts = LearningPath::published()
            ->whereNotNull('competency_track')
            ->selectRaw('competency_track, COUNT(*) as aggregate')
            ->groupBy('competency_track')
            ->pluck('aggregate', 'competency_track');

        return collect(self::order())->mapWithKeys(
            fn (string $key): array => [$key => (int) ($counts[$key] ?? 0)],
        );
    }
}
