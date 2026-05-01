<?php

namespace App\Filament\Resources\LearningPathResource\RelationManagers;

use App\Enums\CourseStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CoursesRelationManager extends RelationManager
{
    protected static string $relationship = 'courses';

    protected static ?string $title = 'المقررات';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('العنوان')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->required()
                ->label('الترتيب'),

            TextInput::make('duration_minutes')
                ->numeric()
                ->minValue(1)
                ->label('المدة (دقائق)')
                ->nullable(),

            Select::make('status')
                ->label('الحالة')
                ->options(CourseStatus::class)
                ->required()
                ->default(CourseStatus::Draft->value),

            Toggle::make('is_required')
                ->label('إلزامي')
                ->default(true)
                ->helperText('المقررات الإلزامية تُحسب في نسبة الإكمال'),

            TextInput::make('video_url')
                ->label('رابط الفيديو')
                ->url()
                ->nullable()
                ->columnSpanFull(),

            Textarea::make('description')
                ->label('الوصف')
                ->rows(3)
                ->columnSpanFull(),

            RichEditor::make('content')
                ->label('المحتوى')
                ->nullable()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'warning' => CourseStatus::Draft->value,
                        'success' => CourseStatus::Published->value,
                        'gray' => CourseStatus::Hidden->value,
                    ]),

                IconColumn::make('is_required')
                    ->label('إلزامي')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus-circle'),

                TextColumn::make('duration_minutes')
                    ->label('المدة (د)')
                    ->suffix(' د')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(CourseStatus::class),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
