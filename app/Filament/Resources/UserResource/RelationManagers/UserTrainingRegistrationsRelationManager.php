<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Support\UserTrainingEnrollmentSupport;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class UserTrainingRegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'learningPathRegistrations';

    protected static ?string $title = 'تسجيلات المستفيد';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('users.view') ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->queryStringIdentifier(Str::lcfirst(class_basename(static::class)))
            ->records(fn (): Collection => UserTrainingEnrollmentSupport::recordsFor($this->getOwnerRecord()))
            ->heading($this->getTableHeading() ?? static::getTitle($this->getOwnerRecord(), $this->getPageClass()));
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->color(fn (array $record): string => str_contains((string) ($record['type'] ?? ''), 'مسار') ? 'info' : 'primary'),

                TextColumn::make('title')
                    ->label('الاسم')
                    ->searchable()
                    ->wrap()
                    ->url(fn (array $record): ?string => $record['url'] ?? null),

                TextColumn::make('context')
                    ->label('السياق')
                    ->placeholder('—')
                    ->toggleable(),

                BadgeColumn::make('status_label')
                    ->label('الحالة')
                    ->color(fn (array $record): string => (string) ($record['status_color'] ?? 'gray')),

                TextColumn::make('created_at')
                    ->label('تاريخ التسجيل')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('approved_at')
                    ->label('تاريخ القبول')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('completed_at')
                    ->label('تاريخ الإكمال')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
