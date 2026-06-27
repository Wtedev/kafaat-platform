<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Concerns\HasInlineEntityViewEditing;
use App\Filament\Concerns\HasTrainingEntitySettingsTab;
use App\Filament\Resources\Pages\BaseViewRecord;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Concerns\ManagesUserAccountForm;
use App\Filament\Support\UserInlineEditSupport;
use App\Filament\Support\UserViewPresenter;
use App\Models\User;
use App\Services\UserActivityLogger;
use Filament\Actions\DeleteAction;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ViewUser extends BaseViewRecord
{
    use HasInlineEntityViewEditing;
    use HasTrainingEntitySettingsTab;
    use ManagesUserAccountForm;

    protected static string $resource = UserResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->getRecord()->loadMissing([
            'profile',
            'roles',
            'profileRecommendations',
        ]);

        $this->initializeUserAccountFormLocks();
        $this->initializeTrainingEntitySettingsTab();
    }

    public function getTitle(): string
    {
        $name = $this->getRecord()->name;

        return filled($name) ? 'مستفيد: '.$name : parent::getTitle();
    }

    public function form(Schema $schema): Schema
    {
        return UserResource::form($schema);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->mutateUserFormDataBeforeFill($data);
    }

    protected function afterValidate(): void
    {
        $this->validateUserAccountFormRoles();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mutateUserFormDataBeforeSave($data);
    }

    protected function afterSave(): void
    {
        $this->afterUserAccountFormSaved();
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
        return 'معلومات المستفيد';
    }

    public function getContentTabComponent(): Tab
    {
        return $this->getBaseContentTabComponent()
            ->schema([
                $this->getUserViewPanel(),
                $this->getInfolistContentComponent(),
            ]);
    }

    protected function getUserViewPanel(): \Filament\Schemas\Components\Html
    {
        return $this->renderEntityViewPanel(
            fn (): array => UserViewPresenter::present(
                $this->getRecord()->loadMissing(['profile', 'roles', 'profileRecommendations']),
            ),
        );
    }

    protected function getSettingsTabLabel(): string
    {
        return 'إعدادات المستفيد';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function getInlineEditableFields(): array
    {
        return UserInlineEditSupport::fields();
    }

    /**
     * @return array<string, string>
     */
    protected function getInlineEditableFieldLabels(): array
    {
        return UserInlineEditSupport::labels();
    }

    protected function canAccessSettingsTab(): bool
    {
        return false;
    }

    public function canInlineEditEntityView(): bool
    {
        $actor = auth()->user();

        return $actor !== null && $actor->can('users.update');
    }

    protected function afterInlineEntityFieldEdited(string $field): void
    {
        $actor = auth()->user();
        $record = $this->getRecord();

        if (! $actor instanceof User || ! $record instanceof User || ! $record->isPortalUser()) {
            return;
        }

        $label = $this->getInlineEditableFieldLabels()[$field] ?? $field;
        UserActivityLogger::logAdminUserUpdated($record, [$label], $actor);
    }

    /**
     * @return array<string, string>
     */
    protected function getSettingsFieldLabels(): array
    {
        return [
            'name' => 'الاسم الكامل',
            'email' => 'البريد الإلكتروني',
            'password' => 'كلمة المرور',
            'phone' => 'رقم الجوال',
            'is_active' => 'نشط',
            'platform_role' => 'الدور في المنصة',
        ];
    }

    protected function getViewPageToolbarActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (): bool => $this->getRecord()->isProtectedAdminUser()),
        ];
    }
}
