<?php

namespace Tests\Unit\Filament;

use App\Filament\Support\TrainingEntityFormSupport;
use App\Models\TrainingProgram;
use PHPUnit\Framework\TestCase;

class TrainingProgramPathLinkSettingsTest extends TestCase
{
    public function test_program_path_link_form_state_reflects_record(): void
    {
        $linked = new TrainingProgram([
            'learning_path_id' => 7,
        ]);

        $standalone = new TrainingProgram([
            'learning_path_id' => null,
        ]);

        $this->assertSame([
            'is_linked_to_path' => true,
            'learning_path_id' => 7,
        ], TrainingEntityFormSupport::programPathLinkFormState($linked));

        $this->assertSame([
            'is_linked_to_path' => false,
            'learning_path_id' => null,
        ], TrainingEntityFormSupport::programPathLinkFormState($standalone));
    }

    public function test_unlinking_clears_path_and_sort_order(): void
    {
        $result = TrainingEntityFormSupport::applyProgramPathLinkSettings([
            'is_linked_to_path' => false,
            'learning_path_id' => 3,
            'path_sort_order' => 2,
            'capacity' => 10,
        ]);

        $this->assertNull($result['learning_path_id']);
        $this->assertNull($result['path_sort_order']);
        $this->assertSame(10, $result['capacity']);
    }

    public function test_linking_clears_registration_fields(): void
    {
        $result = TrainingEntityFormSupport::applyProgramPathLinkSettings([
            'is_linked_to_path' => true,
            'learning_path_id' => 3,
            'competency_track' => 'self',
            'capacity' => 10,
            'registration_start' => '2026-06-01',
            'registration_end' => '2026-06-10',
            'weekdays' => [0, 1],
            'path_sort_order' => 5,
        ]);

        $this->assertSame(3, $result['learning_path_id']);
        $this->assertNull($result['capacity']);
        $this->assertNull($result['registration_start']);
        $this->assertNull($result['registration_end']);
        $this->assertNull($result['weekdays']);
        $this->assertSame(5, $result['path_sort_order']);
    }

    public function test_unlinking_from_inline_overrides_clears_learning_path_id(): void
    {
        $result = TrainingEntityFormSupport::applyProgramPathLinkSettings([
            'is_linked_to_path' => false,
            'learning_path_id' => 9,
        ]);

        $this->assertNull($result['learning_path_id']);
    }
}
