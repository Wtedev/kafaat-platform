<?php

namespace Tests\Feature\Seeders;

use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Models\TrainingProgram;
use Database\Seeders\DataForumProgramSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataForumProgramSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_forum_kind_is_a_selectable_option(): void
    {
        $this->assertSame('ملتقى', TrainingProgramKind::Forum->label());
        $this->assertSame('ملتقى', TrainingProgramKind::options()['forum']);
    }

    public function test_sets_forum_kind_and_removes_presenter_from_the_description(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'ملتقى تحليل البيانات 2',
            'slug' => DataForumProgramSeeder::SLUG,
            'program_kind' => TrainingProgramKind::Event,
            'auto_accept_registrations' => true,
            'status' => ProgramStatus::Published,
            'description' => 'ملتقى تدريبي.<br><br>يقدّمه د. عبد الله العمير، عالم بيانات وعضو هيئة التدريس بجامعة حفر الباطن.<br><br>الأيام النظرية من الساعة 4 إلى 7 مساءً، وتُحدد بقية المواعيد في حينها.',
        ]);

        $this->seed(DataForumProgramSeeder::class);

        $program->refresh();

        $this->assertSame(TrainingProgramKind::Forum, $program->program_kind);
        $this->assertSame(DataForumProgramSeeder::DESCRIPTION, $program->description);
        $this->assertStringNotContainsString('عبد الله العمير', (string) $program->description);
        $this->assertStringContainsString('هندسة الأوامر.', (string) $program->description);
        $this->assertStringContainsString('تحليل البيانات باستخدام لغة Python.', (string) $program->description);
        $this->assertTrue($program->session_topics_enabled);
        $this->assertFalse($program->auto_accept_registrations);
        $this->assertSame(18, $program->acceptance_conditions['min_age']);
        $this->assertSame(38, $program->acceptance_conditions['max_age']);
        $this->assertTrue($program->acceptance_conditions['require_saudi_national']);
        $this->assertSame(DataForumProgramSeeder::STAGES, $program->session_topics);
        $this->assertCount(6, $program->session_topics);
    }
}
