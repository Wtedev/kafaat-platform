<?php

namespace Tests\Feature\Media;

use App\Enums\CompetencyTrack;
use App\Enums\OpportunityStatus;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Models\News;
use App\Models\Profile;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Services\Media\PublicMediaLifecycleService;
use App\Services\News\NewsImageSyncService;
use App\Support\PublicDiskPath;
use Database\Seeders\VolunteerLeadersProgramCoverSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class PublicMediaManagementTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use GeneratesTestIdentityData;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        Storage::fake('public');
        Storage::fake('s3');
        Storage::fake('private_documents');
    }

    public function test_training_program_cover_update_stores_relative_path_and_replaces_owned_file(): void
    {
        Storage::disk('public')->put('programs/covers/old.jpg', 'old-bytes');

        $program = TrainingProgram::query()->create([
            'title' => 'برنامج وسائط',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Draft,
            'image' => 'programs/covers/old.jpg',
        ]);

        $newPath = app(PublicMediaLifecycleService::class)->storeUpload(
            UploadedFile::fake()->image('next.jpg', 320, 240),
            'programs/covers',
        );

        $program->allowCoverUpdate = true;
        $previous = $program->image;
        $program->forceFill(['image' => $newPath])->save();
        app(PublicMediaLifecycleService::class)->deleteOwnedIfReplaced($previous, $program->image);

        $program->refresh();
        $this->assertSame($newPath, $program->image);
        $this->assertStringStartsWith('programs/covers/', $program->image);
        $this->assertStringNotContainsString('http', (string) $program->image);
        Storage::disk('public')->assertMissing('programs/covers/old.jpg');
        Storage::disk('public')->assertExists($newPath);
        $this->assertStringContainsString('/storage/'.$newPath, $program->imagePublicUrl());
    }

    public function test_training_program_failed_update_keeps_old_and_discards_new(): void
    {
        Storage::disk('public')->put('programs/covers/keep.jpg', 'keep');
        Storage::disk('public')->put('programs/covers/failed-new.jpg', 'new');

        $program = TrainingProgram::query()->create([
            'title' => 'برنامج فشل',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Draft,
            'image' => 'programs/covers/keep.jpg',
        ]);

        app(PublicMediaLifecycleService::class)->discardFailedUpload('programs/covers/failed-new.jpg');

        $program->refresh();
        $this->assertSame('programs/covers/keep.jpg', $program->image);
        Storage::disk('public')->assertExists('programs/covers/keep.jpg');
        Storage::disk('public')->assertMissing('programs/covers/failed-new.jpg');
    }

    public function test_git_backed_program_cover_is_not_deleted_on_replace(): void
    {
        $bundled = VolunteerLeadersProgramCoverSeeder::COVER_RELATIVE_PATH;
        Storage::disk('public')->put('programs/covers/staff.jpg', 'staff');

        $program = TrainingProgram::query()->create([
            'title' => 'برنامج قديم',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Draft,
            'image' => $bundled,
        ]);

        $program->allowCoverUpdate = true;
        $previous = $program->image;
        $program->forceFill(['image' => 'programs/covers/staff.jpg'])->save();
        app(PublicMediaLifecycleService::class)->deleteOwnedIfReplaced($previous, $program->image);

        // Bundled path must not be treated as owned deletable file.
        $this->assertFalse(
            app(PublicMediaLifecycleService::class)->isOwnedPublicDiskPath($bundled),
        );
    }

    public function test_legacy_program_cover_still_resolves_url_without_500(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج بلا ملف',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Draft,
            'image' => 'programs/covers/missing-file.jpg',
        ]);

        $url = $program->imagePublicUrl();
        $this->assertNotSame('', $url);
        $this->assertStringContainsString('training-catalog-placeholder', $url);
    }

    public function test_volunteer_opportunity_cover_lifecycle_on_delete(): void
    {
        Storage::disk('public')->put('volunteer-opportunities/images/opp.jpg', 'bytes');

        $opportunity = VolunteerOpportunity::query()->create([
            'title' => 'فرصة وسائط',
            'description' => 'وصف',
            'status' => OpportunityStatus::Draft,
            'image' => 'volunteer-opportunities/images/opp.jpg',
        ]);

        $opportunity->delete();

        Storage::disk('public')->assertMissing('volunteer-opportunities/images/opp.jpg');
    }

    public function test_training_program_delete_removes_owned_cover_only(): void
    {
        Storage::disk('public')->put('programs/covers/del.jpg', 'bytes');

        $program = TrainingProgram::query()->create([
            'title' => 'برنامج حذف',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Draft,
            'image' => 'programs/covers/del.jpg',
        ]);

        $program->delete();

        Storage::disk('public')->assertMissing('programs/covers/del.jpg');
    }

    public function test_news_primary_image_uses_public_disk_relative_path(): void
    {
        Storage::disk('public')->put('news/images/article.jpg', 'img');

        $news = News::query()->create([
            'title' => 'خبر وسائط',
            'excerpt' => 'مقتطف',
            'content' => 'محتوى',
            'image' => 'news/images/article.jpg',
            'published_at' => now(),
        ]);

        $this->assertSame('news/images/article.jpg', $news->image);
        $this->assertStringContainsString('/storage/news/images/article.jpg', $news->imagePublicUrl());
    }

    public function test_portal_avatar_upload_replace_remove_and_validation(): void
    {
        $user = $this->makePortalUserWithProfile();
        $payload = $this->profilePayload();

        $response = $this->actingAsOtpVerified($user)->patch(route('portal.profile.update'), [
            ...$payload,
            'avatar' => UploadedFile::fake()->image('me.jpg', 200, 200),
        ]);
        $response->assertRedirect();

        $user->refresh()->load('profile');
        $first = $user->profile->avatar;
        $this->assertNotNull($first);
        $this->assertStringStartsWith('avatars/', $first);
        Storage::disk('public')->assertExists($first);

        $response = $this->actingAsOtpVerified($user)->patch(route('portal.profile.update'), [
            ...$payload,
            'avatar' => UploadedFile::fake()->image('me2.png', 200, 200),
        ]);
        $response->assertRedirect();

        $user->refresh()->load('profile');
        $second = $user->profile->avatar;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $response = $this->actingAsOtpVerified($user)->patch(route('portal.profile.update'), [
            ...$payload,
            'remove_avatar' => '1',
        ]);
        $response->assertRedirect();

        $user->refresh()->load('profile');
        $this->assertNull($user->profile->avatar);
        Storage::disk('public')->assertMissing($second);

        $this->actingAsOtpVerified($user)->patch(route('portal.profile.update'), [
            ...$payload,
            'avatar' => UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors('avatar');

        $this->actingAsOtpVerified($user)->patch(route('portal.profile.update'), [
            ...$payload,
            'avatar' => UploadedFile::fake()->create('fake.jpg', 20, 'application/pdf'),
        ])->assertSessionHasErrors('avatar');

        $this->actingAsOtpVerified($user)->patch(route('portal.profile.update'), [
            ...$payload,
            'avatar' => UploadedFile::fake()->create('huge.jpg', 6000, 'image/jpeg'),
        ])->assertSessionHasErrors('avatar');
    }

    public function test_user_cannot_update_another_users_avatar_via_portal_route(): void
    {
        $owner = $this->makePortalUserWithProfile(['email' => 'avatar-owner@example.com']);
        $other = $this->makePortalUserWithProfile(['email' => 'avatar-other@example.com']);
        Storage::disk('public')->put('avatars/owner.jpg', 'o');
        $owner->profile()->update(['avatar' => 'avatars/owner.jpg']);

        $payload = $this->profilePayload();
        $payload['first_name'] = 'مهاجم';

        $this->actingAsOtpVerified($other)->patch(route('portal.profile.update'), [
            ...$payload,
            'avatar' => UploadedFile::fake()->image('hijack.jpg', 100, 100),
        ])->assertRedirect();

        $owner->refresh()->load('profile');
        $this->assertSame('avatars/owner.jpg', $owner->profile->avatar);
        $this->assertSame('مهاجم', $other->fresh()->first_name);
    }

    public function test_beneficiary_cannot_update_news_program_or_volunteer_via_policy(): void
    {
        $beneficiary = $this->makePortalUserWithProfile(['email' => 'ben@media.test']);

        $news = News::query()->create([
            'title' => 'خبر محمي',
            'excerpt' => 'x',
            'content' => 'y',
            'published_at' => now(),
        ]);
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج محمي',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Draft,
        ]);
        $opportunity = VolunteerOpportunity::query()->create([
            'title' => 'فرصة محمية',
            'description' => 'وصف',
            'status' => OpportunityStatus::Draft,
        ]);

        $this->assertFalse($beneficiary->can('update', $news));
        $this->assertFalse($beneficiary->can('update', $program));
        $this->assertFalse($beneficiary->can('update', $opportunity));
    }

    public function test_cv_remains_on_s3_while_public_media_uses_public_disk(): void
    {
        $cvPath = 'cv/1/cv.pdf';
        Storage::disk('s3')->put($cvPath, '%PDF-1.4 test');

        $publicPath = app(PublicMediaLifecycleService::class)->storeUpload(
            UploadedFile::fake()->image('cover.jpg', 120, 120),
            'news/images',
        );

        Storage::disk('public')->assertExists($publicPath);
        Storage::disk('s3')->assertExists($cvPath);
        Storage::disk('public')->assertMissing($cvPath);
        Storage::disk('s3')->assertMissing($publicPath);
        $this->assertSame(PublicMediaLifecycleService::DISK, 'public');
    }

    public function test_avatar_missing_file_falls_back_without_throwing(): void
    {
        $profile = new Profile(['avatar' => 'avatars/gone.jpg']);

        $this->assertNull($profile->avatarUrl());
        $this->assertSame(
            PublicDiskPath::urlOrPlaceholder(null),
            PublicDiskPath::urlOrPlaceholder('avatars/gone.jpg'),
        );
    }

    public function test_rejects_image_over_4000px_and_accepts_within_limit(): void
    {
        $user = $this->makePortalUserWithProfile(['email' => 'dims@media.test']);
        $payload = $this->profilePayload();

        $this->actingAsOtpVerified($user)->patch(route('portal.profile.update'), [
            ...$payload,
            'avatar' => UploadedFile::fake()->image('too-wide.jpg', 4001, 200),
        ])->assertSessionHasErrors('avatar');

        $this->actingAsOtpVerified($user)->patch(route('portal.profile.update'), [
            ...$payload,
            'avatar' => UploadedFile::fake()->image('too-tall.jpg', 200, 4001),
        ])->assertSessionHasErrors('avatar');

        $this->actingAsOtpVerified($user)->patch(route('portal.profile.update'), [
            ...$payload,
            'avatar' => UploadedFile::fake()->image('ok.jpg', 1200, 800),
        ])->assertSessionDoesntHaveErrors('avatar')->assertRedirect();

        $user->refresh()->load('profile');
        $this->assertNotNull($user->profile->avatar);
        Storage::disk('public')->assertExists($user->profile->avatar);
    }

    public function test_news_delete_removes_owned_primary_and_gallery_but_not_git_images(): void
    {
        Storage::disk('public')->put('news/images/primary.jpg', 'primary');
        Storage::disk('public')->put('news/images/gallery.jpg', 'gallery');
        Storage::disk('public')->put('images/news/bundled-cover.jpg', 'git-asset');

        $news = News::query()->create([
            'title' => 'خبر للحذف',
            'excerpt' => 'مقتطف',
            'content' => 'محتوى',
            'image' => 'news/images/primary.jpg',
            'published_at' => now(),
        ]);
        $news->images()->create([
            'path' => 'news/images/primary.jpg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        $news->images()->create([
            'path' => 'news/images/gallery.jpg',
            'is_primary' => false,
            'sort_order' => 1,
        ]);

        $news->delete();

        Storage::disk('public')->assertMissing('news/images/primary.jpg');
        Storage::disk('public')->assertMissing('news/images/gallery.jpg');
        Storage::disk('public')->assertExists('images/news/bundled-cover.jpg');
        $this->assertFalse(
            app(PublicMediaLifecycleService::class)->isOwnedPublicDiskPath('images/news/bundled-cover.jpg'),
        );
    }

    public function test_news_delete_keeps_file_still_referenced_by_another_news(): void
    {
        Storage::disk('public')->put('news/images/shared.jpg', 'shared');

        $first = News::query()->create([
            'title' => 'خبر أول مشترك',
            'excerpt' => 'a',
            'content' => 'a',
            'image' => 'news/images/shared.jpg',
            'published_at' => now(),
        ]);
        $first->images()->create([
            'path' => 'news/images/shared.jpg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $second = News::query()->create([
            'title' => 'خبر ثانٍ مشترك',
            'excerpt' => 'b',
            'content' => 'b',
            'image' => 'news/images/shared.jpg',
            'published_at' => now(),
        ]);
        $second->images()->create([
            'path' => 'news/images/shared.jpg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $first->delete();
        Storage::disk('public')->assertExists('news/images/shared.jpg');

        $second->delete();
        Storage::disk('public')->assertMissing('news/images/shared.jpg');
    }

    public function test_news_create_replace_remove_and_missing_fallback_without_directory_delete(): void
    {
        $lifecycleSource = file_get_contents(app_path('Services/Media/PublicMediaLifecycleService.php'));
        $newsSyncSource = file_get_contents(app_path('Services/News/NewsImageSyncService.php'));
        $this->assertIsString($lifecycleSource);
        $this->assertIsString($newsSyncSource);
        $this->assertStringNotContainsString('deleteDirectory', $lifecycleSource);
        $this->assertStringNotContainsString('deleteDirectory', $newsSyncSource);

        Storage::disk('public')->put('news/images/v1.jpg', 'v1');
        Storage::disk('public')->put('news/images/v2.jpg', 'v2');

        $news = News::query()->create([
            'title' => 'خبر دورة الصور',
            'excerpt' => 'مقتطف',
            'content' => 'محتوى',
            'published_at' => now(),
        ]);

        $sync = app(NewsImageSyncService::class);
        $sync->sync($news, [
            ['path' => 'news/images/v1.jpg', 'is_primary' => true],
        ], allowEmpty: true);

        $news->refresh();
        $this->assertSame('news/images/v1.jpg', $news->image);
        Storage::disk('public')->assertExists('news/images/v1.jpg');

        $sync->sync($news, [
            ['path' => 'news/images/v2.jpg', 'is_primary' => true],
        ], allowEmpty: true);

        $news->refresh();
        $this->assertSame('news/images/v2.jpg', $news->image);
        Storage::disk('public')->assertMissing('news/images/v1.jpg');
        Storage::disk('public')->assertExists('news/images/v2.jpg');

        $sync->sync($news, [], allowEmpty: true);
        $news->refresh();
        $this->assertNull($news->image);
        Storage::disk('public')->assertMissing('news/images/v2.jpg');
        $this->assertSame(0, $news->images()->count());

        $news->forceFill(['image' => 'news/images/missing-now.jpg'])->saveQuietly();
        $url = $news->fresh()->imagePublicUrl();
        $this->assertNotSame('', $url);
        $this->assertStringContainsString('news-placeholder', $url);
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(): array
    {
        $payload = $this->validRegistrationPayload();
        unset($payload['email'], $payload['password'], $payload['password_confirmation'], $payload['identity_type'], $payload['identity_number']);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $userAttributes
     */
    private function makePortalUserWithProfile(array $userAttributes = []): User
    {
        $payload = $this->validRegistrationPayload();
        unset($payload['email'], $payload['password'], $payload['password_confirmation']);

        $user = User::factory()->create(array_merge([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
            'first_name' => $payload['first_name'],
            'father_name' => $payload['father_name'],
            'grandfather_name' => $payload['grandfather_name'],
            'family_name' => $payload['family_name'],
            'name' => 'أحمد محمد عبدالله السعود',
            'phone' => '+966501234567',
        ], $userAttributes));
        $user->assignRole('beneficiary');

        $user->profile()->create([
            'birth_date' => $payload['birth_date'],
            'gender' => $payload['gender'],
            'city' => 'الرياض',
        ]);

        return $user->fresh(['profile']);
    }
}
