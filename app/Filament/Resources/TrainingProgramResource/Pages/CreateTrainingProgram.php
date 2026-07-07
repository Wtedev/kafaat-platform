<?php

namespace App\Filament\Resources\TrainingProgramResource\Pages;

use App\Enums\ProgramStatus;
use App\Filament\Resources\Concerns\PreparesTrainingEntityFormData;
use App\Filament\Resources\Pages\BaseCreateRecord;
use App\Filament\Resources\TrainingProgramResource;
use App\Filament\Support\TrainingEntityFormSupport;
use App\Models\LearningPath;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;

class CreateTrainingProgram extends BaseCreateRecord
{
    use PreparesTrainingEntityFormData;

    protected static string $resource = TrainingProgramResource::class;

    public function mount(): void
    {
        parent::mount();

        $pathId = $this->resolvePathContextLearningPathId();
        if ($pathId === null) {
            return;
        }

        $this->form->fill([
            ...($this->form->getState() ?? []),
            'is_linked_to_path' => true,
            'learning_path_id' => $pathId,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return TrainingProgramResource::createForm($schema, $this->resolvePathContextLearningPathId());
    }

    public function getTitle(): string
    {
        $pathId = $this->resolvePathContextLearningPathId();
        if ($pathId === null) {
            return parent::getTitle();
        }

        $pathTitle = LearningPath::query()->whereKey($pathId)->value('title');
        if (filled($pathTitle)) {
            return 'إنشاء برنامج في مسار: '.$pathTitle;
        }

        return 'إنشاء برنامج في المسار';
    }

    protected function getRedirectUrl(): string
    {
        $pathId = $this->resolvePathContextLearningPathId()
            ?? (int) ($this->getRecord()->learning_path_id ?? 0);

        if ($pathId > 0) {
            return TrainingProgramResource::learningPathProgramsViewUrl($pathId);
        }

        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $pathId = $this->resolvePathContextLearningPathId();
        if ($pathId !== null) {
            $data['is_linked_to_path'] = true;
            $data['learning_path_id'] = $pathId;
        }

        $data = TrainingEntityFormSupport::mergeNonDehydratedFormFlags($data, $this->data ?? []);

        $linked = (bool) ($data['is_linked_to_path'] ?? false);

        $data = TrainingEntityFormSupport::applyProgramPathLinkSettings($data);

        TrainingEntityFormSupport::assertValidProgramScheduleOrFail($data, showRegistration: ! $linked);

        $wantPublished = TrainingEntityFormSupport::wantsPublishedStatus($data);
        $data['status'] = $wantPublished && $this->canPublishNewTrainingProgram()
            ? ProgramStatus::Published->value
            : ProgramStatus::Draft->value;

        $data = TrainingEntityFormSupport::applyPublicationSchedule($data);

        $data = TrainingEntityFormSupport::applyCapacityUnlimited($data);
        $data = TrainingEntityFormSupport::applyAudienceNotifications($data);
        $data = TrainingEntityFormSupport::applyDeliveryModeFields($data);
        $data = TrainingEntityFormSupport::stampOwnerFromCreator($data);

        $ownerId = isset($data['owner_id']) ? (int) $data['owner_id'] : null;
        $data['editors'] = TrainingEntityFormSupport::normalizeProgramEditorIds(
            is_array($data['editors'] ?? null) ? $data['editors'] : [],
            $ownerId,
        );

        return $this->dropEmptyTrainingSlug(
            $this->stampTrainingEntityAuditFields($data),
        );
    }

    protected function getCreatedNotification(): ?Notification
    {
        /** @var \App\Models\TrainingProgram $record */
        $record = $this->getRecord();

        if ($record->status === ProgramStatus::Published && $record->notify_on_publish) {
            return Notification::make()
                ->success()
                ->title('تم إنشاء البرنامج ونشره')
                ->body('تم إرسال التنبيه داخل المنصة وبريداً للمستفيدين الذين فعّلوا إشعارات البريد في حساباتهم.');
        }

        if ($record->status === ProgramStatus::Draft && $record->notify_on_publish) {
            return Notification::make()
                ->success()
                ->title('تم حفظ البرنامج كمسودة')
                ->body('التنبيه مفعّل — سيُرسل عند تفعيل «ظاهر للزوار في الموقع» من صفحة التعديل.');
        }

        return parent::getCreatedNotification();
    }

    protected function resolvePathContextLearningPathId(): ?int
    {
        $pathId = (int) request()->query('learning_path_id');

        if ($pathId <= 0) {
            return null;
        }

        return LearningPath::query()->whereKey($pathId)->exists() ? $pathId : null;
    }
}
