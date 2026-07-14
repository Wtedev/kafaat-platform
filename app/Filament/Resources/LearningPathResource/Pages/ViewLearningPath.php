<?php

namespace App\Filament\Resources\LearningPathResource\Pages;

use App\Enums\PathStatus;
use App\Filament\Actions\TransferTrainingEntityOwnershipAction;
use App\Filament\Concerns\HasInlineEntityViewEditing;
use App\Filament\Concerns\HasTrainingEntityPublicationActions;
use App\Filament\Concerns\HasTrainingEntitySettingsTab;
use App\Filament\Resources\Concerns\PreparesTrainingEntityFormData;
use App\Filament\Resources\LearningPathResource;
use App\Filament\Resources\Pages\BaseViewRecord;
use App\Filament\Support\EntityPublicationFormData;
use App\Filament\Support\LearningPathInlineEditSupport;
use App\Filament\Support\LearningPathViewPresenter;
use App\Filament\Support\TrainingEntityFormSupport;
use App\Models\LearningPath;
use Filament\Actions\DeleteAction;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ViewLearningPath extends BaseViewRecord
{
    use HasInlineEntityViewEditing;
    use HasTrainingEntityPublicationActions;
    use HasTrainingEntitySettingsTab;
    use PreparesTrainingEntityFormData;

    protected static string $resource = LearningPathResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->getRecord()->loadCount(['programs', 'registrations', 'completedPathRegistrations']);
        $this->getRecord()->loadMissing(['owner', 'creator', 'editors']);
        $this->initializeTrainingEntitySettingsTab();
    }

    public function getTitle(): string
    {
        $title = $this->getRecord()->title;

        return filled($title) ? 'مسار '.$title : parent::getTitle();
    }

    public function form(Schema $schema): Schema
    {
        return LearningPathResource::editForm($schema);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var LearningPath $record */
        $record = $this->getRecord();
        $data = EntityPublicationFormData::mergePublicationUiState($data, $record, PathStatus::Published);
        $data['capacity_unlimited'] = $record->capacity === null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var LearningPath $path */
        $path = $this->getRecord();
        $wantPublished = TrainingEntityFormSupport::wantsPublishedStatus($data);

        $data['status'] = $this->resolvePathPublicationStatus($path, $wantPublished)->value;

        $preservePublishTime = $path->status === PathStatus::Published && TrainingEntityFormSupport::wantsImmediatePublication($data);
        $data = TrainingEntityFormSupport::applyPublicationSchedule($data, $preservePublishTime);

        $data = TrainingEntityFormSupport::applyCapacityUnlimited($data);

        return $this->dropEmptyTrainingSlug(
            $this->stampTrainingEntityAuditFields($data),
        );
    }

    protected function getSettingsTabLabel(): string
    {
        return 'إعدادات المسار';
    }

    /**
     * @return array<int, mixed>
     */
    protected function getInlineEditableFieldSchema(string $field): array
    {
        /** @var LearningPath $path */
        $path = $this->getRecord();

        return LearningPathInlineEditSupport::fieldSchema($field, $path);
    }

    /**
     * @return array<string, string>
     */
    protected function getInlineEditableFieldLabels(): array
    {
        return LearningPathInlineEditSupport::labels();
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
            'title' => 'اسم المسار',
            'path_kind' => 'نوع المسار',
            'description' => 'الوصف',
            'image' => 'صورة الغلاف',
            'capacity' => 'الحد الأقصى للمسجّلين',
            'capacity_unlimited' => 'تسجيل غير محدود',
            'auto_accept_registrations' => 'قبول تلقائي',
            'status' => 'حالة النشر',
            'published_at' => 'موعد النشر',
            'publish_immediately' => 'نشر فوراً',
            'publication_schedule' => 'جدولة النشر',
            'notify_audience' => 'إشعارات المستفيدين',
            'notify_on_publish' => 'إشعارات المستفيدين',
            'owner_id' => 'مالك المسار',
        ];
    }

    protected function afterTrainingEntitySettingsSaved(): void
    {
        $this->getRecord()->loadMissing(['owner', 'creator', 'editors']);
        $this->getRecord()->loadCount(['programs', 'registrations', 'completedPathRegistrations']);
    }

    protected function recordIsPublishedForPublicationActions(): bool
    {
        return $this->getRecord()->status === PathStatus::Published;
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
        return 'معلومات المسار';
    }

    public function getContentTabComponent(): Tab
    {
        return $this->getBaseContentTabComponent()
            ->schema([
                $this->getPathViewPanel(),
                $this->getInfolistContentComponent(),
            ]);
    }

    protected function getPathViewPanel(): \Filament\Schemas\Components\Html
    {
        return $this->renderEntityViewPanel(
            fn (): array => LearningPathViewPresenter::present($this->getRecord()),
        );
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
