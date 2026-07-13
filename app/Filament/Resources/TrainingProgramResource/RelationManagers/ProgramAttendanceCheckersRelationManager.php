<?php

namespace App\Filament\Resources\TrainingProgramResource\RelationManagers;

use App\Enums\ProgramDeliveryMode;
use App\Models\ProgramAttendanceChecker;
use App\Models\TrainingProgram;
use App\Services\ProgramAttendanceCheckerInviteService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ProgramAttendanceCheckersRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceCheckers';

    protected static ?string $title = 'عضوات التحضير';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if ($user === null || ! ($ownerRecord instanceof TrainingProgram)) {
            return false;
        }

        if ($ownerRecord->delivery_mode !== ProgramDeliveryMode::InPerson) {
            return false;
        }

        return $user->can('viewOperational', $ownerRecord);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('الاسم')
                ->required()
                ->maxLength(120),

            TextInput::make('email')
                ->label('البريد الإلكتروني')
                ->email()
                ->required()
                ->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('البريد')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('نشطة')
                    ->boolean(),

                TextColumn::make('verified_at')
                    ->label('آخر تحقق')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('تاريخ الدعوة')
                    ->dateTime('Y/m/d')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('دعوة متحضّرة')
                    ->icon('heroicon-o-user-plus')
                    ->modalHeading('دعوة متحضّرة للتحضير')
                    ->modalSubmitActionLabel('إرسال الدعوة')
                    ->authorize(fn (): bool => auth()->user()?->can('viewOperational', $this->getOwnerRecord()) ?? false)
                    ->using(function (array $data): ProgramAttendanceChecker {
                        /** @var TrainingProgram $program */
                        $program = $this->getOwnerRecord();

                        try {
                            return app(ProgramAttendanceCheckerInviteService::class)->invite(
                                $program,
                                (string) $data['name'],
                                (string) $data['email'],
                            );
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('تعذّرت الدعوة')
                                ->body(collect($exception->errors())->flatten()->first() ?? 'حدث خطأ.')
                                ->danger()
                                ->send();

                            throw $exception;
                        }
                    })
                    ->successNotificationTitle('تم إرسال رمز الدخول إلى البريد'),
            ])
            ->actions([
                Action::make('resendCode')
                    ->label('إعادة إرسال الرمز')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => auth()->user()?->can('viewOperational', $this->getOwnerRecord()) ?? false)
                    ->visible(fn (ProgramAttendanceChecker $record): bool => $record->is_active)
                    ->action(function (ProgramAttendanceChecker $record): void {
                        try {
                            app(ProgramAttendanceCheckerInviteService::class)->sendCode($record);
                            Notification::make()->title('تم إرسال رمز جديد')->success()->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('تعذّر الإرسال')
                                ->body(collect($exception->errors())->flatten()->first() ?? 'حدث خطأ.')
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('toggleActive')
                    ->label(fn (ProgramAttendanceChecker $record): string => $record->is_active ? 'تعطيل' : 'تفعيل')
                    ->icon(fn (ProgramAttendanceChecker $record): string => $record->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                    ->color(fn (ProgramAttendanceChecker $record): string => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => auth()->user()?->can('viewOperational', $this->getOwnerRecord()) ?? false)
                    ->action(function (ProgramAttendanceChecker $record): void {
                        $record->update(['is_active' => ! $record->is_active]);
                        Notification::make()
                            ->title($record->is_active ? 'تم تفعيل العضوية' : 'تم تعطيل العضوية')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('لا توجد عضوات تحضير بعد')
            ->emptyStateDescription('ادعُ متطوعة بالاسم والبريد لإرسال رمز دخول بوابة التحضير.');
    }
}
