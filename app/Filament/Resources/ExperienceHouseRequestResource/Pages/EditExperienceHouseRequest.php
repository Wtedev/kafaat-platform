<?php

namespace App\Filament\Resources\ExperienceHouseRequestResource\Pages;

use App\Filament\Resources\ExperienceHouseRequestResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Models\ExperienceHouseRequest;
use App\Services\Documents\PrivateDocumentsStorage;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditExperienceHouseRequest extends BaseEditRecord
{
    protected static string $resource = ExperienceHouseRequestResource::class;

    public function getTitle(): string
    {
        /** @var ExperienceHouseRequest $record */
        $record = $this->getRecord();

        return $record->reference_no.' — '.$record->organization_name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadAttachment')
                ->label('تحميل المرفق')
                ->icon('heroicon-o-paper-clip')
                ->color('gray')
                ->visible(fn (): bool => filled($this->getRecord()->attachment_path))
                ->action(function (): ?StreamedResponse {
                    /** @var ExperienceHouseRequest $record */
                    $record = $this->getRecord();
                    $disk = PrivateDocumentsStorage::disk();

                    if (! $disk->exists($record->attachment_path)) {
                        return null;
                    }

                    return $disk->download($record->attachment_path, $record->attachment_name ?? basename($record->attachment_path));
                }),
        ];
    }

    protected function getRecordToolbarActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => ExperienceHouseRequestResource::canDelete($this->getRecord())),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return static::getResource()::getUrl('index');
    }
}
