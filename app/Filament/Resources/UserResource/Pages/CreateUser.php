<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Support\UserAccountRoleForm;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected ?string $pendingAssignedRoleForSync = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingAssignedRoleForSync = null;

        $actor = auth()->user();
        if (! ($actor?->can('manage_roles') ?? false)) {
            if (array_key_exists('assigned_role', $data) && filled($data['assigned_role'])) {
                throw ValidationException::withMessages([
                    'data.assigned_role' => 'تعيين الدور متاح لمن يملك صلاحية إدارة الأدوار فقط.',
                ]);
            }

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

    protected function afterCreate(): void
    {
        if (($this->pendingAssignedRoleForSync ?? '') !== '') {
            $this->record->syncRoles([$this->pendingAssignedRoleForSync]);

            return;
        }

        $this->record->syncRoles(['trainee']);
        if ($this->record->role_type !== 'beneficiary') {
            $this->record->update(['role_type' => 'beneficiary']);
        }
    }
}
