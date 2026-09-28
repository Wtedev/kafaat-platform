<?php

namespace Tests\Unit\Models;

use App\Models\TrainingProgram;
use Database\Seeders\VolunteerLeadersProgramCoverSeeder;
use Tests\TestCase;

class TrainingProgramCoverFitTest extends TestCase
{
    public function test_bundled_program_cover_uses_contain_fit(): void
    {
        $program = new TrainingProgram([
            'image' => VolunteerLeadersProgramCoverSeeder::COVER_RELATIVE_PATH,
        ]);

        $this->assertTrue($program->imageUsesContainFit());
        $this->assertSame(
            '/'.VolunteerLeadersProgramCoverSeeder::COVER_RELATIVE_PATH,
            $program->imagePublicUrl(),
        );
    }

    public function test_filestore_cover_keeps_default_cover_fit(): void
    {
        $program = new TrainingProgram([
            'image' => 'training-programs/images/custom.jpg',
        ]);

        $this->assertFalse($program->imageUsesContainFit());
        $this->assertNull($program->imageHeroSurfaceColor());
    }

    public function test_data_forum_cover_uses_sampled_hero_surface_color(): void
    {
        $program = new TrainingProgram([
            'image' => 'images/programs/multaqa-tahlil-al-bayanat-2.jpg',
        ]);

        $this->assertTrue($program->imageUsesContainFit());
        $this->assertSame('#061824', $program->imageHeroSurfaceColor());
    }

    public function test_fok_cover_uses_sampled_hero_surface_color(): void
    {
        $program = new TrainingProgram([
            'image' => 'images/programs/fok.jpg',
        ]);

        $this->assertTrue($program->imageUsesContainFit());
        $this->assertSame('#265485', $program->imageHeroSurfaceColor());
    }
}
