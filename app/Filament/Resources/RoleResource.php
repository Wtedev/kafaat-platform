<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\RoleResource\Pages;
use App\Services\Rbac\RbacCatalog;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    use RegistersNavigationByPermission;

    protected static ?string $model = Role::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة الوصول';

    protected static ?string $navigationLabel = 'الأدوار';

    protected static ?string $modelLabel = 'دور';

    protected static ?string $pluralModelLabel = 'الأدوار';

    protected static ?int $navigationSort = 2;

    protected static function requiredNavigationPermissions(): array
    {
        return ['roles.view'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الدور')
                ->schema([
                    TextInput::make('name')
                        ->label('المعرّف (بالإنجليزية)')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->helperText('يُستخدم داخل النظام وقاعدة البيانات؛ لا تغيّر أسماء الأدوار الأساسية بعد الإنتاج دون ترحيل.'),

                    TextInput::make('guard_name')
                        ->label('اسم الحارس')
                        ->default(RbacCatalog::GUARD_WEB)
                        ->required()
                        ->maxLength(255),

                    Select::make('permissions')
                        ->label('الصلاحيات')
                        ->multiple()
                        ->relationship('permissions', 'name')
                        ->getOptionLabelFromRecordUsing(fn ($record) => RbacCatalog::permissionArabicLabel($record->name))
                        ->preload()
                        ->searchable()
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('المعرّف')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('label_ar')
                    ->label('الاسم المعروض')
                    ->getStateUsing(fn (Role $record): string => RbacCatalog::roleArabicLabel($record->name)),

                TextColumn::make('permissions_count')
                    ->label('عدد الصلاحيات')
                    ->counts('permissions')
                    ->sortable(),

                TextColumn::make('guard_name')
                    ->label('الحارس')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('roles.create') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('roles.update') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('roles.delete') ?? false;
    }
}
