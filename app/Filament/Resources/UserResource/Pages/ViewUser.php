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
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;
use Throwable;

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

    protected function entityViewPanelStateKey(): string
    {
        /** @var User $user */
        $user = $this->getRecord();
        $user->loadMissing('profile');
        $profileStamp = $user->profile?->updated_at?->getTimestamp() ?? 0;

        return 'kafaat-entity-view-'.$user->getKey()
            .'-'.($user->updated_at?->getTimestamp() ?? 0)
            .'-'.$profileStamp;
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
        return UserInlineEditSupport::editableSectionKeys() !== [];
    }

    protected function canInlineEditEntityViewSection(string $field): bool
    {
        return UserInlineEditSupport::canEditSection($field);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveInlineEditFormStateForFieldFromRecord(string $field): ?array
    {
        /** @var User $user */
        $user = $this->getRecord();
        $profile = $user->profile;

        return match ($field) {
            'profile' => UserInlineEditSupport::profileFormState($profile),
            'competency' => UserInlineEditSupport::competencyFormState($profile),
            'bio' => [
                'bio' => $profile?->bio,
            ],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function commitInlineEntityFieldEdit(string $field, array $data): void
    {
        abort_unless($this->canInlineEditEntityViewSection($field), 403);
        abort_if(! array_key_exists($field, $this->getInlineEditableFields()), 404);

        if (in_array($field, ['profile', 'competency', 'bio'], true)) {
            $this->commitInlineBeneficiaryProfileFieldEdit($field, $data);

            return;
        }

        parent::commitInlineEntityFieldEdit($field, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function commitInlineBeneficiaryProfileFieldEdit(string $field, array $data): void
    {
        try {
            /** @var User $user */
            $user = $this->getRecord();
            $profile = $user->profile ?? $user->profile()->create([
                'user_id' => $user->getKey(),
            ]);

            $attributes = UserInlineEditSupport::extractProfileAttributesForField(
                $field,
                $data,
                auth()->user(),
            );

            $profile->update($attributes);
            $user->unsetRelation('profile');
            $user->loadMissing('profile');

            $this->afterInlineEntityFieldEdited($field);
            $this->forceRender();

            Notification::make()
                ->success()
                ->title('تم حفظ الإعدادات بنجاح')
                ->send();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Halt) {
            Notification::make()
                ->warning()
                ->title('لم يتم حفظ الإعدادات')
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('تعذّر حفظ التعديل')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function afterInlineEntityFieldEdited(string $field): void
    {
        $this->getRecord()->refresh();
        $this->getRecord()->loadMissing(['profile', 'roles', 'profileRecommendations']);

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
            'notify_email' => 'إشعارات البريد',
            'platform_role' => 'الدور في المنصة',
            'gender' => 'الجنس',
            'birth_date' => 'تاريخ الميلاد',
            'city' => 'المدينة',
            'job_title' => 'المسمى الوظيفي',
            'cv_language' => 'لغة السيرة',
            'membership_badges' => 'شارات العضوية',
            'iconic_skill' => 'المهارة المميزة',
            'iconic_skill_style' => 'لون شارة المهارة',
            'competency_levels' => 'مستويات الكفاءات',
            'bio' => 'نبذة تعريفية',
        ];
    }

    protected function getViewPageToolbarActions(): array
    {
        return [];
    }
}
