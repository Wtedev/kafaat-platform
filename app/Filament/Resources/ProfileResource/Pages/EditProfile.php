<?php

namespace App\Filament\Resources\ProfileResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Resources\ProfileResource;
use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Model;

class EditProfile extends BaseEditRecord
{
    protected static string $resource = ProfileResource::class;

    protected function getRecordToolbarActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->can('delete', $this->getRecord()) ?? false),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $record->loadMissing('user');

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = auth()->user();
        if ($user?->can('roles.view')) {
            return parent::handleRecordUpdate($record, $data);
        }

        if ($user?->can('edit_profile_badges')) {
            $record->update([
                'membership_badges' => $data['membership_badges'] ?? null,
                'iconic_skill' => $data['iconic_skill'] ?? null,
                'iconic_skill_style' => $data['iconic_skill_style'] ?? null,
            ]);

            return $record->refresh();
        }

        abort(403);
    }
}
