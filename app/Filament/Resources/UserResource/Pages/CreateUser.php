<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\Pages\BaseCreateRecord;
use App\Filament\Resources\UserResource;
use App\Support\UserAccountRoleForm;
use Illuminate\Validation\ValidationException;

class CreateUser extends BaseCreateRecord
{
    protected static string $resource = UserResource::class;

    protected ?string $pendingPlatformRole = null;

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

        if (! $this->record->hasVerifiedEmail()) {
            $this->record->sendEmailVerificationNotification();
        }
    }
}
