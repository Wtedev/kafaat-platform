<?php

namespace App\Filament\Concerns;

use App\Filament\Support\TrainingEntityFormChangeSummarizer;
use App\Filament\Support\TrainingEntityFormSupport;
use App\Filament\Support\TrainingEntitySettingsState;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Concerns\HasUnsavedDataChangesAlert;
use Filament\Resources\Events\RecordSaved;
use Filament\Resources\Events\RecordUpdated;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Livewire\Partials\PartialsComponentHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Throwable;

/**
 * يضيف تبويب إعدادات قابل للتعديل داخل صفحة العرض مع تأكيد التغييرات قبل الحفظ.
 */
trait HasTrainingEntitySettingsTab
{
    use CanUseDatabaseTransactions;
    use HasUnsavedDataChangesAlert;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $settingsBaselineData = null;

    public bool $settingsFormDirty = false;

    abstract protected function getSettingsTabLabel(): string;

    /**
     * @return array<string, string>
     */
    abstract protected function getSettingsFieldLabels(): array;

    protected function getSettingsTabKey(): string
    {
        return 'settings';
    }

    protected function canAccessSettingsTab(): bool
    {
        return auth()->user()?->can('update', $this->getRecord()) ?? false;
    }

    protected function initializeTrainingEntitySettingsTab(): void
    {
        if (! $this->canAccessSettingsTab() && ! $this->canSaveTrainingEntitySettings()) {
            return;
        }

        $this->settingsFormDirty = false;

        if (strval($this->activeRelationManager ?? '') === $this->getSettingsTabKey()) {
            $this->captureSettingsBaselineForSettingsTab();

            return;
        }

        $this->fillSettingsForm();
        $this->settingsBaselineData = null;
        $this->settingsFormDirty = false;
    }

    public function updatingHasTrainingEntitySettingsTab(string $fullPath, mixed $newValue): void
    {
        if (! $this->shouldTrackSettingsFormChanges($fullPath)) {
            return;
        }

        if ($this->settingsBaselineData === null) {
            $this->captureSettingsBaseline();
        }
    }

    public function updatedHasTrainingEntitySettingsTab(string $fullPath, mixed $newValue): void
    {
        if (! $this->shouldTrackSettingsFormChanges($fullPath)) {
            return;
        }

        $this->refreshSettingsChangeState();
    }

    public function refreshSettingsChangeState(): void
    {
        if (! $this->canAccessSettingsTab() || ! $this->isSettingsTabActive()) {
            return;
        }

        $isDirty = ! $this->settingsChangesAreEmpty();

        if ($this->settingsFormDirty === $isDirty) {
            return;
        }

        $this->settingsFormDirty = $isDirty;

        $this->partiallyRenderSchemaComponent('trainingEntitySettingsFormActions');
        app(PartialsComponentHook::class)->forceRender($this);
    }

    protected function shouldTrackSettingsFormChanges(string $statePath): bool
    {
        return $this->canAccessSettingsTab()
            && $this->isSettingsTabActive()
            && str_starts_with($statePath, 'data.');
    }

    protected function isSettingsTabActive(): bool
    {
        return strval($this->activeRelationManager ?? '') === $this->getSettingsTabKey();
    }

    protected function captureSettingsBaselineForSettingsTab(): void
    {
        if (! $this->canAccessSettingsTab()) {
            $this->settingsBaselineData = null;

            return;
        }

        $this->fillSettingsForm();
        $this->captureSettingsBaseline();
        $this->settingsFormDirty = false;
    }

    protected function fillSettingsForm(): void
    {
        $this->fillFormWithDataAndCallHooks($this->getRecord());
    }

    protected function captureSettingsBaseline(): void
    {
        if (! $this->canAccessSettingsTab()) {
            $this->settingsBaselineData = null;

            return;
        }

        $raw = $this->form->getRawState();
        $this->settingsBaselineData = is_array($raw)
            ? TrainingEntitySettingsState::snapshotRawFormState($raw)
            : [];
    }

