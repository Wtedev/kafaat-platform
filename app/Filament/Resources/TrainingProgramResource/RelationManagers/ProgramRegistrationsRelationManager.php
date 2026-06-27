<?php

namespace App\Filament\Resources\TrainingProgramResource\RelationManagers;

use App\Enums\RegistrationStatus;
use App\Exceptions\ProgramCapacityExceededException;
use App\Filament\Support\RegistrationFilamentTableSupport;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Services\ProgramRegistrationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProgramRegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $title = 'المسجلين في البرنامج';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($ownerRecord instanceof TrainingProgram) {
            return $user->can('viewOperational', $ownerRecord);
        }

        return parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return RegistrationFilamentTableSupport::configureBeneficiaryRowNavigation($table)
            ->columns([
                RegistrationFilamentTableSupport::beneficiaryNameColumn(),
                RegistrationFilamentTableSupport::acceptanceStatusColumn(),
                RegistrationFilamentTableSupport::certificateEligibilityColumn(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('حالة القبول')
                    ->options(RegistrationStatus::class),
            ])
            ->actions([
                Action::make('approve')
                    ->label('قبول')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد قبول الطلب')
                    ->modalDescription('هل تريد قبول طلب التسجيل في هذا البرنامج؟')
                    ->modalSubmitActionLabel('نعم، قبول')
                    ->visible(fn (ProgramRegistration $record): bool => $record->status === RegistrationStatus::Pending)
                    ->authorize('approve')
                    ->action(function (ProgramRegistration $record): void {
                        try {
                            app(ProgramRegistrationService::class)->approve($record, auth()->user());
                            Notification::make()->title('تمت الموافقة على التسجيل')->success()->send();
                        } catch (ProgramCapacityExceededException) {
                            Notification::make()->title('البرنامج بلغ طاقته القصوى')->danger()->send();
                        }
                    }),

                Action::make('reject')
                    ->label('رفض')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('رفض طلب التسجيل')
                    ->modalSubmitActionLabel('نعم، رفض')
                    ->form([
                        Textarea::make('rejected_reason')
                            ->label('سبب الرفض (اختياري)')
                            ->placeholder('اكتب سبب الرفض لإشعار المستفيد...')
                            ->rows(3),
                    ])
                    ->visible(fn (ProgramRegistration $record): bool => $record->status === RegistrationStatus::Pending)
                    ->authorize('reject')
                    ->action(function (ProgramRegistration $record, array $data): void {
                        app(ProgramRegistrationService::class)->reject($record, $data['rejected_reason'] ?? null);
                        Notification::make()->title('تم رفض التسجيل')->warning()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
