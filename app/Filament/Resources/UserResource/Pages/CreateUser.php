<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\Pages\BaseCreateRecord;
use App\Filament\Resources\UserResource;
use App\Models\VolunteerTeam;
use App\Support\UserAccountRoleForm;
use App\Support\UserDirectoryTabs;
use Illuminate\Validation\ValidationException;

class CreateUser extends BaseCreateRecord
{
    protected static string $resource = UserResource::class;

    protected ?string $pendingPlatformRole = null;

    public function mount(): void
    {
        parent::mount();

        $tab = (string) request()->query('directory_tab', '');
        if (! UserDirectoryTabs::isValidTab($tab)) {
            return;
        }

        if (! UserDirectoryTabs::actorCanViewTab(auth()->user(), $tab)) {
            return;
        }

        $defaultRole = UserDirectoryTabs::defaultPlatformRoleForTab($tab);
        if ($defaultRole === null) {
            return;
        }

        $options = UserAccountRoleForm::platformRoleOptionsForActor(auth()->user());
        if (! array_key_exists($defaultRole, $options)) {
            return;
        }

        $this->form->fill([
            'platform_role' => $defaultRole,
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingPlatformRole = null;
        $actor = auth()->user();

        if (! UserAccountRoleForm::canActorEditRoleSection($actor)) {
            unset($data['platform_role']);

            return $data;
        }

        $platformRole = (string) ($data['platform_role'] ?? '');
        if ($platformRole === '') {
            throw ValidationException::withMessages([
                'data.platform_role' => 'يرجى اختيار الدور.',
            ]);
        }

        UserAccountRoleForm::assertActorMayAssign($actor, $platformRole);
        $resolved = UserAccountRoleForm::resolvePlatformRole($platformRole);

        $this->pendingPlatformRole = $resolved['spatie'];
        $data['role_type'] = $resolved['role_type'];
        unset($data['platform_role']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if (($this->pendingPlatformRole ?? '') !== '') {
            $this->record->syncRoles([$this->pendingPlatformRole]);
        } else {
            $this->record->syncRoles(['trainee']);
            if ($this->record->role_type !== 'beneficiary') {
                $this->record->update(['role_type' => 'beneficiary']);
            }
        }

        if ($this->record->hasRole('volunteer')) {
            VolunteerTeam::ensureMember($this->record);
        }
    }
}
