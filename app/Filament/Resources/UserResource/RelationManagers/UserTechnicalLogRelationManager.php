<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Support\UserTechnicalLogService;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class UserTechnicalLogRelationManager extends RelationManager
{
    protected static string $relationship = 'inboxNotifications';

    protected static ?string $title = 'سجل المستفيد التقني';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $viewer = auth()->user();

        if ($viewer === null) {
            return false;
        }

        if (! $viewer->can('manage_roles') && ! $viewer->hasRole('technical_admin')) {
            return false;
        }

        return $viewer->can('users.view');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->queryStringIdentifier(Str::lcfirst(class_basename(static::class)))
            ->records(fn (): Collection => UserTechnicalLogService::tableRecords($this->getOwnerRecord()))
            ->heading($this->getTableHeading() ?? static::getTitle($this->getOwnerRecord(), $this->getPageClass()));
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('التاريخ والوقت')
                    ->dateTime('j F Y — H:i')
                    ->sortable(),

                TextColumn::make('category')
                    ->label('التصنيف')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'الحساب' => 'gray',
                        'الجلسة' => 'info',
                        'الإعدادات' => 'warning',
                        'الملف الشخصي' => 'primary',
                        'صفحة الكفاءات' => 'primary',
                        'من الإدارة' => 'danger',
                        'التسجيل' => 'success',
                        'الحضور' => 'success',
                        'الشهادات' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('title')
                    ->label('الحدث')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('detail')
                    ->label('التفاصيل')
                    ->wrap()
                    ->searchable(),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('لا توجد أحداث مسجّلة')
            ->emptyStateDescription('ستظهر هنا أنشطة المستفيد: الحساب، التسجيلات، صفحة الكفاءات، الحضور، وغيرها.');
    }
}
