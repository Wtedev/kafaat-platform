<?php

namespace App\Filament\Resources\TrainingProgramResource\RelationManagers;

use App\Models\ProgramAttendanceLink;
use App\Models\TrainingProgram;
use App\Services\Attendance\ProgramAttendanceLinkService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class ProgramAttendanceLinksRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceLinks';

    protected static ?string $title = 'روابط التحضير';

    protected static ?string $modelLabel = 'رابط تحضير';

    protected static ?string $pluralModelLabel = 'روابط التحضير';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user !== null
            && $ownerRecord instanceof TrainingProgram
            && ($user->can('view', $ownerRecord) || $user->can('update', $ownerRecord));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('اسم الرابط')
                ->required()
                ->maxLength(160),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordAction('attendees')
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم'),
                TextColumn::make('marks_count')
                    ->counts('marks')
                    ->label('عدد الحاضرين'),
                TextColumn::make('cancelled_at')
                    ->label('الحالة')
                    ->formatStateUsing(fn ($state): string => filled($state) ? 'ملغى' : 'نشط')
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('رابط تحضير')
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false),
            ])
            ->actions([
                Action::make('copy')
                    ->label('نسخ الرابط')
                    ->modalHeading('نسخ الرابط')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->modalContent(fn (ProgramAttendanceLink $record): HtmlString => new HtmlString(
                        Blade::render(
                            <<<'BLADE'
                            <div x-data="{ copied: false }">
                                <input type="text" readonly value="{{ $url }}" class="w-full rounded-lg border px-3 py-2 text-sm" x-ref="link" onclick="this.select()">
                                <button type="button" class="mt-3 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white" x-on:click="navigator.clipboard.writeText($refs.link.value); copied = true">
                                    <span x-text="copied ? 'تم النسخ' : 'نسخ الرابط'"></span>
                                </button>
                            </div>
                            BLADE,
                            ['url' => $record->publicUrl()],
                        )
                    )),
                Action::make('cancel')
                    ->label('إلغاء الرابط')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ProgramAttendanceLink $record): bool => ! $record->isCancelled())
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->action(fn (ProgramAttendanceLink $record) => app(ProgramAttendanceLinkService::class)->cancel($record)),
                Action::make('attendees')
                    ->label('الحاضرون')
                    ->modalHeading(fn (ProgramAttendanceLink $record): string => $record->name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->modalContent(function (ProgramAttendanceLink $record): HtmlString {
                        $rows = $record->marks()->with('registration.user')->orderBy('attended_at')->get();
                        $html = '<table class="w-full text-sm"><thead><tr><th class="py-2 text-right">الاسم</th><th class="py-2 text-right">وقت التحضير</th><th class="py-2 text-right">المصدر</th></tr></thead><tbody>';
                        foreach ($rows as $mark) {
                            $html .= '<tr><td class="py-2">'.e($mark->registration?->user?->fullName()).'</td><td class="py-2">'.e($mark->attended_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s')).'</td><td class="py-2">'.e($mark->source->label()).'</td></tr>';
                        }
                        $html .= '</tbody></table>';

                        return new HtmlString($html);
                    }),
            ])
            ->emptyStateHeading('لا توجد روابط تحضير')
            ->defaultSort('id', 'desc');
    }
}
