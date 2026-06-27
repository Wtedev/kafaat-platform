<?php

namespace Tests\Unit\Filament;

use App\Filament\Support\TrainingEntitySettingsState;
use App\Filament\Support\TrainingEntityFormChangeSummarizer;
use PHPUnit\Framework\TestCase;

class TrainingEntitySettingsTabStateTest extends TestCase
{
    public function test_null_baseline_is_treated_as_no_changes(): void
    {
        $this->assertTrue(TrainingEntitySettingsState::changesAreEmpty(
            null,
            ['title' => 'جديد'],
            ['title' => 'اسم البرنامج'],
        ));
    }

    public function test_captured_baseline_detects_title_change(): void
    {
        $baseline = TrainingEntitySettingsState::snapshotRawFormState([
            'title' => 'قديم',
            'capacity_unlimited' => false,
        ]);

        $this->assertFalse(TrainingEntitySettingsState::changesAreEmpty(
            $baseline,
            ['title' => 'جديد', 'capacity_unlimited' => false],
            ['title' => 'اسم البرنامج'],
        ));
    }

    public function test_captured_baseline_ignores_unchanged_fields(): void
    {
        $state = [
            'title' => 'نفس العنوان',
            'capacity_unlimited' => true,
            'notify_audience' => false,
        ];

        $baseline = TrainingEntitySettingsState::snapshotRawFormState($state);

        $this->assertTrue(TrainingEntitySettingsState::changesAreEmpty(
            $baseline,
            $state,
            ['title' => 'اسم البرنامج'],
        ));
    }

    public function test_snapshot_falls_back_to_comparable_snapshot_for_non_json_values(): void
    {
        $object = new \stdClass;
        $object->name = 'test';

        $snapshot = TrainingEntitySettingsState::snapshotRawFormState([
            'title' => 'برنامج',
            'meta' => $object,
        ]);

        $this->assertSame('برنامج', $snapshot['title']);
        $this->assertArrayHasKey('meta', $snapshot);
    }

    public function test_summarizer_detects_toggle_change_after_snapshot(): void
    {
        $baseline = TrainingEntitySettingsState::snapshotRawFormState([
            'capacity_unlimited' => false,
        ]);

        $changes = TrainingEntityFormChangeSummarizer::describeChanges(
            $baseline,
            ['capacity_unlimited' => true],
            ['capacity_unlimited' => 'تسجيل غير محدود'],
        );

        $this->assertCount(1, $changes);
    }
}
