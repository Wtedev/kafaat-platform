<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\RoleResource\Pages;
use App\Services\Rbac\RbacCatalog;
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

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() === true && static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label_ar')
                    ->label('الدور')
                    ->getStateUsing(fn (Role $record): string => RbacCatalog::roleArabicLabel($record->name))
                    ->searchable(query: function ($query, string $search): void {
                        $query->where('name', 'like', "%{$search}%");
                    })
                    ->sortable(query: function ($query, string $direction): void {
                        $query->orderBy('name', $direction);
                    }),

                TextColumn::make('permissions_count')
                    ->label('عدد الصلاحيات')
                    ->counts('permissions')
                    ->sortable(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
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
