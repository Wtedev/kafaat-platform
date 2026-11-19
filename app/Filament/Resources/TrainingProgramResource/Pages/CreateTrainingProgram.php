<?php

namespace App\Filament\Resources\TrainingProgramResource\Pages;

use App\Enums\ProgramStatus;
use App\Filament\Resources\Concerns\PreparesTrainingEntityFormData;
use App\Filament\Resources\Pages\BaseCreateRecord;
use App\Filament\Resources\TrainingProgramResource;
use App\Filament\Support\TrainingEntityFormSupport;
use App\Models\TrainingProgram;
use App\Support\TrainingProgramExtrasSupport;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;

class CreateTrainingProgram extends BaseCreateRecord
{
    use PreparesTrainingEntityFormData;

    protected static string $resource = TrainingProgramResource::class;

    public function form(Schema $schema): Schema
    {
        return TrainingProgramResource::createForm($schema);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = TrainingEntityFormSupport::mergeNonDehydratedFormFlags($data, $this->data ?? []);

        TrainingEntityFormSupport::assertValidProgramScheduleOrFail($data);

        $wantPublished = TrainingEntityFormSupport::wantsPublishedStatus($data);
        $data['status'] = $wantPublished && $this->canPublishNewTrainingProgram()
            ? ProgramStatus::Published->value
            : ProgramStatus::Draft->value;

        $data = TrainingEntityFormSupport::applyPublicationSchedule($data);

        $data = TrainingEntityFormSupport::applyCapacityUnlimited($data);
        $data = TrainingEntityFormSupport::applyAudienceNotifications($data);
        $data = TrainingEntityFormSupport::applyDeliveryModeFields($data);
        $data = TrainingEntityFormSupport::applyAcceptanceConditions($data);
        $data = TrainingProgramExtrasSupport::applyFormData($data);
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

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): TrainingProgram
    {
        $program = new TrainingProgram;
        $program->allowCoverUpdate = true;
        $program->fill($data);
        $program->save();

        return $program;
    }

    protected function getCreatedNotification(): ?Notification
    {
        /** @var TrainingProgram $record */
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
}
