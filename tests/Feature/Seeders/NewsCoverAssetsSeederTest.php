<?php

namespace Tests\Feature\Seeders;

use App\Models\News;
use App\Support\PublicDiskPath;
use Database\Seeders\NewsCoverAssetsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsCoverAssetsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_attaches_covers_by_flexible_title_match(): void
    {
        Storage::fake('public');

        $camp = News::query()->create([
            'title' => 'مقتطفات معسكر تحليل الاعمال',
            'slug' => 'ba-camp',
            'excerpt' => 't',
            'content' => 't',
            'published_at' => now()->subDay(),
        ]);

        $afaq = News::query()->create([
            'title' => 'زيارة فريق الإشراف على مرحلة التطوير في مبادرة آفــاق',
            'slug' => 'afaq-visit',
            'excerpt' => 't',
            'content' => 't',
            'published_at' => now()->subDay(),
        ]);

        $houses = News::query()->create([
            'title' => 'متابعة أعمال #مشروع_بيوت_الخبرة',
            'slug' => 'expert-houses',
            'excerpt' => 't',
            'content' => 't',
            'published_at' => now()->subDay(),
        ]);

        $mudrik = News::query()->create([
            'title' => 'اختتام برنامج مدرك',
            'slug' => 'mudrik-close',
            'excerpt' => 't',
            'content' => 't',
            'published_at' => now()->subDay(),
        ]);

        // Teaser should lose to «اختتام» when both match مدارك/مدرك needles.
        News::query()->create([
            'title' => 'قريبًا: الإصدار الرابع من برنامج مدارك',
            'slug' => 'madarik-teaser',
            'excerpt' => 't',
            'content' => 't',
            'published_at' => now()->subDays(2),
        ]);

        $assembly = News::query()->create([
            'title' => 'انعقاد الجمعية العمومية العادية لجمعية كفاءات الأهلية للعام المالي 2026',
            'slug' => 'ga-2026',
            'excerpt' => 't',
            'content' => 't',
            'published_at' => now()->subDay(),
        ]);

        $this->seed(NewsCoverAssetsSeeder::class);

        $camp->refresh();
        $afaq->refresh();
        $houses->refresh();
        $mudrik->refresh();
        $assembly->refresh();

        $this->assertSame('images/news/business-analysis-camp.jpg', $camp->image);
        $this->assertSame('images/news/afaq-supervision-visit.jpg', $afaq->image);
        $this->assertSame('images/news/expert-houses-followup.jpg', $houses->image);
        $this->assertSame('images/news/mudrik-closing.jpg', $mudrik->image);
        $this->assertSame('images/news/general-assembly-2026.jpg', $assembly->image);

        $this->assertSame(
            'images/news/business-analysis-camp.jpg',
            $camp->primaryImageRecord()?->path,
        );

        $this->assertSame(
            '/images/news/business-analysis-camp.jpg',
            PublicDiskPath::url($camp->image),
        );

        $this->assertTrue(Storage::disk('public')->exists('news/images/business-analysis-camp.jpg'));

        // Idempotent
        $this->seed(NewsCoverAssetsSeeder::class);
        $this->assertSame(1, $camp->images()->where('is_primary', true)->count());
    }
}
