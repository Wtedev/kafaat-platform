<?php

namespace Tests\Unit\Models;

use App\Enums\ProgramDeliveryMode;
use App\Models\TrainingProgram;
use Tests\TestCase;

class TrainingProgramDeliveryTest extends TestCase
{
    public function test_delivery_mode_description_for_remote(): void
    {
        $program = new TrainingProgram([
            'delivery_mode' => ProgramDeliveryMode::Remote,
        ]);

        $this->assertSame('عن بُعد', $program->deliveryModeDescription());
    }

    public function test_delivery_mode_description_for_in_person_with_venue(): void
    {
        $program = new TrainingProgram([
            'delivery_mode' => ProgramDeliveryMode::InPerson,
            'venue' => 'قاعة الأمير',
        ]);

        $this->assertSame('حضوري — قاعة الأمير', $program->deliveryModeDescription());
    }

    public function test_delivery_mode_description_for_hybrid_with_venue(): void
    {
        $program = new TrainingProgram([
            'delivery_mode' => ProgramDeliveryMode::Hybrid,
            'venue' => 'بريدة - بيت الثقافة',
        ]);

        $this->assertSame(
            'هايبرد (حضوري وعن بعد) — بريدة - بيت الثقافة',
            $program->deliveryModeDescription(),
        );
        $this->assertTrue($program->delivery_mode->hasPhysicalComponent());
    }

    public function test_delivery_mode_description_is_null_when_unset(): void
    {
        $program = new TrainingProgram();

        $this->assertNull($program->deliveryModeDescription());
    }
}
