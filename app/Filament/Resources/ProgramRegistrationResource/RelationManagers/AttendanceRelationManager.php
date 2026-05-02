<?php

namespace App\Filament\Resources\ProgramRegistrationResource\RelationManagers;

use App\Enums\AttendanceStatus;
use App\Models\ProgramAttendance;
use App\Models\ProgramRegistration;
use App\Services\ProgramAttendanceService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AttendanceRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceRecords';

    protected static ?string $title = 'سجل الحضور اليومي';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($ownerRecord instanceof ProgramRegistration) {
            $ownerRecord->loadMissing('trainingProgram');

            return $ownerRecord->trainingProgram !== null
                && $user->can('viewOperational', $ownerRecord->trainingProgram);
        }

        return parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('training_date')
                ->label('تاريخ الجلسة')
                ->required()
                ->disabled(fn (string $operation): bool => $operation === 'edit'),

            Select::make('status')
                ->label('الحالة')
                ->options(AttendanceStatus::class)
                ->required()
                ->default(AttendanceStatus::Absent),

            Textarea::make('notes')
                ->label('ملاحظات')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        /** @var ProgramRegistration $registration */
        $registration = $this->getOwnerRecord();
        $summary = app(ProgramAttendanceService::class)->getSummary($registration);

        return $table
            ->description(
                sprintf(
                    'الجلسات: %d | حاضر: %d | غائب: %d | معذور: %d',
                    $summary['total'],
                    $summary['present'],
                    $summary['absent'],
                    $summary['excused'],
                )
            )
            ->columns([
                TextColumn::make('training_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('day_name')
                    ->label('اليوم')
                    ->getStateUsing(fn (ProgramAttendance $record): string => match ($record->training_date?->dayOfWeek) {
                        0 => 'الأحد',
                        1 => 'الاثنين',
                        2 => 'الثلاثاء',
                        3 => 'الأربعاء',
                        4 => 'الخميس',
                        5 => 'الجمعة',
                        6 => 'السبت',
                        default => '—',
                    }),

                BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'success' => AttendanceStatus::Present->value,
                        'danger' => AttendanceStatus::Absent->value,
                        'warning' => AttendanceStatus::Excused->value,
                    ]),

                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(60)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(AttendanceStatus::class),
            ])
            ->headerActions([
                Action::make('generateSessions')
                    ->label('توليد الجلسات التدريبية')
                    ->icon('heroicon-o-calendar-days')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('توليد الجلسات التدريبية')
                    ->modalDescription(
                        'سيتم إنشاء سجل حضور (غائب) لكل يوم دراسة متوقع بناءً على الجدول الأسبوعي للبرنامج. '
                        .'السجلات الموجودة لن تُحذف أو تُعدَّل.'
                    )
                    ->modalSubmitActionLabel('نعم، توليد')
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->action(function (RelationManager $livewire): void {
                        /** @var ProgramRegistration $reg */
                        $reg = $livewire->getOwnerRecord();
                        $count = app(ProgramAttendanceService::class)->generateSessions($reg);

                        if ($count === 0) {
                            Notification::make()
                                ->title('لم تُنشأ جلسات جديدة')
                                ->body('تأكد من تحديد أيام الدراسة وتواريخ بداية ونهاية البرنامج.')
                                ->warning()
                                ->send();
                        } else {
                            Notification::make()
                                ->title("تم توليد {$count} جلسة تدريبية جديدة")
                                ->success()
                                ->send();
                        }
                    }),

                CreateAction::make()
                    ->label('إضافة يوم يدوياً')
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false),
            ])
            ->actions([
                EditAction::make()
                    ->label('تعديل الحالة')
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false),
                DeleteAction::make()
                    ->label('حذف')
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false),
                ]),
            ])
            ->defaultSort('training_date', 'asc');
    }
}
