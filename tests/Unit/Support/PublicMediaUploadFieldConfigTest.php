<?php

namespace Tests\Unit\Support;

use App\Filament\Resources\VolunteerOpportunityResource;
use App\Filament\Support\TrainingEntityFormSupport;
use App\Support\NewsFormSupport;
use Filament\Forms\Components\FileUpload;
use ReflectionObject;
use Tests\TestCase;

class PublicMediaUploadFieldConfigTest extends TestCase
{
    public function test_news_image_upload_disables_editor_crop_and_early_persist(): void
    {
        $field = NewsFormSupport::newsImageUploadField();

        $this->assertInstanceOf(FileUpload::class, $field);
        $this->assertFalse($field->hasImageEditor());
        $this->assertNull($field->getAutomaticallyCropImagesAspectRatio());
        $this->assertTrue($field->shouldStoreFiles());
        $this->assertSame([], $this->afterStateUpdatedCallbacks($field));
        $this->assertSame(5120, $field->getMaxSize());
    }

    public function test_program_and_volunteer_cover_uploads_disable_image_editor(): void
    {
        $program = TrainingEntityFormSupport::coverImageUpload('programs/covers');
        $volunteer = VolunteerOpportunityResource::volunteerOpportunityImageUploadField();
        $learningPath = TrainingEntityFormSupport::coverImageUpload('learning-paths/images');

        foreach ([$program, $volunteer, $learningPath] as $field) {
            $this->assertInstanceOf(FileUpload::class, $field);
            $this->assertFalse($field->hasImageEditor());
            $this->assertNull($field->getAutomaticallyCropImagesAspectRatio());
            $this->assertSame([], $this->afterStateUpdatedCallbacks($field));
        }
    }

    public function test_news_form_support_source_does_not_call_save_uploaded_files_early(): void
    {
        $source = file_get_contents(app_path('Support/NewsFormSupport.php'));

        $this->assertIsString($source);
        $this->assertStringNotContainsString('->imageEditor(', $source);
        $this->assertStringNotContainsString('automaticallyCropImagesToAspectRatio', $source);
        $this->assertStringNotContainsString('afterStateUpdated(', $source);
        $this->assertStringNotContainsString('$component->saveUploadedFiles()', $source);
        $this->assertStringContainsString('5 ميجابايت', $source);
        $this->assertStringNotContainsString('قص بنسبة', $source);
    }

    public function test_training_entity_cover_source_does_not_enable_image_editor(): void
    {
        $source = file_get_contents(app_path('Filament/Support/TrainingEntityFormSupport.php'));

        $this->assertIsString($source);
        $this->assertStringNotContainsString('->imageEditor(', $source);
        $this->assertStringNotContainsString('automaticallyCropImagesToAspectRatio', $source);
    }

    /**
     * @return array<int, mixed>
     */
    private function afterStateUpdatedCallbacks(FileUpload $field): array
    {
        $reflection = new ReflectionObject($field);
        $property = $reflection->getProperty('afterStateUpdated');
        $property->setAccessible(true);

        /** @var array<int, mixed> $callbacks */
        $callbacks = $property->getValue($field);

        return $callbacks;
    }
}
