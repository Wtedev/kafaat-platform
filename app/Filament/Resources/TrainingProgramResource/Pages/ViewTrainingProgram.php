<?php

namespace App\Filament\Resources\TrainingProgramResource\Pages;

use App\Enums\ProgramStatus;
use App\Filament\Actions\TransferTrainingEntityOwnershipAction;
use App\Filament\Concerns\HasInlineEntityViewEditing;
use App\Filament\Concerns\HasTrainingEntityPublicationActions;
use App\Filament\Concerns\HasTrainingEntitySettingsTab;
use App\Filament\Resources\Concerns\PreparesTrainingEntityFormData;
use App\Filament\Resources\Pages\BaseViewRecord;
use App\Filament\Resources\TrainingProgramResource;
use App\Filament\Support\EntityPublicationFormData;
use App\Filament\Support\TrainingEntityFormSupport;
use App\Filament\Support\TrainingProgramInlineEditSupport;
use App\Filament\Support\TrainingProgramViewPresenter;
use App\Models\TrainingProgram;
use Filament\Actions\DeleteAction;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ViewTrainingProgram extends BaseViewRecord
{
    use HasInlineEntityViewEditing;
    use HasTrainingEntityPublicationActions;
    use HasTrainingEntitySettingsTab;
    use PreparesTrainingEntityFormData;

    protected static string $resource = TrainingProgramResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->getRecord()->loadMissing(['learningPath', 'owner', 'creator', 'assignee', 'editors']);
        $this->initializeTrainingEntitySettingsTab();
    }

    public function getTitle(): string
    {
        $title = $this->getRecord()->title;

        return filled($title) ? 'برنامج '.$title : parent::getTitle();
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
        $data = EntityPublicationFormData::mergePublicationUiState($data, $record, ProgramStatus::Published);
        $data = array_merge($data, TrainingEntityFormSupport::programPathLinkFormState($record));
        $data['capacity_unlimited'] = $record->capacity === null;
        $data['notify_audience'] = $record->notify_on_publish || $record->notify_milestones;
        $data['editors'] = TrainingEntityFormSupport::normalizeProgramEditorIds(
            $record->editors()->pluck('users.id')->all(),
            $record->owner_id !== null ? (int) $record->owner_id : null,
        );

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
        $data = $this->mergePendingInlineEditOverrides($data);
        $wantPublished = TrainingEntityFormSupport::wantsPublishedStatus($data);

        $linked = (bool) ($data['is_linked_to_path'] ?? false);

        $data = TrainingEntityFormSupport::applyProgramPathLinkSettings($data);

        TrainingEntityFormSupport::assertValidProgramScheduleOrFail($data, showRegistration: ! $linked);

        $data['status'] = $this->resolveProgramPublicationStatus($program, $wantPublished)->value;

        $preservePublishTime = $program->status === ProgramStatus::Published && TrainingEntityFormSupport::wantsImmediatePublication($data);
        $data = TrainingEntityFormSupport::applyPublicationSchedule($data, $preservePublishTime);

        $data = TrainingEntityFormSupport::applyCapacityUnlimited($data);
        $data = TrainingEntityFormSupport::applyAudienceNotifications($data);

        if (! $linked) {
            $ownerId = isset($data['owner_id']) ? (int) $data['owner_id'] : (int) $program->owner_id;
            $data['editors'] = TrainingEntityFormSupport::normalizeProgramEditorIds(
                is_array($data['editors'] ?? null) ? $data['editors'] : [],
                $ownerId > 0 ? $ownerId : null,
            );
        }

        return $this->dropEmptyTrainingSlug(
            $this->stampTrainingEntityAuditFields($data),
        );
    }

    protected function getSettingsTabLabel(): string
    {
        return 'إعدادات البرنامج';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function getInlineEditableFields(): array
    {
        /** @var TrainingProgram $program */
        $program = $this->getRecord();

        return TrainingProgramInlineEditSupport::fields($program);
    }

    /**
     * @return array<string, string>
     */
    protected function getInlineEditableFieldLabels(): array
    {
        return TrainingProgramInlineEditSupport::labels();
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveInlineEditFormStateOverrides(): array
    {
        /** @var TrainingProgram $program */
        $program = $this->getRecord();

        return TrainingEntityFormSupport::programPathLinkFormState($program);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveInlineEditFormStateForFieldFromRecord(string $field): ?array
    {
        if ($field !== 'schedule') {
            return null;
        }

        /** @var TrainingProgram $program */
        $program = $this->getRecord();

        return TrainingEntityFormSupport::scheduleFormState($program);
    }

    protected function canAccessSettingsTab(): bool
    {
        return false;
    }

  /**
   * @return array<string, string>
   */
    protected function getSettingsFieldLabels(): array
    {
        return [
            'title' => 'اسم البرنامج',
            'program_kind' => 'نوع البرنامج',
            'description' => 'الوصف',
            'image' => 'صورة الغلاف',
            'is_linked_to_path' => 'تابع لمسار تدريبي',
            'learning_path_id' => 'المسار التدريبي',
            'capacity' => 'الحد الأقصى للمسجّلين',
            'capacity_unlimited' => 'تسجيل غير محدود',
            'auto_accept_registrations' => 'قبول تلقائي',
            'start_date' => 'تاريخ البدء',
            'end_date' => 'تاريخ الانتهاء',
            'registration_start' => 'بداية التسجيل',
            'registration_end' => 'نهاية التسجيل',
            'weekdays' => 'أيام البرنامج',
            'status' => 'حالة النشر',
            'published_at' => 'موعد النشر',
            'publish_immediately' => 'نشر فوراً',
            'publication_schedule' => 'جدولة النشر',
            'notify_audience' => 'إشعارات المستفيدين',
            'owner_id' => 'مالك البرنامج',
            'assigned_to' => 'المسؤول',
            'editors' => 'أعضاء فريق العمل',
        ];
    }

    protected function afterTrainingEntitySettingsSaved(): void
    {
        $this->getRecord()->unsetRelation('learningPath');
        $this->getRecord()->loadMissing(['learningPath', 'owner', 'creator', 'assignee', 'editors']);
    }

    protected function afterInlineEntityFieldEdited(string $field): void
    {
        $this->getRecord()->refresh();
        $this->getRecord()->unsetRelation('learningPath');
        $this->getRecord()->loadMissing(['learningPath', 'owner', 'creator', 'assignee', 'editors']);
    }

    protected function getProgramViewPanel(): \Filament\Schemas\Components\Html
    {
        return $this->renderEntityViewPanel(function (): array {
            /** @var TrainingProgram $program */
            $program = $this->getRecord()->fresh();
            $program->loadMissing(['learningPath', 'owner', 'creator', 'assignee', 'editors']);

            return TrainingProgramViewPresenter::present($program);
        });
    }

    protected function recordIsPublishedForPublicationActions(): bool
    {
        return $this->getRecord()->status === ProgramStatus::Published;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'معلومات البرنامج';
    }

    public function getContentTabComponent(): Tab
    {
        return $this->getBaseContentTabComponent()
            ->schema([
                $this->getProgramViewPanel(),
                $this->getInfolistContentComponent(),
            ]);
    }

    protected function getViewPageToolbarActions(): array
    {
        return [
            TransferTrainingEntityOwnershipAction::make($this),
            DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->can('delete', $this->getRecord()) ?? false),
        ];
    }
}
