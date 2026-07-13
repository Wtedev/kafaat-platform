<?php

namespace App\Filament\Resources;

use App\Enums\SupportTicketStatus;
use App\Filament\Resources\SupportTicketResource\Pages;
use App\Models\SupportTicket;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|\UnitEnum|null $navigationGroup = 'الأمان والامتثال';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'تذاكر الدعم';

    protected static ?string $modelLabel = 'تذكرة';

    protected static ?string $pluralModelLabel = 'تذاكر الدعم';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return static::canAccess();
    }

    public static function canDelete($record): bool
    {
        return static::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('تفاصيل التذكرة')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('الاسم')->disabled(),
                    TextInput::make('email')->label('البريد')->disabled(),
                    TextInput::make('subject')->label('الموضوع')->disabled()->columnSpanFull(),
                    Textarea::make('body')->label('الوصف')->disabled()->rows(6)->columnSpanFull(),
                    TextInput::make('page_url')->label('رابط الصفحة')->disabled()->columnSpanFull(),
                    Select::make('status')
                        ->label('الحالة')
                        ->options(SupportTicketStatus::options())
                        ->required()
                        ->native(false),
                    Textarea::make('admin_notes')
                        ->label('ملاحظات داخلية')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('subject')->label('الموضوع')->searchable()->wrap()->limit(40),
                TextColumn::make('name')->label('المرسل')->searchable(),
                TextColumn::make('email')->label('البريد')->searchable()->toggleable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof SupportTicketStatus
                        ? $state->label()
                        : (SupportTicketStatus::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->color(fn ($state): string => match ($state instanceof SupportTicketStatus ? $state : SupportTicketStatus::tryFrom((string) $state)) {
                        SupportTicketStatus::Open => 'warning',
                        SupportTicketStatus::InProgress => 'info',
                        SupportTicketStatus::Closed => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->label('أُنشئت')->dateTime('j F Y — H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(SupportTicketStatus::options()),
            ])
            ->recordActions([
                EditAction::make()->label('عرض / تحديث'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user']);
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }

        $count = SupportTicket::query()
            ->where('status', SupportTicketStatus::Open->value)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportTickets::route('/'),
            'edit' => Pages\EditSupportTicket::route('/{record}/edit'),
        ];
    }
}
