<?php

namespace Tests\Unit\Filament;

use App\Enums\ProgramStatus;
use App\Filament\Support\TrainingEntityFormChangeSummarizer;
use App\Filament\Support\TrainingEntityFormSupport;
use App\Models\TrainingProgram;
use Tests\TestCase;

class TrainingEntitySettingsFormSupportTest extends TestCase
{
    public function test_merge_non_dehydrated_form_flags_restores_ui_only_toggles(): void
    {
        $data = [
            'title' => 'برنامج',
            'learning_path_id' => 5,
        ];

        $raw = [
            'title' => 'برنامج',
            'learning_path_id' => 5,
            'is_linked_to_path' => true,
            'capacity_unlimited' => true,
        ];

        $merged = TrainingEntityFormSupport::mergeNonDehydratedFormFlags($data, $raw);

        $this->assertTrue($merged['is_linked_to_path']);
        $this->assertTrue($merged['capacity_unlimited']);
    }

    public function test_apply_capacity_unlimited_nulls_capacity_when_flag_is_true(): void
    {
        $result = TrainingEntityFormSupport::applyCapacityUnlimited([
            'capacity' => 25,
            'capacity_unlimited' => true,
        ]);

        $this->assertNull($result['capacity']);
        $this->assertArrayNotHasKey('capacity_unlimited', $result);
    }

    public function test_change_summarizer_treats_boolean_like_values_as_equal(): void
    {
        $this->assertFalse(TrainingEntityFormChangeSummarizer::hasChanges(
            ['auto_accept_registrations' => false],
            ['auto_accept_registrations' => 0],
        ));

        $this->assertFalse(TrainingEntityFormChangeSummarizer::hasChanges(
            ['capacity_unlimited' => true],
            ['capacity_unlimited' => 1],
        ));

        $this->assertTrue(TrainingEntityFormChangeSummarizer::hasChanges(
            ['capacity_unlimited' => false],
            ['capacity_unlimited' => true],
        ));
    }

    public function test_comparable_snapshot_normalizes_boolean_like_values(): void
    {
        $snapshot = TrainingEntityFormChangeSummarizer::comparableSnapshot([
            'capacity_unlimited' => 1,
            'notify_audience' => '0',
            'title' => 'برنامج',
        ]);

        $this->assertTrue($snapshot['capacity_unlimited']);
        $this->assertFalse($snapshot['notify_audience']);
        $this->assertSame('برنامج', $snapshot['title']);
    }

    public function test_change_summarizer_detects_ui_toggle_changes(): void
    {
        $changes = TrainingEntityFormChangeSummarizer::describeChanges(
            ['capacity_unlimited' => false, 'notify_audience' => true],
            ['capacity_unlimited' => true, 'notify_audience' => false],
            [
                'capacity_unlimited' => 'تسجيل غير محدود',
                'notify_audience' => 'إشعارات المستفيدين',
            ],
        );

        $this->assertCount(2, $changes);
        $this->assertStringContainsString('تسجيل غير محدود', $changes[0]);
        $this->assertStringContainsString('إشعارات المستفيدين', $changes[1]);
    }

    public function test_change_summarizer_detects_title_update(): void
    {
        $changes = TrainingEntityFormChangeSummarizer::describeChanges(
            ['title' => 'قديم'],
            ['title' => 'جديد'],
            ['title' => 'اسم البرنامج'],
        );

        $this->assertCount(1, $changes);
        $this->assertStringContainsString('اسم البرنامج', $changes[0]);
        $this->assertStringContainsString('قديم', $changes[0]);
        $this->assertStringContainsString('جديد', $changes[0]);
    }

    public function test_change_summarizer_ignores_identical_dates_in_different_formats(): void
    {
        $changes = TrainingEntityFormChangeSummarizer::describeChanges(
            ['start_date' => '2026-06-01'],
            ['start_date' => '2026-06-01 00:00:00'],
            ['start_date' => 'تاريخ البدء'],
        );

        $this->assertSame([], $changes);
    }

    public function test_change_summarizer_to_html_renders_change_list(): void
    {
        $html = TrainingEntityFormChangeSummarizer::toHtml(
            ['title' => 'قديم'],
            ['title' => 'جديد'],
            ['title' => 'اسم البرنامج'],
        );

        $htmlString = (string) $html;

        $this->assertStringContainsString('kafaat-settings-changes', $htmlString);
        $this->assertStringContainsString('kafaat-settings-changes__card', $htmlString);
        $this->assertStringContainsString('kafaat-settings-changes__field', $htmlString);
        $this->assertStringContainsString('kafaat-settings-changes__diff', $htmlString);
        $this->assertStringContainsString('اسم البرنامج', $htmlString);
        $this->assertStringContainsString('قديم', $htmlString);
        $this->assertStringContainsString('جديد', $htmlString);
        $this->assertStringContainsString('kafaat-settings-changes__chip--old', $htmlString);
        $this->assertStringContainsString('kafaat-settings-changes__chip--new', $htmlString);
    }

    public function test_change_summarizer_to_html_shows_empty_message_when_no_changes(): void
    {
        $html = TrainingEntityFormChangeSummarizer::toHtml(
            ['title' => 'نفس'],
            ['title' => 'نفس'],
        );

        $this->assertStringContainsString('kafaat-settings-changes--empty', (string) $html);
        $this->assertStringContainsString('لا توجد تعديلات', (string) $html);
    }

    public function test_structured_changes_returns_label_old_and_new(): void
    {
        $changes = TrainingEntityFormChangeSummarizer::structuredChanges(
            ['capacity_unlimited' => false, 'capacity' => 80],
            ['capacity_unlimited' => false, 'capacity' => 81],
            ['capacity' => 'الحد الأقصى للمسجّلين'],
        );

        $this->assertCount(1, $changes);
        $this->assertSame('الحد الأقصى للمسجّلين', $changes[0]['label']);
        $this->assertSame('80', $changes[0]['old']);
        $this->assertSame('81', $changes[0]['new']);
    }

    public function test_publish_controls_hidden_when_record_is_published(): void
    {
        $program = new TrainingProgram;
        $program->exists = true;
        $program->status = ProgramStatus::Published;

        $this->assertFalse(TrainingEntityFormSupport::publishControlsVisibleForRecord(
            $program,
            ProgramStatus::Published,
        ));
    }

    public function test_publish_controls_visible_for_draft_record(): void
    {
        $program = new TrainingProgram;
        $program->exists = true;
        $program->status = ProgramStatus::Draft;

        $this->assertTrue(TrainingEntityFormSupport::publishControlsVisibleForRecord(
            $program,
            ProgramStatus::Published,
        ));
    }

    public function test_publish_controls_visible_when_no_record(): void
    {
        $this->assertTrue(TrainingEntityFormSupport::publishControlsVisibleForRecord(
            null,
            ProgramStatus::Published,
        ));
    }
}
