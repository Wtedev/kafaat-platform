<?php

namespace App\Filament\Resources\VolunteerOpportunityResource\Pages;

use App\Enums\OpportunityStatus;
use App\Filament\Concerns\HasTrainingEntitySettingsTab;
use App\Filament\Concerns\RendersEntityViewPanel;
use App\Filament\Resources\Concerns\PreparesTrainingEntityFormData;
use App\Filament\Resources\Pages\BaseViewRecord;
use App\Filament\Resources\VolunteerOpportunityResource;
use App\Filament\Support\EntityPublicationFormData;
use App\Filament\Support\TrainingEntityFormSupport;
use App\Filament\Support\VolunteerOpportunityViewPresenter;
use App\Models\VolunteerOpportunity;
use Filament\Actions\DeleteAction;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ViewVolunteerOpportunity extends BaseViewRecord
{
    use HasTrainingEntitySettingsTab;
    use PreparesTrainingEntityFormData;
    use RendersEntityViewPanel;

    protected static string $resource = VolunteerOpportunityResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->getRecord()->loadCount('registrations');
        $this->getRecord()->loadMissing(['assignee', 'creator']);
        $this->initializeTrainingEntitySettingsTab();
    }

    public function getTitle(): string
    {
        $title = $this->getRecord()->title;

        return filled($title) ? 'فرصة '.$title : parent::getTitle();
    }

    public function form(Schema $schema): Schema
    {
        return VolunteerOpportunityResource::editForm($schema);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var VolunteerOpportunity $record */
        $record = $this->getRecord();
        $data = EntityPublicationFormData::mergePublicationUiState($data, $record, OpportunityStatus::Published);
        $data['capacity_unlimited'] = $record->capacity === null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var VolunteerOpportunity $opportunity */
        $opportunity = $this->getRecord();
        $wantPublished = TrainingEntityFormSupport::wantsPublishedStatus($data);

        $data['status'] = $this->resolveOpportunityPublicationStatus($opportunity, $wantPublished)->value;

        $preservePublishTime = $opportunity->status === OpportunityStatus::Published
            && TrainingEntityFormSupport::wantsImmediatePublication($data);
        $data = TrainingEntityFormSupport::applyPublicationSchedule($data, $preservePublishTime);
        $data = TrainingEntityFormSupport::applyCapacityUnlimited($data);

        return $this->dropEmptyTrainingSlug(
            $this->stampTrainingEntityAuditFields($data),
        );
    }

    protected function getSettingsTabLabel(): string
    {
        return 'إعدادات الفرصة';
    }

    /**
     * @return array<string, string>
     */
    protected function getSettingsFieldLabels(): array
    {
        return [
            'title' => 'اسم الفرصة',
            'slug' => 'الرابط المختصر',
            'description' => 'الوصف',
            'image' => 'صورة الفرصة',
            'capacity' => 'الحد الأقصى للمسجّلين',
            'capacity_unlimited' => 'تسجيل غير محدود',
            'hours_expected' => 'الساعات المتوقعة',
            'start_date' => 'تاريخ البداية',
            'end_date' => 'تاريخ الانتهاء',
            'status' => 'حالة النشر',
            'published_at' => 'موعد النشر',
            'publish_immediately' => 'نشر فوراً',
            'notify_on_publish' => 'إشعارات المستفيدين',
            'notify_registrants_on_update' => 'تنبيه المسجّلين',
            'assigned_to' => 'منسق الفرصة',
        ];
    }

    protected function afterTrainingEntitySettingsSaved(): void
    {
        $this->getRecord()->loadMissing(['assignee', 'creator']);
        $this->getRecord()->loadCount('registrations');
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
        return 'معلومات الفرصة';
    }

    public function getContentTabComponent(): Tab
    {
        return $this->getBaseContentTabComponent()
            ->schema([
                $this->getOpportunityViewPanel(),
                $this->getInfolistContentComponent(),
            ]);
    }

    protected function getOpportunityViewPanel(): \Filament\Schemas\Components\Html
    {
        return $this->renderEntityViewPanel(
            fn (): array => VolunteerOpportunityViewPresenter::present($this->getRecord()),
        );
    }

    protected function getViewPageToolbarActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->can('delete', $this->getRecord()) ?? false),
        ];
    }
}
