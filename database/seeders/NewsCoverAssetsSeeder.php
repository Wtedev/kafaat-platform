<?php

namespace Database\Seeders;

use App\Models\News;
use App\Models\NewsImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Attaches durable cover images to named news articles by flexible title match.
 *
 * Covers live under public/images/news/ (git-backed) so Railway redeploys keep the
 * files without relying on the ephemeral public-disk volume. Also mirrored onto
 * the Laravel public disk under news/images/ when that disk is writable.
 *
 * Safe to re-run: updates image + primary news_images path only when changed.
 * Does not delete staff gallery images or run NewsSeeder.
 */
class NewsCoverAssetsSeeder extends Seeder
{
    /**
     * @return list<array{
     *   key: string,
     *   file: string,
     *   label: string,
     *   needles: list<string>
     * }>
     */
    public static function covers(): array
    {
        return [
            [
                'key' => 'business-analysis-camp',
                'file' => 'business-analysis-camp.jpg',
                'label' => 'مقتطفات معسكر تحليل الاعمال',
                'needles' => [
                    'مقتطفات معسكر تحليل',
                    'معسكر تحليل الاعمال',
                    'معسكر تحليل الأعمال',
                    'معسكر تحليل',
                ],
            ],
            [
                'key' => 'afaq-supervision-visit',
                'file' => 'afaq-supervision-visit.jpg',
                'label' => 'زيارة فريق الإشراف على مرحلة التطوير في مبادرة آفاق',
                'needles' => [
                    'زيارة فريق الإشراف',
                    'مرحلة التطوير في مبادرة',
                    'مبادرة آف',
                    'مبادرة أف',
                    'آفــاق',
                    'آفاق',
                    'أفاق',
                ],
            ],
            [
                'key' => 'expert-houses-followup',
                'file' => 'expert-houses-followup.jpg',
                'label' => 'متابعة أعمال مشروع بيوت الخبرة',
                'needles' => [
                    'مشروع_بيوت_الخبرة',
                    'بيوت_الخبرة',
                    'بيوت الخبرة',
                ],
            ],
            [
                'key' => 'mudrik-closing',
                'file' => 'mudrik-closing.jpg',
                'label' => 'اختتام برنامج مدرك',
                'needles' => [
                    'اختتام برنامج مدرك',
                    'اختتام برنامج مدارك',
                    'برنامج مدرك',
                    'برنامج مدارك',
                ],
            ],
            [
                'key' => 'general-assembly-2026',
                'file' => 'general-assembly-2026.jpg',
                'label' => 'انعقاد الجمعية العمومية العادية 2026',
                'needles' => [
                    'الجمعية العمومية العادية',
                    'انعقاد الجمعية العمومية',
                    'الجمعية العمومية',
                    'جمعية_كفاءات',
                    'جمعية كفاءات',
                ],
            ],
        ];
    }

    public function run(): void
    {
        if (! Schema::hasTable('news')) {
            $this->command?->warn('NewsCoverAssetsSeeder: news table missing. Skipping.');

            return;
        }

        $hasImagesTable = Schema::hasTable('news_images');
        $updated = 0;
        $matched = 0;
        $missing = 0;

        foreach (self::covers() as $cover) {
            $relativePath = 'images/news/'.$cover['file'];

            if (! $this->ensureCoverPublished($cover['file'], $relativePath)) {
                $missing++;
                continue;
            }

            $news = $this->findBestMatch($cover['needles'], $cover['key']);

            if ($news === null) {
                $this->command?->warn(sprintf(
                    'NewsCoverAssetsSeeder: no news title matching «%s» (%s). Cover published at %s.',
                    $cover['label'],
                    $cover['key'],
                    $relativePath,
                ));

                continue;
            }

            $matched++;

            if ($this->attachCover($news, $relativePath, $hasImagesTable)) {
                $updated++;
                $this->command?->info(sprintf(
                    'NewsCoverAssetsSeeder: news #%d «%s» → %s',
                    $news->id,
                    $news->title,
                    $relativePath,
                ));
            } else {
                $this->command?->info(sprintf(
                    'NewsCoverAssetsSeeder: news #%d «%s» already has %s',
                    $news->id,
                    $news->title,
                    $relativePath,
                ));
            }
        }

        $this->command?->info(sprintf(
            'NewsCoverAssetsSeeder: matched %d, updated %d, missing assets %d.',
            $matched,
            $updated,
            $missing,
        ));
    }

