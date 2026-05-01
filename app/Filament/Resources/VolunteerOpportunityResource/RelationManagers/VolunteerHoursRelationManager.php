<?php

namespace App\Filament\Resources\VolunteerOpportunityResource\RelationManagers;

use App\Enums\VolunteerHoursStatus;
use App\Models\VolunteerHour;
use App\Services\VolunteerHoursService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VolunteerHoursRelationManager extends RelationManager
{
    protected static string $relationship = 'volunteerHours';

    protected static ?string $title = 'ساعات التطوع';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('hours')
                ->label('الساعات')
                ->numeric()
                ->minValue(0.5)
                ->required()
                ->suffix('ساعة'),

            Textarea::make('notes')
                ->label('ملاحظات')
                ->rows(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable()
                    ->sortable()
                    ->label('المتطوع'),

                TextColumn::make('hours')
                    ->label('الساعات')
                    ->suffix(' ساعة')
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'warning' => VolunteerHoursStatus::Pending->value,
                        'success' => VolunteerHoursStatus::Approved->value,
                        'danger' => VolunteerHoursStatus::Rejected->value,
                    ]),

                TextColumn::make('approvedBy.name')
                    ->label('اعتمد بواسطة')
                    ->toggleable(),

                TextColumn::make('approved_at')
                    ->label('تاريخ الموافقة')
                    ->dateTime()
                    ->toggleable(),

                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(VolunteerHoursStatus::class),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),

                Action::make('approve')
                    ->label('موافقة على الساعات')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (VolunteerHour $record): bool => $record->status === VolunteerHoursStatus::Pending)
                    ->action(function (VolunteerHour $record): void {
                        app(VolunteerHoursService::class)->approve($record, auth()->user());
                        Notification::make()->title('تمت الموافقة على الساعات')->success()->send();
                    }),

                Action::make('reject')
                    ->label('رفض الساعات')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('notes')
                            ->label('ملاحظات (اختياري)')
                            ->rows(3),
                    ])
                    ->visible(fn (VolunteerHour $record): bool => $record->status === VolunteerHoursStatus::Pending)
                    ->action(function (VolunteerHour $record, array $data): void {
                        app(VolunteerHoursService::class)->reject(
                            $record,
                            $data['notes'] ?? null
                        );
                        Notification::make()->title('تم رفض الساعات')->warning()->send();
                    }),

                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
