<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\VolunteerOpportunityResource;
use App\Filament\Support\RegistrationStatusDisplay;
use App\Models\VolunteerRegistration;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserVolunteerRegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'volunteerRegistrations';

    protected static ?string $title = 'فرص المستفيد التطوعية';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('users.view') ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('opportunity')->latest('created_at'))
            ->columns([
                TextColumn::make('opportunity.title')
                    ->label('الفرصة التطوعية')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->url(fn (VolunteerRegistration $record): ?string => $record->opportunity !== null
                        ? VolunteerOpportunityResource::getUrl('view', ['record' => $record->opportunity])
                        : null),

                BadgeColumn::make('status')
                    ->label('الحالة')
                    ->formatStateUsing(fn ($state): string => RegistrationStatusDisplay::beneficiaryLabel($state))
                    ->color(fn ($state): string => RegistrationStatusDisplay::beneficiaryColor($state))
                    ->sortable(),

                TextColumn::make('approved_hours')
                    ->label('ساعات معتمدة')
                    ->getStateUsing(fn (VolunteerRegistration $record): string => number_format($record->getApprovedHours(), 1)),

                TextColumn::make('created_at')
                    ->label('تاريخ التسجيل')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('approved_at')
                    ->label('تاريخ القبول')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('rejected_reason')
                    ->label('سبب الرفض')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
