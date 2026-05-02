<?php

namespace App\Filament\Actions;

use App\Filament\Resources\Pages\BaseViewRecord;
use App\Models\LearningPath;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\TrainingOwnershipTransferService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

final class TransferTrainingEntityOwnershipAction
{
    public static function make(BaseViewRecord $page): Action
    {
        return Action::make('transferOwnership')
            ->label('نقل الملكية')
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->color('warning')
            ->modalHeading('نقل الملكية')
            ->modalDescription(
                'يُحدَّث المسؤول الحالي (المالك) فقط. سجل المنشئ (created_by) يبقى كما هو. '
                .'لا يُزال المالك السابق تلقائياً من قائمة المحررين.'
            )
            ->modalSubmitActionLabel('تأكيد النقل')
            ->visible(fn (): bool => auth()->check() && auth()->user()->can('transferOwnership', $page->getRecord()))
            ->authorize(fn (): bool => auth()->user()->can('transferOwnership', $page->getRecord()))
            ->form([
                Select::make('new_owner_id')
                    ->label('المسؤول الجديد')
                    ->options(function (): array {
                        return User::query()
                            ->eligibleForTrainingOwnershipTransfer()
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (User $user): array => [
                                $user->getKey() => $user->name.' ('.$user->email.')',
                            ])
                            ->all();
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('موظفون وإداريون نشطون فقط.'),
            ])
            ->action(function (array $data) use ($page): void {
                $actor = auth()->user();
                abort_if($actor === null, 403);

                $record = $page->getRecord();
                $service = app(TrainingOwnershipTransferService::class);

                if ($record instanceof TrainingProgram) {
                    $service->transferProgramOwnership($record, (int) $data['new_owner_id'], $actor);
                } elseif ($record instanceof LearningPath) {
                    $service->transferPathOwnership($record, (int) $data['new_owner_id'], $actor);
                } else {
                    throw new \InvalidArgumentException('Unsupported record for ownership transfer.');
                }

                Notification::make()
                    ->title('تم نقل الملكية')
                    ->body('تم تعيين المسؤول الحالي بنجاح.')
                    ->success()
                    ->send();
            });
    }
}
