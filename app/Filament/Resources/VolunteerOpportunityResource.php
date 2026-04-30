<?php

namespace App\Filament\Resources;

use App\Enums\OpportunityStatus;
use App\Filament\Resources\VolunteerOpportunityResource\Pages;
use App\Filament\Resources\VolunteerOpportunityResource\RelationManagers\RegistrationsRelationManager;
use App\Filament\Resources\VolunteerOpportunityResource\RelationManagers\VolunteerHoursRelationManager;
use App\Models\VolunteerOpportunity;
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

class VolunteerOpportunityResource extends Resource
{
    protected static ?string $model = VolunteerOpportunity::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-hand-raised';

    protected static string|\UnitEnum|null $navigationGroup = 'التطوع';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'الفرص التطوعية';

    protected static ?string $modelLabel = 'فرصة تطوعية';

    protected static ?string $pluralModelLabel = 'الفرص التطوعية';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('تفاصيل الفرصة')->columns(2)->schema([
                TextInput::make('title')
                    ->label('العنوان')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('slug')
                    ->label('الرابط المختصر')
                    ->required()
                    ->maxLength(255),

                Select::make('status')
                    ->label('الحالة')
                    ->options(OpportunityStatus::class)
                    ->required()
                    ->default(OpportunityStatus::Draft->value),

                Textarea::make('description')
                    ->label('الوصف')
                    ->rows(4)
                    ->columnSpanFull(),
            ]),

            Section::make('الطاقة والجدول')->columns(2)->schema([
                TextInput::make('capacity')
                    ->label('الطاقة الاستيعابية (فارغاً = غير محدود)')
                    ->numeric()
                    ->minValue(1),

                TextInput::make('hours_expected')
                    ->label('الساعات المطلوبة')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('ساعة'),

                Grid::make(2)->schema([
                    DatePicker::make('start_date')
                        ->label('تاريخ البداية'),

                    DatePicker::make('end_date')
                        ->label('تاريخ الانتهاء')
                        ->afterOrEqual('start_date'),
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
                        'gray'    => OpportunityStatus::Draft->value,
                        'success' => OpportunityStatus::Published->value,
                        'warning' => OpportunityStatus::Archived->value,
                    ])
                    ->sortable(),

                TextColumn::make('capacity')
                    ->label('الطاقة')
                    ->default('غير محدودة'),

                TextColumn::make('registrations_count')
                    ->counts('registrations')
                    ->label('عدد المتطوعين')
                    ->badge()
                    ->color('info'),

                TextColumn::make('hours_expected')
                    ->label('الساعات المطلوبة')
                    ->suffix(' ساعة')
                    ->toggleable(),

                TextColumn::make('start_date')
                    ->label('تاريخ البداية')
                    ->date()
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('تاريخ الانتهاء')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(OpportunityStatus::class),
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

    public static function getRelationManagers(): array
    {
        return [
            RegistrationsRelationManager::class,
            VolunteerHoursRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVolunteerOpportunities::route('/'),
            'create' => Pages\CreateVolunteerOpportunity::route('/create'),
            'view'   => Pages\ViewVolunteerOpportunity::route('/{record}'),
            'edit'   => Pages\EditVolunteerOpportunity::route('/{record}/edit'),
        ];
    }
}
