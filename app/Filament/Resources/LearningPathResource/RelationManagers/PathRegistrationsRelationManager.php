<?php

namespace App\Filament\Resources\LearningPathResource\RelationManagers;

use App\Enums\RegistrationStatus;
use App\Exceptions\PathCapacityExceededException;
use App\Filament\Support\RegistrationFilamentTableSupport;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Services\PathRegistrationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PathRegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $title = 'المسجلون في المسار';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($ownerRecord instanceof LearningPath) {
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
            ->heading('')
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
                    ->modalDescription('هل تريد قبول طلب التسجيل في هذا المسار؟')
                    ->modalSubmitActionLabel('نعم، قبول')
                    ->visible(fn (PathRegistration $record): bool => $record->status === RegistrationStatus::Pending)
                    ->authorize('approve')
                    ->action(function (PathRegistration $record): void {
                        try {
                            app(PathRegistrationService::class)->approve($record, auth()->user());
                            Notification::make()->title('تمت الموافقة على التسجيل')->success()->send();
                        } catch (PathCapacityExceededException) {
                            Notification::make()->title('المسار بلغ طاقته القصوى')->danger()->send();
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
                    ->visible(fn (PathRegistration $record): bool => $record->status === RegistrationStatus::Pending)
                    ->authorize('reject')
                    ->action(function (PathRegistration $record, array $data): void {
                        app(PathRegistrationService::class)->reject($record, $data['rejected_reason'] ?? null);
                        Notification::make()->title('تم رفض التسجيل')->warning()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
