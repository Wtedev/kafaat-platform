<?php

namespace App\Filament\Resources\TrainingProgramResource\RelationManagers;

use App\Enums\RegistrationStatus;
use App\Filament\Support\RegistrationFilamentTableSupport;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProgramGradesRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $title = 'الدرجات';

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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ]))
            ->columns([
                RegistrationFilamentTableSupport::beneficiaryNameColumn(),
                RegistrationFilamentTableSupport::scoreColumn(),
            ])
            ->headerActions([
                Action::make('enterGradeForAll')
                    ->label('إدخال الدرجة الكاملة لجميع المسجلين')
                    ->icon('heroicon-o-users')
                    ->color('primary')
                    ->authorize(fn (): bool => auth()->user()?->can('viewOperational', $this->getOwnerRecord()) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('إدخال درجة لجميع المسجلين')
                    ->modalDescription('سيتم تطبيق نفس الدرجة على كل المسجلين المقبولين والمكتملين في هذا البرنامج.')
                    ->modalSubmitActionLabel('نعم، حفظ')
                    ->form([
                        TextInput::make('score')
                            ->label('الدرجة')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('تُحتسب أهلية الشهادة من متوسط الحضور والدرجة (الحد الأدنى 75٪).'),
                    ])
                    ->action(function (array $data): void {
                        /** @var TrainingProgram $program */
                        $program = $this->getOwnerRecord();
                        $updated = $program->registrations()
                            ->whereIn('status', [
                                RegistrationStatus::Approved->value,
                                RegistrationStatus::Completed->value,
                            ])
                            ->update(['score' => (float) $data['score']]);

                        if ($updated > 0) {
                            Notification::make()
                                ->title("تم تحديث درجة {$updated} مستفيد")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('لا يوجد مسجلون مقبولون')
                                ->warning()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Action::make('enterGrade')
                    ->label('إدخال الدرجة')
                    ->icon('heroicon-o-academic-cap')
                    ->color('primary')
                    ->authorize('update')
                    ->fillForm(fn (ProgramRegistration $record): array => [
                        'score' => $record->score,
                    ])
                    ->form([
                        TextInput::make('score')
                            ->label('الدرجة')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('تُحتسب أهلية الشهادة من متوسط الحضور والدرجة (الحد الأدنى 75٪).'),
                    ])
                    ->action(function (ProgramRegistration $record, array $data): void {
                        $record->update([
                            'score' => (float) $data['score'],
                        ]);

                        Notification::make()->title('تم حفظ الدرجة')->success()->send();
                    }),
            ])
            ->defaultSort('user.name');
    }
}
