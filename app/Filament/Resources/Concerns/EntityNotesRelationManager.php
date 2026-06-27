<?php

namespace App\Filament\Resources\Concerns;

use App\Models\EntityNote;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EntityNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'entityNotes';

    protected static ?string $title = 'ملاحظات';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('update', $ownerRecord) ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('body')
                ->label('نص الملاحظة')
                ->required()
                ->rows(4)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('creator.name')
                    ->label('منشئ الملاحظة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('body')
                    ->label('الملاحظة')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('j F Y — H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('إضافة ملاحظة')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('لا توجد ملاحظات')
            ->emptyStateDescription('أضف ملاحظة داخلية لفريق العمل حول هذا السجل.');
    }
}
