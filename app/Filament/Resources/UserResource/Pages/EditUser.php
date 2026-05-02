<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Support\UserAccountRoleForm;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class EditUser extends BaseEditRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return 'تعديل المستخدم';
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'تم حفظ التغييرات';
    }

    /** @var list<int|string> */
    protected array $lockedRoleIds = [];

    protected ?string $lockedRoleType = null;

    protected ?string $lockedAssignedRole = null;

    protected ?string $lockedFormAccountType = null;

    protected ?string $pendingAssignedRoleForSync = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var User $user */
        $user = $this->record;
        $this->lockedRoleIds = $user->roles->pluck('id')->all();
        $this->lockedRoleType = $user->role_type;

        if (! $user->isProtectedAdminUser()) {
            $this->lockedAssignedRole = UserAccountRoleForm::resolvedSpatieRoleFromUser($user);
            $this->lockedFormAccountType = UserAccountRoleForm::formAccountTypeFromUser($user);
        }
    }

    protected function getRecordToolbarActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->hidden(fn (): bool => $this->record->isProtectedAdminUser()),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->record;

        if (! auth()->user()?->can('manage_roles') || $user->isProtectedAdminUser()) {
            unset($data['assigned_role'], $data['role_type']);

            return $data;
        }

        $data['role_type'] = UserAccountRoleForm::formAccountTypeFromUser($user);
        $data['assigned_role'] = UserAccountRoleForm::resolvedSpatieRoleFromUser($user);

        return $data;
    }

    protected function afterValidate(): void
    {
        if (auth()->user()?->can('manage_roles')) {
            return;
        }

        $data = $this->form->getState();

        if (array_key_exists('assigned_role', $data)
            && (string) ($data['assigned_role'] ?? '') !== (string) ($this->lockedAssignedRole ?? '')) {
            throw ValidationException::withMessages([
                'data.assigned_role' => 'تعديل الدور متاح لمن يملك صلاحية إدارة الأدوار فقط.',
            ]);
        }

        if (array_key_exists('role_type', $data)
            && (string) ($data['role_type'] ?? '') !== (string) ($this->lockedFormAccountType ?? '')) {
            throw ValidationException::withMessages([
                'data.role_type' => 'تعديل نوع الحساب متاح لمن يملك صلاحية إدارة الأدوار فقط.',
            ]);
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingAssignedRoleForSync = null;

        $actor = auth()->user();
        /** @var User $record */
        $record = $this->record;

        if ($record->isProtectedAdminUser() || ! ($actor?->can('manage_roles') ?? false)) {
            unset($data['assigned_role'], $data['role_type']);

            return $data;
        }

        $accountType = (string) ($data['role_type'] ?? '');
        $assigned = (string) ($data['assigned_role'] ?? '');

        if (! in_array($accountType, [UserAccountRoleForm::TYPE_STAFF, UserAccountRoleForm::TYPE_BENEFICIARY], true)) {
            throw ValidationException::withMessages([
                'data.role_type' => 'نوع الحساب غير صالح.',
            ]);
        }

        if ($assigned === '') {
            throw ValidationException::withMessages([
                'data.assigned_role' => 'يرجى اختيار الدور.',
            ]);
        }

        UserAccountRoleForm::assertValidCombination($accountType, $assigned);

        $this->pendingAssignedRoleForSync = $assigned;
        unset($data['assigned_role']);

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record->fresh();
        if ($record === null) {
            return;
        }

        if ($record->isProtectedAdminUser()) {
            return;
        }

        $actor = auth()->user();
        if (! ($actor?->can('manage_roles') ?? false)) {
            $roleNames = Role::query()
                ->whereIn('id', $this->lockedRoleIds)
                ->pluck('name')
                ->all();

            $record->syncRoles($roleNames);

            if ((string) $record->role_type !== (string) $this->lockedRoleType) {
                $record->update(['role_type' => $this->lockedRoleType]);
            }

            return;
        }

        if ($this->pendingAssignedRoleForSync !== null && $this->pendingAssignedRoleForSync !== '') {
            $record->syncRoles([$this->pendingAssignedRoleForSync]);
        }
    }
}
