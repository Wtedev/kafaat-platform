<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\VolunteerTeamResource\Pages;
use App\Filament\Resources\VolunteerTeamResource\RelationManagers\TeamMembersRelationManager;
use App\Filament\Resources\VolunteerTeamResource\RelationManagers\TeamNotificationsRelationManager;
use App\Models\VolunteerTeam;
use App\Support\FilamentAssignmentVisibility;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VolunteerTeamResource extends Resource
{
    use RegistersNavigationByPermission;

    protected static ?string $model = VolunteerTeam::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'التطوع';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'الفرق التطوعية';

    protected static ?string $modelLabel = 'فريق تطوعي';

    protected static ?string $pluralModelLabel = 'الفرق التطوعية';

    protected static function requiredNavigationPermissions(): array
    {
        return ['volunteering.view'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الفريق')->columns(2)->schema([
                TextInput::make('name')
                    ->label('اسم الفريق')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('slug')
                    ->label('المعرّف في الرابط')
                    ->required()
                    ->maxLength(255)
                    ->alphaDash(),

                Select::make('assigned_to')
                    ->label('مسؤول الفريق')
                    ->relationship('assignee', 'name', modifyQueryUsing: fn (Builder $q) => $q->role('volunteering_manager'))
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => FilamentAssignmentVisibility::bypasses(auth()->user()))
                    ->required(fn (): bool => FilamentAssignmentVisibility::bypasses(auth()->user()))
                    ->dehydrated(fn (): bool => FilamentAssignmentVisibility::bypasses(auth()->user()))
                    ->helperText('يحدد مدير التطوع المكلّف بإدارة هذا الفريق.'),

                Toggle::make('is_active')
                    ->label('نشط')
                    ->default(true)
                    ->inline(false),

                Textarea::make('description')
                    ->label('الوصف')
                    ->rows(4)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('assignee.name')
                    ->label('المسؤول')
                    ->toggleable()
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),

                TextColumn::make('members_count')
                    ->counts('members')
                    ->label('عدد الأعضاء')
                    ->badge()
                    ->color('info'),

                TextColumn::make('slug')
                    ->label('المعرّف')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ->modifyQueryUsing(fn (Builder $query) => $query->forFilamentAssignmentAccess(auth()->user()))
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            TeamMembersRelationManager::class,
            TeamNotificationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVolunteerTeams::route('/'),
            'create' => Pages\CreateVolunteerTeam::route('/create'),
            'view' => Pages\ViewVolunteerTeam::route('/{record}'),
            'edit' => Pages\EditVolunteerTeam::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forFilamentAssignmentAccess(auth()->user());
    }
}
