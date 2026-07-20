<?php

namespace Tests\Unit\Support;

use App\Enums\CompetencyTrack;
use App\Enums\ProfileGender;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Models\Profile;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Support\RichContentSupport;
use App\Support\TrainingProgramExtrasSupport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingProgramExtrasSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_formats_session_topics_into_public_description(): void
    {
        $text = TrainingProgramExtrasSupport::formatPublicDescription(
            'وصف البرنامج الأساسي.',
            true,
            [
                ['title' => 'مهارات التواصل', 'facilitators' => 'أ. أحمد، د. سارة'],
                ['title' => 'العمل الجماعي', 'facilitators' => 'م. خالد'],
            ],
        );

        $this->assertTrue(TrainingProgramExtrasSupport::looksLikeHtml($text));
        $this->assertStringContainsString('وصف البرنامج الأساسي.', $text);
        $this->assertStringContainsString('محاور البرنامج', $text);
        $this->assertStringContainsString('program-session-topics', $text);
        $this->assertStringContainsString('مهارات التواصل', $text);
        $this->assertStringContainsString('أ. أحمد، د. سارة', $text);
        $this->assertStringContainsString('العمل الجماعي', $text);
        $this->assertStringContainsString('<ol', $text);
    }

    public function test_appends_session_topics_as_html_when_description_is_rich(): void
    {
        $text = TrainingProgramExtrasSupport::formatPublicDescription(
            '<p><strong>نبذة</strong> منسّقة</p>',
            true,
            [
                ['title' => 'المحور الأول', 'facilitators' => 'أ. سارة'],
            ],
        );

        $this->assertTrue(TrainingProgramExtrasSupport::looksLikeHtml($text));
        $this->assertStringContainsString('<p><strong>نبذة</strong> منسّقة</p>', $text);
        $this->assertStringContainsString('program-session-topics', $text);
        $this->assertStringContainsString('المحور الأول', $text);
        $this->assertStringContainsString('أ. سارة', $text);
        $this->assertStringContainsString('#335483', $text);
    }

    public function test_formats_session_topics_plain_text_block(): void
    {
        $text = TrainingProgramExtrasSupport::formatSessionTopicsBlock([
            ['title' => 'المحور الأول', 'facilitators' => 'أ. سارة'],
        ]);

        $this->assertStringContainsString('محاور البرنامج:', $text);
        $this->assertStringContainsString('1. المحور الأول', $text);
        $this->assertStringContainsString('أ. سارة', $text);
    }

    public function test_public_session_topics_respects_enabled_flag(): void
    {
        $enabled = $this->makeProgram([
            'session_topics_enabled' => true,
            'session_topics' => [
                ['title' => 'محور نشط', 'facilitators' => 'أ. علي'],
            ],
        ]);

        $disabled = $this->makeProgram([
            'slug' => 'test-program-disabled-'.uniqid(),
            'session_topics_enabled' => false,
            'session_topics' => [
                ['title' => 'محور مخفي', 'facilitators' => 'أ. علي'],
            ],
        ]);

        $this->assertCount(1, TrainingProgramExtrasSupport::publicSessionTopics($enabled));
        $this->assertSame([], TrainingProgramExtrasSupport::publicSessionTopics($disabled));
    }

    public function test_normalizes_and_exposes_program_presenters(): void
    {
        $program = $this->makeProgram([
            'program_presenters' => [
                ['name' => 'أحمد الرفاعي', 'role' => ''],
                ['name' => '  ', 'role' => 'ignored'],
                ['name' => 'د. محمد النصار', 'role' => 'مدرب'],
            ],
        ]);

        $this->assertSame(
            [
                ['name' => 'أحمد الرفاعي', 'role' => ''],
                ['name' => 'د. محمد النصار', 'role' => 'مدرب'],
            ],
            TrainingProgramExtrasSupport::publicProgramPresenters($program),
        );

        $this->assertSame('أر', TrainingProgramExtrasSupport::presenterInitials('أحمد الرفاعي'));
        $this->assertSame('من', TrainingProgramExtrasSupport::presenterInitials('د. محمد النصار'));
    }

    public function test_apply_form_data_clears_empty_program_presenters(): void
    {
        $data = TrainingProgramExtrasSupport::applyFormData([
            'session_topics_enabled' => false,
            'program_presenters' => [
                ['name' => ''],
            ],
            'whatsapp_groups_enabled' => false,
        ]);

        $this->assertNull($data['program_presenters']);
    }

    public function test_apply_form_data_normalizes_tiptap_description_array_to_json_string(): void
    {
        $document = [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => 'نبذة TipTap',
                ]],
            ]],
        ];

        $data = TrainingProgramExtrasSupport::applyFormData([
            'description' => $document,
            'session_topics_enabled' => false,
            'whatsapp_groups_enabled' => false,
        ]);

        $this->assertIsString($data['description']);
        $this->assertTrue(RichContentSupport::isTipTapJson($data['description']));
        $this->assertStringContainsString('نبذة TipTap', $data['description']);
    }

    public function test_apply_form_data_clears_empty_description(): void
    {
        $data = TrainingProgramExtrasSupport::applyFormData([
            'description' => '',
            'session_topics_enabled' => false,
            'whatsapp_groups_enabled' => false,
        ]);

        $this->assertNull($data['description']);
    }

    public function test_description_preview_accepts_tiptap_array_without_array_to_string_error(): void
    {
        $document = [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => 'معاينة TipTap',
                    'marks' => [['type' => 'bold']],
                ]],
            ]],
        ];

        $html = TrainingProgramExtrasSupport::descriptionPreviewHtml($document, false, null)->toHtml();

        $this->assertStringContainsString('معاينة TipTap', $html);
        $this->assertStringContainsString('<strong>', $html);
        $this->assertStringNotContainsString('"type":"doc"', $html);
        $this->assertTrue(TrainingProgramExtrasSupport::shouldShowDescriptionPreview($document, false, null));
    }

    public function test_description_preview_accepts_plain_text_and_empty(): void
    {
        $plain = TrainingProgramExtrasSupport::descriptionPreviewHtml('نبذة نص عادي', false, null)->toHtml();
        $this->assertStringContainsString('نبذة نص عادي', $plain);
        $this->assertTrue(TrainingProgramExtrasSupport::shouldShowDescriptionPreview('نبذة نص عادي', false, null));

        $this->assertFalse(TrainingProgramExtrasSupport::shouldShowDescriptionPreview(null, false, null));
        $this->assertFalse(TrainingProgramExtrasSupport::shouldShowDescriptionPreview('', false, null));
        $this->assertFalse(TrainingProgramExtrasSupport::shouldShowDescriptionPreview([], false, null));
        $this->assertFalse(TrainingProgramExtrasSupport::shouldShowDescriptionPreview([
            'type' => 'doc',
            'content' => [],
        ], false, null));

        $empty = TrainingProgramExtrasSupport::descriptionPreviewHtml(null, false, null)->toHtml();
        $this->assertStringContainsString('—', $empty);
    }

    public function test_description_preview_visibility_with_session_topics_only(): void
    {
        $this->assertTrue(TrainingProgramExtrasSupport::shouldShowDescriptionPreview(
            null,
            true,
            [['title' => 'محور فقط', 'facilitators' => '']],
        ));

        $html = TrainingProgramExtrasSupport::descriptionPreviewHtml(
            null,
            true,
            [['title' => 'محور فقط', 'facilitators' => '']],
        )->toHtml();

        $this->assertStringContainsString('محور فقط', $html);
        $this->assertStringContainsString('محاور البرنامج', $html);
    }

    public function test_resolves_whatsapp_group_by_gender_with_fallback(): void
    {
        $program = $this->makeProgram([
            'whatsapp_groups_enabled' => true,
            'whatsapp_group_male' => 'https://chat.whatsapp.com/male',
            'whatsapp_group_female' => 'https://chat.whatsapp.com/female',
        ]);

        $male = User::factory()->create();
        Profile::query()->create([
            'user_id' => $male->id,
            'gender' => ProfileGender::Male,
        ]);

        $female = User::factory()->create();
        Profile::query()->create([
            'user_id' => $female->id,
            'gender' => ProfileGender::Female,
        ]);

        $this->assertSame(
            'https://chat.whatsapp.com/male',
            TrainingProgramExtrasSupport::whatsappGroupUrlFor($program, $male->fresh('profile')),
        );
        $this->assertSame(
            'https://chat.whatsapp.com/female',
            TrainingProgramExtrasSupport::whatsappGroupUrlFor($program, $female->fresh('profile')),
        );
    }

    public function test_registration_message_is_clean_without_raw_whatsapp_url(): void
    {
        $program = $this->makeProgram([
            'title' => 'برنامج تجريبي',
            'whatsapp_groups_enabled' => true,
            'whatsapp_group_male' => 'https://chat.whatsapp.com/test-group',
        ]);

        $user = User::factory()->create();
        Profile::query()->create([
            'user_id' => $user->id,
            'gender' => ProfileGender::Male,
        ]);

        $message = TrainingProgramExtrasSupport::registrationApprovalMessage($program, $user->fresh('profile'));

        $this->assertStringContainsString('تم قبول طلبك', $message);
        $this->assertStringNotContainsString('https://chat.whatsapp.com/test-group', $message);
        $this->assertSame(
            'https://chat.whatsapp.com/test-group',
            TrainingProgramExtrasSupport::whatsappGroupUrlFor($program, $user->fresh('profile')),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProgram(array $overrides = []): TrainingProgram
    {
        return TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج اختبار',
            'slug' => 'test-program-'.uniqid(),
            'description' => 'وصف',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Self,
            'delivery_mode' => ProgramDeliveryMode::Remote,
            'status' => ProgramStatus::Draft,
        ], $overrides));
    }
}
