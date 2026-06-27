<?php

namespace App\Filament\Resources;

use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacyRequestType;
use App\Filament\Resources\PrivacyRequestResource\Pages;
use App\Models\PrivacyRequest;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PrivacyRequestResource extends Resource
{
    protected static ?string $model = PrivacyRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'الحوكمة';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'طلبات الخصوصية';

    protected static ?string $modelLabel = 'طلب خصوصية';

    protected static ?string $pluralModelLabel = 'طلبات الخصوصية';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('privacy_requests.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('uuid')
                    ->label('المرجع')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('user.name')
                    ->label('المستخدم'),
                TextColumn::make('request_type')
                    ->label('النوع')
                    ->formatStateUsing(fn (PrivacyRequestType $state): string => $state->label()),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (PrivacyRequestStatus $state): string => $state->label()),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d H:i'),
                TextColumn::make('due_at')
                    ->label('المهلة')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—'),
                TextColumn::make('assignee.name')
                    ->label('المعيَّن')
                    ->placeholder('—'),
                TextColumn::make('overdue')
                    ->label('متأخر')
                    ->getStateUsing(function (PrivacyRequest $record): string {
                        if ($record->due_at === null || $record->status->isTerminal()) {
                            return '—';
                        }

                        return $record->due_at->isPast() ? 'نعم' : '—';
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrivacyRequests::route('/'),
            'view' => Pages\ViewPrivacyRequest::route('/{record}'),
        ];
    }
}