    public function defaultForm(Schema $schema): Schema
    {
        if (! $schema->hasCustomColumns()) {
            $schema->columns($this->hasInlineLabels() ? 1 : 2);
        }

        return $schema
            ->inlineLabel($this->hasInlineLabels())
            ->model($this->getRecord())
            ->operation('edit')
            ->statePath('data')
            ->live(debounce: 500);
    }

    public function getRelationManagersContentComponent(): Component
    {
        $managers = $this->getRelationManagers();
        $hasCombinedRelationManagerTabsWithContent = $this->hasCombinedRelationManagerTabsWithContent();
        $ownerRecord = $this->getRecord();

        $managerLivewireData = ['ownerRecord' => $ownerRecord, 'pageClass' => static::class];

        if (property_exists($this, 'activeLocale') && filled($this->activeLocale ?? null)) {
            $managerLivewireData['activeLocale'] = $this->activeLocale;
        }

        if (! ((count($managers) > 1) || $hasCombinedRelationManagerTabsWithContent)) {
            return parent::getRelationManagersContentComponent();
        }

        $tabs = $managers;

        if ($hasCombinedRelationManagerTabsWithContent) {
            $tabs = array_replace(['' => null], $tabs);
        }

        if ($this->canAccessSettingsTab()) {
            $tabs = $this->insertSettingsTabAfterContentTab($tabs, $hasCombinedRelationManagerTabsWithContent);
        }

        $tabs = collect($tabs)
            ->map(function ($manager, string|int $tabKey) use ($hasCombinedRelationManagerTabsWithContent, $managerLivewireData, $ownerRecord): Tab {
                $tabKey = strval($tabKey);

                if ($tabKey === $this->getSettingsTabKey()) {
                    return $this->getSettingsTabComponent();
                }

                if (blank($tabKey) && $hasCombinedRelationManagerTabsWithContent) {
                    return $this->getContentTabComponent();
                }

                if ($manager instanceof RelationGroup) {
                    $manager->ownerRecord($ownerRecord);
                    $manager->pageClass(static::class);

                    return $manager->getTabComponent()
                        ->schema(fn (): array => collect($manager->getManagers())
                            ->map(fn ($groupedManager, $groupedManagerKey): Livewire => Livewire::make(
                                $normalizedGroupedManagerClass = $this->normalizeRelationManagerClass($groupedManager),
                                [...$managerLivewireData, ...(($groupedManager instanceof RelationManagerConfiguration) ? [...$groupedManager->relationManager::getDefaultProperties(), ...$groupedManager->getProperties()] : $groupedManager::getDefaultProperties())],
                            )->key("{$normalizedGroupedManagerClass}-{$groupedManagerKey}"))
                            ->all());
                }

                $normalizedManagerClass = $this->normalizeRelationManagerClass($manager);

                return $normalizedManagerClass::getTabComponent($ownerRecord, static::class)
                    ->schema(fn (): array => [
                        Livewire::make(
                            $normalizedManagerClass,
                            [...$managerLivewireData, ...(($manager instanceof RelationManagerConfiguration) ? [...$manager->relationManager::getDefaultProperties(), ...$manager->getProperties()] : $manager::getDefaultProperties())],
                        )->key($normalizedManagerClass),
                    ]);
            })
            ->all();

        return Tabs::make()
            ->key('relationManagerTabs')
            ->livewireProperty('activeRelationManager')
            ->contained(false)
            ->tabs($tabs);
    }

    protected function insertSettingsTabAfterContentTab(array $tabs, bool $hasContentTab): array
    {
        unset($tabs[$this->getSettingsTabKey()]);

        $ordered = [];

        foreach ($tabs as $key => $manager) {
            $ordered[$key] = $manager;
        }

        $ordered[$this->getSettingsTabKey()] = 'settings';

        return $ordered;
    }

    protected function getSettingsTabComponent(): Tab
    {
        return Tab::make($this->getSettingsTabLabel())
            ->key($this->getSettingsTabKey())
            ->schema([
                $this->getSettingsFormContentComponent(),
            ]);
    }

