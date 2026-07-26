<?php

namespace Tests\Feature\Filament;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramDeliveryMode;
use App\Enums\TrainingProgramKind;
use App\Filament\Resources\NewsResource\Pages\CreateNews;
use App\Filament\Resources\TrainingProgramResource\Pages\CreateTrainingProgram;
use App\Filament\Resources\VolunteerOpportunityResource;
use App\Filament\Resources\VolunteerOpportunityResource\Pages\CreateVolunteerOpportunity;
use App\Filament\Support\TrainingEntityFormSupport;
use App\Models\News;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Services\Media\PublicMediaLifecycleService;
use App\Services\News\NewsImageSyncService;
use App\Support\NewsFormSupport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class PublicMediaFilamentUploadFixTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_news_and_cover_fields_disable_image_editor_in_php_config(): void
    {
        // HTML for news gallery FileUpload only appears after a repeater item is
        // mounted; field-level assertions cover Cropper disablement for News.
        $this->assertFalse(NewsFormSupport::newsImageUploadField()->hasImageEditor());
        $this->assertFalse(
            TrainingEntityFormSupport::coverImageUpload('programs/covers')->hasImageEditor(),
        );
        $this->assertFalse(
            VolunteerOpportunityResource::volunteerOpportunityImageUploadField()->hasImageEditor(),
        );
    }

    public function test_create_program_page_renders_cover_upload_without_image_editor(): void
    {
        $staff = $this->staffAdmin();

        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($staff)
            ->test(CreateTrainingProgram::class)
            ->assertSuccessful()
            ->assertSee('hasImageEditor: false', false, false);
    }

    public function test_create_news_after_create_syncs_uploaded_gallery_paths(): void
    {
        $staff = $this->staffAdmin();
        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($staff)
            ->test(CreateNews::class)
            ->fillForm([
                'title' => 'خبر رفع Filament',
                'content' => [
                    'type' => 'doc',
                    'content' => [[
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => 'محتوى']],
                    ]],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $news = News::query()->where('title', 'خبر رفع Filament')->first();
        $this->assertNotNull($news);

        $stored = app(PublicMediaLifecycleService::class)->storeUpload(
            UploadedFile::fake()->image('news-card.jpg', 320, 192),
            'news/images',
        );

        app(NewsImageSyncService::class)->sync($news, [
            ['path' => $stored, 'is_primary' => true],
        ], allowEmpty: true);

        $news->refresh();
        $this->assertSame($stored, $news->image);
        $this->assertStringStartsWith('news/images/', (string) $news->image);
        Storage::disk('public')->assertExists((string) $news->image);
        $this->assertSame(1, $news->images()->count());
    }

    public function test_create_program_persists_cover_relative_path(): void
    {
        $staff = $this->staffAdmin();
        $this->withSession(['otp_verified' => true]);

        $upload = UploadedFile::fake()->image('cover.jpg', 640, 360);

        Livewire::actingAs($staff)
            ->test(CreateTrainingProgram::class)
            ->fillForm([
                'title' => 'برنامج غلاف Filament',
                'program_kind' => TrainingProgramKind::Course->value,
                'competency_track' => CompetencyTrack::Self->value,
                'delivery_mode' => ProgramDeliveryMode::Remote->value,
                'description' => 'وصف',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-15',
                'registration_start' => '2026-07-15',
                'registration_end' => '2026-08-14',
                'publish_immediately' => false,
                'image' => [$upload],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $program = TrainingProgram::query()->where('title', 'برنامج غلاف Filament')->first();
        $this->assertNotNull($program);
        $this->assertNotNull($program->image);
        $this->assertStringStartsWith('programs/covers/', (string) $program->image);
        Storage::disk('public')->assertExists((string) $program->image);
    }

    public function test_create_volunteer_persists_cover_relative_path(): void
    {
        $coordinator = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $coordinator->assignRole('staff');
        $coordinator->givePermissionTo([
            'manage_volunteers',
            'volunteering.view',
            'volunteering.create',
            'volunteering.update',
        ]);

        $this->withSession(['otp_verified' => true]);

        $upload = UploadedFile::fake()->image('vol.jpg', 640, 360);

        Livewire::actingAs($coordinator)
            ->test(CreateVolunteerOpportunity::class)
            ->fillForm([
                'title' => 'فرصة غلاف Filament',
                'description' => 'وصف فرصة',
                'image' => [$upload],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $opportunity = VolunteerOpportunity::query()->where('title', 'فرصة غلاف Filament')->first();
        $this->assertNotNull($opportunity);
        $this->assertNotNull($opportunity->image);
        $this->assertStringStartsWith('volunteer-opportunities/images/', (string) $opportunity->image);
        Storage::disk('public')->assertExists((string) $opportunity->image);
    }

    private function staffAdmin(): User
    {
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        return $admin;
    }
}
