<?php

namespace App\Filament\Resources\InvestmentDecisionYearResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'قرارات السنة';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('content')
                ->label('نص القرار')
                ->required()
                ->rows(4)
                ->maxLength(2000)
                ->columnSpanFull(),

            TextInput::make('sort_order')
                ->label('الترتيب')
                ->numeric()
                ->default(0)
                ->required()
                ->minValue(0),

            Toggle::make('is_active')
                ->label('منشور')
                ->default(true)
                ->inline(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->alignCenter()
                    ->width('3rem'),

                TextColumn::make('content')
                    ->label('نص القرار')
                    ->searchable()
                    ->wrap()
                    ->limit(120),

                IconColumn::make('is_active')
                    ->label('منشور')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make()->color('gray'),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
