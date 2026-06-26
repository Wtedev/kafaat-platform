<?php

namespace App\Filament\Resources\TrainingProgramResource\Pages;

use App\Enums\ProgramStatus;
use App\Filament\Resources\Concerns\PreparesTrainingEntityFormData;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Resources\TrainingProgramResource;
use App\Filament\Support\TrainingEntityFormSupport;
use App\Models\TrainingProgram;
use App\Policies\TrainingProgramPolicy;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Access: {@see TrainingProgramPolicy::update()} via
 * {@see TrainingProgramResource::canEdit()} (Filament {@see \Filament\Resources\Resource::authorizeEdit()}).
 */
class EditTrainingProgram extends BaseEditRecord
{
    use PreparesTrainingEntityFormData;

    protected static string $resource = TrainingProgramResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        /** @var TrainingProgram $record */
        $record = parent::resolveRecord($key);

        return $record->loadMissing(['learningPath', 'owner', 'assignee']);
    }

    public function form(Schema $schema): Schema
    {
        return TrainingProgramResource::editForm($schema);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var TrainingProgram $record */
        $record = $this->getRecord();
        $data['publish_immediately'] = TrainingEntityFormSupport::resolvePublishImmediatelyFromRecord(
            $record->status,
            $record->published_at,
            ProgramStatus::Published,
        );
        $data['published_at'] = $record->published_at?->timezone(config('app.timezone'))->format('Y-m-d');
        $data['is_linked_to_path'] = $record->learning_path_id !== null;
        $data['capacity_unlimited'] = $record->capacity === null;
        $data['notify_audience'] = $record->notify_on_publish || $record->notify_milestones;
        $data['editors'] = $record->editors()->pluck('users.id')->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var TrainingProgram $program */
        $program = $this->getRecord();
        $wantPublished = TrainingEntityFormSupport::wantsPublishedStatus($data);

        $linked = (bool) ($data['is_linked_to_path'] ?? false);

        unset($data['is_linked_to_path'], $data['capacity_unlimited']);

        if (! $linked) {
            $data['learning_path_id'] = null;
            $data['path_sort_order'] = null;
        } else {
            $data['capacity'] = null;
            $data['registration_start'] = null;
            $data['registration_end'] = null;
            $data['weekdays'] = null;
        }

        TrainingEntityFormSupport::assertValidProgramScheduleOrFail($data, showRegistration: ! $linked);

        $data['status'] = $this->resolveProgramPublicationStatus($program, $wantPublished)->value;

        $preservePublishTime = $program->status === ProgramStatus::Published && TrainingEntityFormSupport::wantsImmediatePublication($data);
        $data = TrainingEntityFormSupport::applyPublicationSchedule($data, $preservePublishTime);

        $data = TrainingEntityFormSupport::applyCapacityUnlimited($data);
        $data = TrainingEntityFormSupport::applyAudienceNotifications($data);

        return $this->dropEmptyTrainingSlug(
            $this->stampTrainingEntityAuditFields($data),
        );
    }

    /**
     * @return array<class-string<RelationManager> | RelationGroup | RelationManagerConfiguration>
     */
    protected function getAllRelationManagers(): array
    {
        return [];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Text::make('عدّل البيانات الأساسية والمواعيد والنشر من هذه الصفحة؛ إدارة التسجيلات من صفحة عرض البرنامج.')
                    ->columnSpanFull(),
                $this->getFormContentComponent(),
            ]);
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('حفظ التغييرات')
                ->color('success'),
            DeleteAction::make()
                ->label('حذف البرنامج')
                ->visible(fn (): bool => auth()->user()?->can('delete', $this->getRecord()) ?? false),
            $this->getCancelFormAction()
                ->label('إلغاء')
                ->color('gray'),
        ];
    }
}
