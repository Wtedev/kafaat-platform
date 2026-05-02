<?php

namespace App\Filament\Pages;

use App\Filament\Support\InboxNotificationRecordActions;
use App\Models\InboxNotification;
use App\Services\Inbox\InboxNotificationService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;

class InAppNotificationCenter extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'التنبيهات';

    protected static ?string $navigationLabel = 'التنبيهات';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell';

    protected static ?int $navigationSort = 45;

    protected static string|\UnitEnum|null $navigationGroup = 'الإشعارات';

    /**
     * @var array<string, mixed>|null
     */
    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    /**
     * @var mixed
     */
    #[Url(as: 'search')]
    public $tableSearch = '';

    #[Url(as: 'sort')]
    public ?string $tableSort = null;

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->mountInteractsWithTable();
    }

    /**
     * @return array<Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('send_in_app')
                ->label('إرسال تنبيه')
                ->icon('heroicon-o-paper-airplane')
                ->url(SendInAppNotification::getUrl())
                ->visible(fn (): bool => Gate::allows('accessSendInAppNotificationPage', auth()->user())),
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('view_notifications');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if ($user === null || ! $user->can('view_notifications')) {
            return null;
        }

        $count = app(InboxNotificationService::class)->unreadCount($user);

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public function getTitle(): string|Htmlable
    {
        return 'التنبيهات';
    }

    protected function getTableQuery(): Builder
    {
        return InboxNotification::query()
            ->where('user_id', auth()->id())
            ->with(['sender']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('message')
                    ->label('الرسالة')
                    ->limit(80)
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('type')
                    ->label('النوع')
                    ->formatStateUsing(fn ($state) => $state?->arabicLabel() ?? ''),

                TextColumn::make('sender.name')
                    ->label('المرسل')
                    ->placeholder('—')
                    ->default('—'),

                TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),

                TextColumn::make('read_at')
                    ->label('الحالة')
                    ->formatStateUsing(fn ($state) => $state === null ? 'غير مقروء' : 'مقروء')
                    ->badge()
                    ->color(fn ($state) => $state === null ? 'warning' : 'success'),
            ])
            ->filters([
                SelectFilter::make('read_status')
                    ->label('الحالة')
                    ->options([
                        'unread' => 'غير المقروءة',
                        'read' => 'المقروءة',
                    ])
                    ->placeholder('الكل')
                    ->query(function (Builder $query, array $data): void {
                        $value = $data['value'] ?? null;
                        if ($value === 'unread') {
                            $query->whereNull('read_at');
                        } elseif ($value === 'read') {
                            $query->whereNotNull('read_at');
                        }
                    }),
            ])
            ->actions(InboxNotificationRecordActions::filamentStandardRowActions())
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('mark_selected_read')
                        ->label('تحديد المحدد كمقروء')
                        ->icon('heroicon-o-check')
                        ->action(function (Collection $records): void {
                            $uid = auth()->id();
                            $records->each(function (InboxNotification $record) use ($uid): void {
                                abort_unless((int) $record->user_id === (int) $uid, 403);
                                Gate::authorize('update', $record);
                                if ($record->read_at === null) {
                                    $record->markAsRead();
                                }
                            });
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }
}
