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
    }
}
