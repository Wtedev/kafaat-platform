<?php

namespace App\Filament\Resources\PrivacyPolicyVersionResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AcknowledgementsRelationManager extends RelationManager
{
    protected static string $relationship = 'acknowledgements';

    protected static ?string $title = 'إقرارات الاطلاع';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('المستخدم')->searchable(),
                TextColumn::make('user.email')->label('البريد')->searchable(),
                TextColumn::make('source')->label('المصدر')->badge(),
                TextColumn::make('acknowledged_at')->label('تاريخ الإقرار')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('acknowledgement_text_snapshot')->label('نص الإقرار')->limit(40)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('acknowledged_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('privacy_policy.view') ?? false;
    }
}