    public function getSettingsFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('training-entity-settings-form')
            ->key('trainingEntitySettingsForm')
            ->footer([
                Actions::make([
                    $this->getSettingsSaveAction(),
                ])
                    ->alignment($this->getFormActionsAlignment())
                    ->key('trainingEntitySettingsFormActions'),
            ]);
    }

    protected function getSettingsSaveAction(): Action
    {
        return Action::make('saveSettings')
            ->label('حفظ التغييرات')
            ->color(fn (): string => $this->settingsFormDirty ? 'primary' : 'gray')
            ->disabled(fn (): bool => $this->shouldDisableSettingsSaveAction())
            ->extraAttributes(fn (): array => [
                'class' => $this->settingsFormDirty
                    ? 'fi-training-settings-save'
                    : 'fi-training-settings-save fi-training-settings-save--disabled',
            ])
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-clipboard-document-check')
            ->modalIconColor('primary')
            ->modalWidth(Width::Large)
            ->modalHeading('تأكيد تحديث الإعدادات')
            ->modalDescription(fn (): string => $this->getSettingsSaveModalDescription())
            ->modalContent(fn (): HtmlString => TrainingEntityFormChangeSummarizer::toHtml(
                $this->settingsBaselineData ?? [],
                $this->form->getRawState(),
                $this->getSettingsFieldLabels(),
            ))
            ->modalSubmitActionLabel('تطبيق التعديلات')
            ->modalCancelActionLabel('إلغاء')
            ->action(fn (): mixed => $this->commitTrainingEntitySettingsFromTab());
    }

    protected function shouldDisableSettingsSaveAction(): bool
    {
        $mountedAction = $this->getMountedAction();

        if ($mountedAction?->getName() === 'saveSettings') {
            return false;
        }

        return ! $this->settingsFormDirty;
    }

    protected function getSettingsSaveModalDescription(): string
    {
        $count = count(TrainingEntityFormChangeSummarizer::structuredChanges(
            $this->settingsBaselineData ?? [],
            is_array($this->form->getRawState()) ? $this->form->getRawState() : [],
            $this->getSettingsFieldLabels(),
        ));

        if ($count === 0) {
            return 'لا توجد تعديلات للتطبيق.';
        }

        if ($count === 1) {
            return 'سيتم تطبيق تعديل واحد. يمكنك التراجع بالضغط على «إلغاء».';
        }

        if ($count === 2) {
            return 'سيتم تطبيق تعديلين. يمكنك التراجع بالضغط على «إلغاء».';
        }

        if ($count <= 10) {
            return 'سيتم تطبيق '.$count.' تعديلات. يمكنك التراجع بالضغط على «إلغاء».';
        }

        return 'سيتم تطبيق '.$count.' تعديلاً. يمكنك التراجع بالضغط على «إلغاء».';
    }

    public function commitTrainingEntitySettingsFromTab(): mixed
    {
        if (! $this->settingsFormDirty && $this->settingsChangesAreEmpty()) {
            Notification::make()
                ->warning()
                ->title('لا توجد تغييرات للحفظ')
                ->send();

            return null;
        }

        $this->saveTrainingEntitySettings();

        return null;
    }

    public function updatedActiveRelationManager(mixed $value): void
    {
        if (strval($value) !== $this->getSettingsTabKey()) {
            $this->settingsBaselineData = null;
            $this->settingsFormDirty = false;

            return;
        }

        if (! $this->canAccessSettingsTab()) {
            return;
        }

        $this->captureSettingsBaselineForSettingsTab();
    }

    /**
     * @return array<int, string>
     */
    public function getSettingsPendingChanges(): array
    {
        if ($this->settingsBaselineData === null) {
            return [];
        }

        return TrainingEntityFormChangeSummarizer::describeChanges(
            $this->settingsBaselineData,
            $this->form->getRawState(),
            $this->getSettingsFieldLabels(),
        );
    }

    protected function settingsChangesAreEmpty(): bool
    {
        return TrainingEntitySettingsState::changesAreEmpty(
            $this->settingsBaselineData,
            is_array($this->form->getRawState()) ? $this->form->getRawState() : [],
            $this->getSettingsFieldLabels(),
        );
    }

    public function saveTrainingEntitySettings(bool $shouldSendSavedNotification = true): void
    {
        abort_unless($this->canSaveTrainingEntitySettings(), 403);

        try {
            $this->beginDatabaseTransaction();

            $this->callHook('beforeValidate');

            $scopedFieldKeys = method_exists($this, 'resolveInlineEditPersistFieldKeys')
                ? $this->resolveInlineEditPersistFieldKeys()
                : null;

            if ($scopedFieldKeys !== null) {
                $this->callHook('afterValidate');
                $this->callHook('beforeSave');

                $raw = is_array($this->form->getRawState()) ? $this->form->getRawState() : [];
                $data = TrainingEntityFormSupport::mergeNonDehydratedFormFlags(
                    array_merge($raw, $this->pendingInlineEditOverrides ?? []),
                    $raw,
                    [
                        'is_linked_to_path',
                        'capacity_unlimited',
                        'notify_audience',
                        'publish_immediately',
                    ],
                );
            } else {
                $data = $this->form->getState(afterValidate: function (): void {
                    $this->callHook('afterValidate');
                    $this->callHook('beforeSave');
                });

                $data = TrainingEntityFormSupport::mergeNonDehydratedFormFlags(
                    $data,
                    $this->form->getRawState(),
                    [
                        'is_linked_to_path',
                        'capacity_unlimited',
                        'notify_audience',
                        'publish_immediately',
                    ],
                );
            }

            $data = $this->mutateFormDataBeforeSave($data);

            $editorIds = is_array($data['editors'] ?? null) ? $data['editors'] : null;

            $this->handleRecordUpdate($this->getRecord(), $this->stripNonPersistedFormKeys($data));

            $this->syncTrainingEntityEditors($editorIds);

            $this->callHook('afterSave');
            Event::dispatch(RecordUpdated::class, ['record' => $this->record, 'data' => $data, 'page' => $this]);
            Event::dispatch(RecordSaved::class, ['record' => $this->record, 'data' => $data, 'page' => $this]);
        } catch (Halt $exception) {
            $exception->shouldRollbackDatabaseTransaction() ?
                $this->rollBackDatabaseTransaction() :
                $this->commitDatabaseTransaction();

            Notification::make()
                ->warning()
                ->title('لم يتم حفظ الإعدادات')
                ->send();

            return;
        } catch (ValidationException $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        }

        $this->commitDatabaseTransaction();

        $this->rememberData();

        $this->getRecord()->refresh();
        $this->afterTrainingEntitySettingsSaved();

        if ($scopedFieldKeys !== ['description']) {
            $this->fillSettingsForm();
            $this->captureSettingsBaseline();
        }

        $this->settingsFormDirty = false;

        if ($shouldSendSavedNotification) {
            Notification::make()
                ->success()
                ->title('تم حفظ الإعدادات بنجاح')
                ->send();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stripNonPersistedFormKeys(array $data): array
    {
        unset(
            $data['editors'],
            $data['schedule_calendar'],
            $data['registration_status_display'],
            $data['is_linked_to_path'],
            $data['capacity_unlimited'],
            $data['notify_audience'],
            $data['publish_immediately'],
        );

        return $data;
    }

    protected function canSaveTrainingEntitySettings(): bool
    {
        if ($this->canAccessSettingsTab()) {
            return true;
        }

        if (method_exists($this, 'canInlineEditEntityView')) {
            return $this->canInlineEditEntityView();
        }

        return false;
    }

    protected function afterTrainingEntitySettingsSaved(): void
    {
    }

    /**
     * @param  array<int, int|string>|null  $editorIds
     */
    protected function syncTrainingEntityEditors(?array $editorIds): void
    {
        if ($editorIds === null || ! method_exists($this->getRecord(), 'editors')) {
            return;
        }

        $this->getRecord()->editors()->sync(
            collect($editorIds)
                ->map(static fn ($id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->values()
                ->all(),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);

        return $record;
    }

    protected function hasFullWidthFormActions(): bool
    {
        return false;
    }
}
