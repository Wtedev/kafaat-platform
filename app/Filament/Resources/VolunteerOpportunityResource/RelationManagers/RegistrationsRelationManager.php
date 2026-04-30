<?php

namespace App\Filament\Resources\VolunteerOpportunityResource\RelationManagers;

use App\Enums\RegistrationStatus;
use App\Exceptions\OpportunityCapacityExceededException;
use App\Models\VolunteerRegistration;
use App\Services\VolunteerRegistrationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $title = 'التسجيلات';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->relationship('user', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->label('المتطوع'),

            Select::make('status')
                ->label('الحالة')
                ->options(RegistrationStatus::class)
                ->required(),

            Textarea::make('rejected_reason')
                ->rows(3)
                ->label('سبب الرفض'),
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

                TextColumn::make('user.email')
                    ->searchable()
                    ->label('البريد الإلكتروني')
                    ->toggleable(),

                BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'warning' => RegistrationStatus::Pending->value,
                        'success' => RegistrationStatus::Approved->value,
                        'danger'  => RegistrationStatus::Rejected->value,
                        'gray'    => RegistrationStatus::Cancelled->value,
                    ]),

                TextColumn::make('approvedBy.name')
                    ->label('اعتمد بواسطة')
                    ->toggleable(),

                TextColumn::make('approved_at')
                    ->label('تاريخ الموافقة')
                    ->dateTime()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(RegistrationStatus::class),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('approve')
                    ->label('موافقة')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (VolunteerRegistration $record): bool => $record->status === RegistrationStatus::Pending)
                    ->action(function (VolunteerRegistration $record): void {
                        try {
                            app(VolunteerRegistrationService::class)->approve($record, auth()->user());
                            Notification::make()->title('تمت الموافقة على التسجيل')->success()->send();
                        } catch (OpportunityCapacityExceededException) {
                            Notification::make()->title('الفرصة بلغت طاقتها القصوى')->danger()->send();
                        }
                    }),

                Action::make('reject')
                    ->label('رفض')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('rejected_reason')
                            ->label('سبب الرفض (اختياري)')
                            ->rows(3),
                    ])
                    ->visible(fn (VolunteerRegistration $record): bool => $record->status === RegistrationStatus::Pending)
                    ->action(function (VolunteerRegistration $record, array $data): void {
                        app(VolunteerRegistrationService::class)->reject(
                            $record,
                            $data['rejected_reason'] ?? null
                        );
                        Notification::make()->title('تم رفض التسجيل')->warning()->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