    /**
     * @param  list<string>  $needles
     */
    private function findBestMatch(array $needles, string $key): ?News
    {
        $query = News::query()->where(function ($q) use ($needles): void {
            foreach ($needles as $needle) {
                $q->orWhere('title', 'like', '%'.$needle.'%');
            }
        });

        // Prefer published articles; still allow a draft match so covers can be seeded early.
        $candidates = $query
            ->orderByRaw('CASE WHEN published_at IS NOT NULL AND published_at <= ? THEN 0 ELSE 1 END', [now()])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'image', 'published_at']);

        if ($candidates->isEmpty()) {
            return null;
        }

        // Score by earliest matching needle (more specific first in covers()).
        $best = null;
        $bestScore = PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            $score = $this->needleScore($candidate->title, $needles);

            // Prefer rows that already mention 2026 for the general-assembly cover.
            if ($key === 'general-assembly-2026' && str_contains((string) $candidate->title, '2026')) {
                $score -= 10;
            }

            // Prefer explicit «اختتام» for mudrik closing vs generic مدارك launch teasers.
            if ($key === 'mudrik-closing' && str_contains((string) $candidate->title, 'اختتام')) {
                $score -= 10;
            }

            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        if ($candidates->count() > 1) {
            $this->command?->warn(sprintf(
                'NewsCoverAssetsSeeder: %d title matches for %s — using news #%d «%s».',
                $candidates->count(),
                $key,
                $best?->id,
                $best?->title,
            ));
        }

        return $best;
    }

    /**
     * @param  list<string>  $needles
     */
    private function needleScore(string $title, array $needles): int
    {
        foreach ($needles as $index => $needle) {
            if (str_contains($title, $needle)) {
                return $index;
            }
        }

        return count($needles);
    }

    private function attachCover(News $news, string $relativePath, bool $hasImagesTable): bool
    {
        $changed = false;

        if ($news->image !== $relativePath) {
            $news->forceFill(['image' => $relativePath])->saveQuietly();
            $changed = true;
        }

        if (! $hasImagesTable) {
            return $changed;
        }

        $primary = NewsImage::query()
            ->where('news_id', $news->id)
            ->where('is_primary', true)
            ->first();

        if ($primary === null) {
            NewsImage::query()->create([
                'news_id' => $news->id,
                'path' => $relativePath,
                'is_primary' => true,
                'sort_order' => 0,
            ]);

            return true;
        }

        if ($primary->path !== $relativePath) {
            $primary->forceFill(['path' => $relativePath])->save();

            return true;
        }

        return $changed;
    }

    private function ensureCoverPublished(string $filename, string $relativePath): bool
    {
        $dest = public_path($relativePath);
        $source = database_path('seeders/assets/news/'.$filename);

        if (! File::exists($source)) {
            if (File::exists($dest) && File::size($dest) > 0) {
                $this->mirrorToPublicDisk($dest, $filename);

                return true;
            }

            $this->command?->warn('NewsCoverAssetsSeeder: missing asset at seeders/assets/news/'.$filename);

            return false;
        }

        File::ensureDirectoryExists(dirname($dest));
        File::copy($source, $dest);

        if (! File::exists($dest) || File::size($dest) <= 0) {
            return false;
        }

        $this->mirrorToPublicDisk($dest, $filename);

        return true;
    }

    /**
     * Best-effort copy onto the Laravel public disk (volume/S3) without failing the seeder.
     */
    private function mirrorToPublicDisk(string $localPath, string $filename): void
    {
        $diskPath = 'news/images/'.$filename;

        try {
            Storage::disk('public')->put($diskPath, File::get($localPath));
        } catch (\Throwable $e) {
            $this->command?->warn(sprintf(
                'NewsCoverAssetsSeeder: could not mirror %s to public disk (%s). Git path still used.',
                $diskPath,
                $e->getMessage(),
            ));
        }
    }
}
