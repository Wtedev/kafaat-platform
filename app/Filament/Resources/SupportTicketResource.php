<?php

namespace App\Filament\Resources;

use App\Enums\SupportMessageSenderType;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Filament\Resources\SupportTicketResource\Pages;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\SupportUnreadService;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'الأمان والامتثال';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'تذاكر الدعم';

    protected static ?string $modelLabel = 'تذكرة';

    protected static ?string $pluralModelLabel = 'تذاكر الدعم';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && (
            $user->isAdmin()
            || $user->can('support_tickets.view')
            || $user->can('support_tickets.reply')
        );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canView($record): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        // Conversation hub uses View page actions — not classic Edit for replies.
        return false;
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('تفاصيل التذكرة')
                ->columns(2)
                ->schema([
                    TextInput::make('ticket_number')->label('الرقم')->disabled(),
                    TextInput::make('subject')->label('الموضوع')->disabled()->columnSpanFull(),
                    TextInput::make('name')->label('الاسم')->disabled(),
                    TextInput::make('email')->label('البريد')->disabled(),
                    Select::make('category')->label('التصنيف')->options(SupportTicketCategory::options())->disabled(),
                    Select::make('status')->label('الحالة')->options(SupportTicketStatus::options())->disabled(),
                    Textarea::make('admin_notes')
                        ->label('ملاحظات داخلية قديمة (غير مرئية للمستفيد)')
                        ->disabled()
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_message_at', 'desc')
            ->columns([
                TextColumn::make('ticket_number')->label('الرقم')->searchable()->sortable(),
                TextColumn::make('subject')->label('الموضوع')->searchable()->wrap()->limit(40),
                TextColumn::make('name')->label('المستفيد')->searchable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => SupportTicketStatus::coerce($state)->adminLabel())
                    ->color(fn ($state): string => match (SupportTicketStatus::coerce($state)) {
                        SupportTicketStatus::Open => 'warning',
                        SupportTicketStatus::InProgress => 'info',
                        SupportTicketStatus::WaitingOnUser => 'gray',
                        SupportTicketStatus::Resolved => 'success',
                        SupportTicketStatus::Closed => 'success',
                    }),
                TextColumn::make('priority')
                    ->label('الأولوية')
                    ->formatStateUsing(fn ($state): string => $state instanceof SupportTicketPriority
                        ? $state->label()
                        : (SupportTicketPriority::tryFrom((string) $state)?->label() ?? '—')),
                TextColumn::make('unread_beneficiary_count')
                    ->label('غير مقروء')
                    ->badge()
                    ->state(fn (SupportTicket $record): int => (int) ($record->unread_beneficiary_count ?? 0))
                    ->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('last_message_at')->label('آخر نشاط')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('last_message_sender_type')
                    ->label('آخر مرسل')
                    ->formatStateUsing(fn ($state): string => $state instanceof SupportMessageSenderType
                        ? $state->adminLabel()
                        : (SupportMessageSenderType::tryFrom((string) $state)?->adminLabel() ?? '—')),
                TextColumn::make('assignee.name')->label('المسند')->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('أُنشئت')->dateTime('Y-m-d H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(SupportTicketStatus::options()),
                SelectFilter::make('category')
                    ->label('التصنيف')
                    ->options(SupportTicketCategory::options()),
                SelectFilter::make('priority')
                    ->label('الأولوية')
                    ->options(SupportTicketPriority::options()),
                TernaryFilter::make('has_unread')
                    ->label('رسائل مستفيد غير مقروءة')
                    ->queries(
                        true: function (Builder $query): Builder {
                            $user = auth()->user();
                            if (! $user instanceof User) {
                                return $query;
                            }

                            return $query->whereRaw(
                                '(SELECT COUNT(*) FROM support_ticket_messages m WHERE m.support_ticket_id = support_tickets.id AND m.sender_type = ? AND m.id > COALESCE((SELECT c.last_read_message_id FROM support_ticket_read_cursors c WHERE c.support_ticket_id = support_tickets.id AND c.user_id = ?), 0)) > 0',
                                [SupportMessageSenderType::Beneficiary->value, $user->id]
                            );
                        },
                        false: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make()->label('المحادثة'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['user', 'assignee']);

        $user = auth()->user();
        if ($user instanceof User) {
            app(SupportUnreadService::class)->attachUnreadBeneficiarySelect($query, $user);

            // Default sort priority: unread desc, then oldest waiting (open/in_progress), then last activity.
            $query->orderByRaw(
                '(SELECT COUNT(*) FROM support_ticket_messages m WHERE m.support_ticket_id = support_tickets.id AND m.sender_type = ? AND m.id > COALESCE((SELECT c.last_read_message_id FROM support_ticket_read_cursors c WHERE c.support_ticket_id = support_tickets.id AND c.user_id = ?), 0)) DESC',
                [SupportMessageSenderType::Beneficiary->value, $user->id]
            )->orderByRaw(
                "CASE WHEN status IN ('open','in_progress','waiting_on_user') THEN 0 ELSE 1 END ASC"
            )->orderByRaw(
                "CASE WHEN status IN ('open','in_progress','waiting_on_user') THEN COALESCE(last_message_at, created_at) ELSE NULL END ASC"
            )->orderByDesc('last_message_at');
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }

        $user = auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        $count = SupportTicket::query()
            ->whereRaw(
                '(SELECT COUNT(*) FROM support_ticket_messages m WHERE m.support_ticket_id = support_tickets.id AND m.sender_type = ? AND m.id > COALESCE((SELECT c.last_read_message_id FROM support_ticket_read_cursors c WHERE c.support_ticket_id = support_tickets.id AND c.user_id = ?), 0)) > 0',
                [SupportMessageSenderType::Beneficiary->value, $user->id]
            )
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportTickets::route('/'),
            'view' => Pages\ViewSupportTicket::route('/{record}'),
        ];
    }
}
