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

        $this->assertStringContainsString('وصف البرنامج الأساسي.', $text);
        $this->assertStringContainsString('محاور البرنامج:', $text);
        $this->assertStringContainsString('مهارات التواصل', $text);
        $this->assertStringContainsString('أ. أحمد، د. سارة', $text);
        $this->assertStringContainsString('العمل الجماعي', $text);
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

    public function test_registration_message_includes_whatsapp_link_when_enabled(): void
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
        $this->assertStringContainsString('https://chat.whatsapp.com/test-group', $message);
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
