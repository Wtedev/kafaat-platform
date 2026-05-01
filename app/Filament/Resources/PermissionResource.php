<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\PermissionResource\Pages;
use App\Services\Rbac\RbacCatalog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;

class PermissionResource extends Resource
{
    use RegistersNavigationByPermission;

    protected static ?string $model = Permission::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة الوصول';

    protected static ?string $navigationLabel = 'الصلاحيات';

    protected static ?string $modelLabel = 'صلاحية';

    protected static ?string $pluralModelLabel = 'الصلاحيات';

    protected static ?int $navigationSort = 3;

    protected static function requiredNavigationPermissions(): array
    {
        return ['roles.view'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
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
                    ->label('الوصف')
                    ->getStateUsing(fn (Permission $record): string => RbacCatalog::permissionArabicLabel($record->name)),

                TextColumn::make('guard_name')
                    ->label('الحارس')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermissions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
