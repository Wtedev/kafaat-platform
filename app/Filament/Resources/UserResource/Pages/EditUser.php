<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Models\VolunteerTeam;
use App\Support\UserAccountRoleForm;
use Filament\Actions\DeleteAction;
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

    protected ?string $lockedPlatformRole = null;

    protected ?string $pendingPlatformRole = null;

    protected ?string $pendingRoleType = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var User $user */
        $user = $this->record;
        $this->lockedRoleIds = $user->roles->pluck('id')->all();
        $this->lockedRoleType = $user->role_type;

        if (! $user->isProtectedAdminUser()) {
            $this->lockedPlatformRole = UserAccountRoleForm::platformRoleFromUser($user);
        }
    }

    protected function getRecordToolbarActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (): bool => $this->record->isProtectedAdminUser()),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->record;

        if (! UserAccountRoleForm::canActorEditRoleSection(auth()->user(), $user)) {
            unset($data['platform_role']);

            return $data;
        }

        $data['platform_role'] = UserAccountRoleForm::platformRoleFromUser($user);

        return $data;
    }

    protected function afterValidate(): void
    {
        if (UserAccountRoleForm::canActorEditRoleSection(auth()->user(), $this->record)) {
            return;
        }

        $data = $this->form->getState();

        if (array_key_exists('platform_role', $data)
            && (string) ($data['platform_role'] ?? '') !== (string) ($this->lockedPlatformRole ?? '')) {
            throw ValidationException::withMessages([
                'data.platform_role' => 'تعديل الدور غير متاح لحسابك.',
            ]);
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingPlatformRole = null;
        $this->pendingRoleType = null;

        /** @var User $record */
        $record = $this->record;

        if ($record->isProtectedAdminUser()
            || ! UserAccountRoleForm::canActorEditRoleSection(auth()->user(), $record)) {
            unset($data['platform_role']);

            return $data;
        }

        $platformRole = (string) ($data['platform_role'] ?? '');
        if ($platformRole === '') {
            throw ValidationException::withMessages([
                'data.platform_role' => 'يرجى اختيار الدور.',
            ]);
        }

        UserAccountRoleForm::assertActorMayAssign(auth()->user(), $platformRole);
        $resolved = UserAccountRoleForm::resolvePlatformRole($platformRole);

        $this->pendingPlatformRole = $resolved['spatie'];
        $this->pendingRoleType = $resolved['role_type'];
        $data['role_type'] = $resolved['role_type'];
        unset($data['platform_role']);

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

        if (! UserAccountRoleForm::canActorEditRoleSection(auth()->user(), $record)) {
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

        if ($this->pendingPlatformRole !== null && $this->pendingPlatformRole !== '') {
            $record->syncRoles([$this->pendingPlatformRole]);
        }

        if ($this->pendingRoleType !== null && (string) $record->role_type !== $this->pendingRoleType) {
            $record->update(['role_type' => $this->pendingRoleType]);
        }

        if ($record->hasRole('volunteer')) {
            VolunteerTeam::ensureMember($record);
        }
    }
}
