<?php

namespace App\Filament\Resources\TrainingProgramResource\Pages;

use App\Enums\ProgramStatus;
use App\Filament\Resources\Concerns\PreparesTrainingEntityFormData;
use App\Filament\Resources\Pages\BaseCreateRecord;
use App\Filament\Resources\TrainingProgramResource;
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $linked = (bool) ($data['is_linked_to_path'] ?? false);
        $unlimited = (bool) ($data['capacity_unlimited'] ?? false);

        unset($data['is_linked_to_path'], $data['capacity_unlimited'], $data['editors']);

        if ($unlimited) {
            $data['capacity'] = null;
        }

        if (! $linked) {
            $data['learning_path_id'] = null;
            $data['path_sort_order'] = null;
        }

        $visible = (bool) ($data['visible_on_site'] ?? false);
        $data['status'] = $visible
            ? ProgramStatus::Published->value
            : ProgramStatus::Draft->value;

        unset($data['visible_on_site']);

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
}
