<?php

namespace App\Filament\Resources\Concerns;

use App\Models\EntityNote;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EntityNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'entityNotes';

    protected static ?string $title = 'ملاحظات';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('body')
                ->label('نص الملاحظة')
                ->placeholder('اكتب ملاحظة داخلية لفريق العمل…')
                ->required()
                ->maxLength(5000)
                ->rows(5)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('creator'))
            ->columns([
                TextColumn::make('body')
                    ->label('الملاحظات')
                    ->wrap()
                    ->searchable()
                    ->description(fn (EntityNote $record): string => sprintf(
                        '%s · %s',
                        $record->creator?->name ?? '—',
                        $record->created_at?->timezone(config('app.timezone'))->translatedFormat('j F Y — H:i') ?? '—',
                    )),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('إضافة ملاحظة')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('إضافة ملاحظة')
                    ->modalSubmitActionLabel('حفظ')
                    ->modalCancelActionLabel('إلغاء')
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();

                        return $data;
                    })
                    ->successNotificationTitle('تمت إضافة الملاحظة'),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('لا توجد ملاحظات')
            ->emptyStateDescription('أضف ملاحظة داخلية لفريق العمل حول هذا السجل.')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-ellipsis');
    }
}
