<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\InAppNotificationCenter;
use App\Models\InboxNotification;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestInAppNotificationsWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('view_notifications');
    }

    public function mount(): void
    {
        $this->mountInteractsWithTable();
    }

    protected function getTableQuery(): Builder
    {
        return InboxNotification::query()
            ->where('user_id', auth()->id())
            ->with('sender')
            ->latest('created_at')
            ->limit(5);
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->heading('آخر التنبيهات')
            ->headerActions([
                Action::make('all')
                    ->label('عرض جميع التنبيهات')
                    ->url(InAppNotificationCenter::getUrl()),
            ])
            ->columns([
                TextColumn::make('title')
                    ->label('العنوان')
                    ->limit(40),

                TextColumn::make('read_at')
                    ->label('')
                    ->formatStateUsing(fn ($state) => $state === null ? '●' : '')
                    ->tooltip(fn (InboxNotification $record) => $record->read_at === null ? 'غير مقروء' : ''),

                TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('Y/m/d'),
            ]);
    }
}
