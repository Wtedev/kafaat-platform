<?php

namespace App\Filament\Resources;

use App\Enums\OpportunityStatus;
use App\Enums\RegistrationStatus;
use App\Exceptions\OpportunityCapacityExceededException;
use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\VolunteerRegistrationResource\Pages;
use App\Models\Certificate;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;
use App\Services\CertificateService;
use App\Services\VolunteerRegistrationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VolunteerRegistrationResource extends Resource
{
    use RegistersNavigationByPermission;

    protected static ?string $model = VolunteerRegistration::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static string|\UnitEnum|null $navigationGroup = 'التطوع';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'المتطوعين';

    protected static ?string $modelLabel = 'تسجيل تطوع';

    protected static ?string $pluralModelLabel = 'المتطوعين';

    protected static function requiredNavigationPermissions(): array
    {
        return ['registrations.view', 'volunteering.view'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('opportunity_id')
                    ->relationship(
                        'opportunity',
                        'title',
                        modifyQueryUsing: fn (Builder $q) => $q->forFilamentAssignmentAccess(auth()->user()),
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('الفرصة التطوعية'),

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
                    ->columnSpanFull()
                    ->label('سبب الرفض'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('المتطوع')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.email')
                    ->label('البريد الإلكتروني')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('opportunity.title')
                    ->label('الفرصة التطوعية')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('opportunity.status')
                    ->label('حالة الفرصة')
                    ->colors([
                        'gray' => OpportunityStatus::Draft->value,
                        'success' => OpportunityStatus::Published->value,
                        'warning' => OpportunityStatus::Archived->value,
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),

                BadgeColumn::make('status')
                    ->label('حالة التسجيل')
                    ->colors([
                        'warning' => RegistrationStatus::Pending->value,
                        'success' => RegistrationStatus::Approved->value,
                        'danger' => RegistrationStatus::Rejected->value,
                        'gray' => RegistrationStatus::Cancelled->value,
                        'info' => RegistrationStatus::Completed->value,
                    ])
                    ->sortable(),

                TextColumn::make('approvedBy.name')
                    ->label('اعتمد بواسطة')
                    ->toggleable(),

                TextColumn::make('approved_at')
                    ->label('تاريخ الموافقة')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('approved_hours')
                    ->label('تقدم الساعات')
                    ->getStateUsing(fn (VolunteerRegistration $record): string => number_format($record->getApprovedHours(), 1).' / '.
                        number_format((float) $record->opportunity?->hours_expected, 1).' ساعة'
                    ),

                TextColumn::make('has_certificate')
                    ->label('شهادة التطوع')
                    ->badge()
                    ->getStateUsing(function (VolunteerRegistration $record): string {
                        return Certificate::query()
                            ->where('user_id', $record->user_id)
                            ->where('certificateable_type', VolunteerOpportunity::class)
                            ->where('certificateable_id', $record->opportunity_id)
                            ->exists() ? 'صدرت ✓' : '—';
                    })
                    ->color(fn (string $state): string => str_contains($state, 'صدرت') ? 'success' : 'gray')
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

                SelectFilter::make('opportunity_id')
                    ->relationship(
                        'opportunity',
                        'title',
                        modifyQueryUsing: fn (Builder $q) => $q->forFilamentAssignmentAccess(auth()->user()),
                    )
                    ->label('الفرصة التطوعية')
                    ->searchable(),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('approve')
                    ->label('قبول الطلب')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد قبول التسجيل')
                    ->modalDescription('هل تريد قبول طلب التطوع في هذه الفرصة؟')
                    ->modalSubmitActionLabel('نعم، قبول')
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
                    ->label('رفض الطلب')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('رفض طلب التطوع')
                    ->modalSubmitActionLabel('نعم، رفض')
                    ->form([
                        Textarea::make('rejected_reason')
                            ->label('سبب الرفض (اختياري)')
                            ->placeholder('اكتب سبب الرفض لإشعار المستفيد...')
                            ->rows(3),
                    ])
                    ->visible(fn (VolunteerRegistration $record): bool => $record->status === RegistrationStatus::Pending)
                    ->action(function (VolunteerRegistration $record, array $data): void {
                        app(VolunteerRegistrationService::class)->reject(
                            $record,
                            auth()->user(),
                            $data['rejected_reason'] ?? null
                        );
                        Notification::make()->title('تم رفض التسجيل')->warning()->send();
                    }),

                Action::make('issueCertificate')
                    ->label('إصدار شهادة تطوع')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->visible(fn (VolunteerRegistration $record): bool => $record->isCompleted())
                    ->requiresConfirmation()
                    ->modalHeading('إصدار شهادة تطوع')
                    ->modalDescription('سيتم إصدار شهادة PDF للمتطوع. تأكد أن جميع الساعات المطلوبة معتمدة قبل المتابعة.')
                    ->modalSubmitActionLabel('نعم، إصدار')
                    ->action(function (VolunteerRegistration $record): void {
                        $record->loadMissing(['user', 'opportunity']);
                        $existing = Certificate::query()
                            ->where('user_id', $record->user_id)
                            ->where('certificateable_type', VolunteerOpportunity::class)
                            ->where('certificateable_id', $record->opportunity_id)
                            ->first();
                        if ($existing !== null) {
                            Notification::make()
                                ->title('الشهادة موجودة مسبقاً')
                                ->body('رقم الشهادة: '.$existing->certificate_number)
                                ->warning()
                                ->send();

                            return;
                        }
                        app(CertificateService::class)->issue($record->user, $record->opportunity, auth()->user());
                        Notification::make()
                            ->title('تم إصدار شهادة التطوع بنجاح')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->forFilamentAssignmentAccess(auth()->user()))
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVolunteerRegistrations::route('/'),
            'view' => Pages\ViewVolunteerRegistration::route('/{record}'),
        ];
    }
}
