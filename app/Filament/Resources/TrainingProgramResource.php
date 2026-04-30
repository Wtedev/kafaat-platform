<?php

namespace App\Filament\Resources;

use App\Enums\ProgramStatus;
use App\Filament\Resources\TrainingProgramResource\Pages;
use App\Models\TrainingProgram;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TrainingProgramResource extends Resource
{
    protected static ?string $model = TrainingProgram::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|\UnitEnum|null $navigationGroup = 'التدريب';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'البرامج التدريبية';

    protected static ?string $modelLabel = 'برنامج تدريبي';

    protected static ?string $pluralModelLabel = 'البرامج التدريبية';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('تفاصيل البرنامج')->columns(2)->schema([
                TextInput::make('title')
                    ->label('العنوان')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->columnSpanFull(),

                TextInput::make('slug')
                    ->label('الرابط المختصر')
                    ->required()
                    ->maxLength(255),

                Select::make('status')
                    ->label('الحالة')
                    ->options(ProgramStatus::class)
                    ->required()
                    ->default(ProgramStatus::Draft->value),

                Textarea::make('description')
                    ->label('الوصف')
                    ->rows(4)
                    ->columnSpanFull(),
            ]),

            Section::make('الطاقة والمواعيد')->columns(2)->schema([
                TextInput::make('capacity')
                    ->label('الطاقة الاستيعابية (فارغاً = غير محدود)')
                    ->numeric()
                    ->minValue(1),

                Grid::make(2)->schema([
                    DatePicker::make('start_date')
                        ->label('بداية البرنامج'),

                    DatePicker::make('end_date')
                        ->label('نهاية البرنامج')
                        ->afterOrEqual('start_date'),
                ]),

                Grid::make(2)->schema([
                    DatePicker::make('registration_start')
                        ->label('بداية التسجيل'),

                    DatePicker::make('registration_end')
                        ->label('نهاية التسجيل')
                        ->afterOrEqual('registration_start'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'gray'    => ProgramStatus::Draft->value,
                        'success' => ProgramStatus::Published->value,
                        'warning' => ProgramStatus::Archived->value,
                    ])
                    ->sortable(),

                TextColumn::make('capacity')
                    ->label('الطاقة')
                    ->default('غير محدودة')
                    ->sortable(),

                TextColumn::make('registrations_count')
                    ->counts('registrations')
                    ->label('عدد المسجلين')
                    ->badge()
                    ->color('info'),

                TextColumn::make('start_date')
                    ->label('تاريخ البداية')
                    ->date()
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('تاريخ الانتهاء')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('registration_start')
                    ->date()
                    ->label('بداية التسجيل')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('registration_end')
                    ->date()
                    ->label('نهاية التسجيل')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(ProgramStatus::class),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTrainingPrograms::route('/'),
            'create' => Pages\CreateTrainingProgram::route('/create'),
            'view'   => Pages\ViewTrainingProgram::route('/{record}'),
            'edit'   => Pages\EditTrainingProgram::route('/{record}/edit'),
        ];
    }
}
