<?php

namespace Tests\Feature;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Models\TrainingProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProgramsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_programs_index_lists_all_published_standalone_programs_without_duplicates(): void
    {
        $self = TrainingProgram::query()->create([
            'title' => 'برنامج ذاتي',
            'slug' => 'prog-self',
            'description' => 'وصف',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Self,
            'delivery_mode' => ProgramDeliveryMode::Remote,
            'status' => ProgramStatus::Published,
            'published_at' => now()->subDay(),
            'capacity' => 20,
            'auto_accept_registrations' => true,
        ]);

        $professional = TrainingProgram::query()->create([
            'title' => 'برنامج مهني',
            'slug' => 'prog-pro',
            'description' => 'وصف',
            'program_kind' => TrainingProgramKind::Workshop,
            'competency_track' => CompetencyTrack::Professional,
            'delivery_mode' => ProgramDeliveryMode::Remote,
            'status' => ProgramStatus::Published,
            'published_at' => now()->subHours(2),
            'capacity' => 20,
            'auto_accept_registrations' => true,
        ]);

        TrainingProgram::query()->create([
            'title' => 'مسودة',
            'slug' => 'prog-draft',
            'description' => 'وصف',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Self,
            'delivery_mode' => ProgramDeliveryMode::Remote,
            'status' => ProgramStatus::Draft,
            'capacity' => 20,
            'auto_accept_registrations' => true,
        ]);

        $html = $this->get(route('public.programs.index'))
            ->assertOk()
            ->assertSee('جميع البرامج', false)
            ->assertSee('برنامج ذاتي', false)
            ->assertSee('برنامج مهني', false)
            ->assertDontSee('مسودة', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'برنامج ذاتي'));
        $this->assertSame(1, substr_count($html, 'برنامج مهني'));
        $this->assertSame(1, substr_count($html, 'href="'.route('public.programs.show', $self->slug).'"'));
        $this->assertSame(1, substr_count($html, 'href="'.route('public.programs.show', $professional->slug).'"'));
    }

    public function test_navbar_programs_link_goes_to_all_programs_index(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.route('public.programs.index').'"', false)
            ->assertSee('نتائج نعتز بها', false)
            ->assertSee('By Kafaat Team', false)
            ->assertSee('٦:٠٠ص - ١٢:٠٠م', false)
            ->assertSee('١٢:٠٠م - ٦:٠٠ص', false);
    }
}
