<?php

namespace Tests\Unit\Support\Media;

use App\Support\Media\ImageSurfaceColor;
use Database\Seeders\VolunteerLeadersProgramCoverSeeder;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ImageSurfaceColorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_samples_dark_edge_color_from_data_forum_cover(): void
    {
        $this->assertSame(
            '#061824',
            ImageSurfaceColor::fromStoredPath('images/programs/multaqa-tahlil-al-bayanat-2.jpg'),
        );
    }

    public function test_falls_back_when_file_is_missing(): void
    {
        $this->assertSame(
            ImageSurfaceColor::FALLBACK,
            ImageSurfaceColor::fromStoredPath('images/programs/missing-cover.jpg'),
        );
    }

    public function test_volunteer_leaders_cover_stays_light(): void
    {
        $color = ImageSurfaceColor::fromStoredPath(VolunteerLeadersProgramCoverSeeder::COVER_RELATIVE_PATH);

        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $color);
        $this->assertNotSame(ImageSurfaceColor::FALLBACK, $color);

        $hex = ltrim($color, '#');
        $luma = (hexdec(substr($hex, 0, 2)) * 299
            + hexdec(substr($hex, 2, 2)) * 587
            + hexdec(substr($hex, 4, 2)) * 114) / 1000;
        $this->assertGreaterThan(200, $luma);
    }
}
