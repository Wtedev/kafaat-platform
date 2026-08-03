<?php

namespace App\Filament\Resources\TrainingProgramResource\RelationManagers;

use App\Models\ProgramAttendanceChecker;
use App\Models\TrainingProgram;
use App\Services\ProgramAttendanceCheckerAccessService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class ProgramAttendanceCheckersRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceCheckers';

    protected static ?string $title = 'مسؤولو التحضير';

    public ?string $revealedAccessUrl = null;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if ($user === null || ! ($ownerRecord instanceof TrainingProgram)) {
            return false;
        }

        if (! $ownerRecord->prepDays()->exists()) {
            return false;
        }

        return $user->can('viewOperational', $ownerRecord);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('اسم مسؤول التحضير')
                ->required()
                ->maxLength(120),
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

                TextColumn::make('is_active')
                    ->label('الحالة')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'نشط' : 'معطل')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y/m/d')
                    ->sortable(),

                TextColumn::make('last_used_at')
                    ->label('آخر استخدام')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('إضافة مسؤول تحضير')
                    ->icon('heroicon-o-user-plus')
                    ->modalHeading('إضافة مسؤول تحضير')
                    ->modalSubmitActionLabel('إنشاء الرابط')
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->createAnother(false)
                    ->using(function (array $data): ProgramAttendanceChecker {
                        /** @var TrainingProgram $program */
                        $program = $this->getOwnerRecord();

                        try {
                            $result = app(ProgramAttendanceCheckerAccessService::class)->create(
                                $program,
                                (string) $data['name'],
                                auth()->user(),
                            );
                            $this->revealedAccessUrl = $result['url'];

                            return $result['checker'];
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('تعذّرت الإضافة')
                                ->body(collect($exception->errors())->flatten()->first() ?? 'حدث خطأ.')
                                ->danger()
                                ->send();

                            throw $exception;
                        }
                    })
                    ->successNotification(null)
                    ->after(function (): void {
                        if (filled($this->revealedAccessUrl)) {
                            $this->mountAction('revealAccessLink');
                        }
                    }),

                Action::make('revealAccessLink')
                    ->label('رابط التحضير')
                    ->visible(fn (): bool => filled($this->revealedAccessUrl))
                    ->modalHeading('رابط التحضير')
                    ->modalDescription('انسخ الرابط الآن. لن يظهر مرة أخرى لأسباب أمنية.')
                    ->modalContent(fn (): HtmlString => new HtmlString(
                        Blade::render(
                            <<<'BLADE'
                            <div class="space-y-3" x-data="{ copied: false }">
                                <input
                                    type="text"
                                    readonly
                                    value="{{ $url }}"
                                    class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm font-mono"
                                    x-ref="link"
                                    onclick="this.select()"
                                />
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white"
                                    x-on:click="navigator.clipboard.writeText($refs.link.value); copied = true; setTimeout(() => copied = false, 2000)"
                                >
                                    <span x-text="copied ? 'تم النسخ' : 'نسخ رابط التحضير'"></span>
                                </button>
                            </div>
                            BLADE,
                            ['url' => $this->revealedAccessUrl],
                        )
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->extraModalFooterActions([])
                    ->action(fn () => null),
            ])
            ->actions([
                Action::make('regenerateLink')
                    ->label(fn (ProgramAttendanceChecker $record): string => $record->hasAccessLink()
                        ? 'إنشاء رابط جديد'
                        : 'إنشاء رابط')
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading(fn (ProgramAttendanceChecker $record): string => $record->hasAccessLink()
                        ? 'إنشاء رابط جديد؟'
                        : 'إنشاء رابط التحضير')
                    ->modalDescription(fn (ProgramAttendanceChecker $record): string => $record->hasAccessLink()
                        ? 'سيُبطَل الرابط السابق وجميع الجلسات المرتبطة به فوراً.'
                        : 'سيُنشأ رابط تحضير آمن لهذا المسؤول.')
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->visible(fn (ProgramAttendanceChecker $record): bool => $record->is_active)
                    ->action(function (ProgramAttendanceChecker $record): void {
                        try {
                            $result = app(ProgramAttendanceCheckerAccessService::class)->regenerateLink(
                                $record,
                                auth()->user(),
                            );
                            $this->revealedAccessUrl = $result['url'];
                            $this->mountAction('revealAccessLink');
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('تعذّر إنشاء الرابط')
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
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->action(function (ProgramAttendanceChecker $record): void {
                        app(ProgramAttendanceCheckerAccessService::class)->setActive(
                            $record,
                            ! $record->is_active,
                            auth()->user(),
                        );
                        Notification::make()
                            ->title($record->fresh()?->is_active ? 'تم تفعيل مسؤول التحضير' : 'تم تعطيل مسؤول التحضير')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('لا يوجد مسؤولو تحضير بعد')
            ->emptyStateDescription('أضف مسؤولاً وأنشئ له رابطاً للوصول إلى بوابة التحضير.');
    }
}
