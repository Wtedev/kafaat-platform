<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Enums\SupportMessageSenderType;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Filament\Resources\Pages\BaseViewRecord;
use App\Filament\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\SupportTicketService;
use App\Services\Support\SupportUnreadService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class ViewSupportTicket extends BaseViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->load([
            'messages' => fn ($q) => $q->orderBy('id'),
            'messages.author:id,name',
            'statusEvents' => fn ($q) => $q->orderBy('id'),
            'statusEvents.actor:id,name',
            'assignee:id,name',
        ]);

        $user = auth()->user();
        if ($user instanceof User) {
            app(SupportUnreadService::class)->markTicketRead($this->getRecord(), $user, 'staff');
        }
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات التذكرة')->columns(3)->schema([
                TextEntry::make('ticket_number')->label('الرقم'),
                TextEntry::make('subject')->label('الموضوع')->columnSpan(2),
                TextEntry::make('name')->label('الاسم'),
                TextEntry::make('email')->label('البريد'),
                TextEntry::make('status')->label('الحالة')->formatStateUsing(
                    fn ($state): string => SupportTicketStatus::coerce($state)->adminLabel()
                ),
                TextEntry::make('priority')->label('الأولوية')->formatStateUsing(
                    fn ($state): string => $state instanceof SupportTicketPriority
                        ? $state->label()
                        : (SupportTicketPriority::tryFrom((string) $state)?->label() ?? '—')
                ),
                TextEntry::make('category')->label('التصنيف')->formatStateUsing(
                    fn ($state): string => $state?->label() ?? '—'
                ),
                TextEntry::make('assignee.name')->label('المسند')->placeholder('—'),
                TextEntry::make('created_at')->label('أُنشئت')->dateTime('Y-m-d H:i'),
                TextEntry::make('last_message_at')->label('آخر نشاط')->dateTime('Y-m-d H:i')->placeholder('—'),
                TextEntry::make('admin_notes')
                    ->label('ملاحظات داخلية قديمة')
                    ->placeholder('—')
                    ->columnSpanFull()
                    ->visible(fn (SupportTicket $record): bool => filled($record->admin_notes)),
                TextEntry::make('resolution_summary')
                    ->label('ملخص الحل')
                    ->placeholder('—')
                    ->columnSpanFull()
                    ->visible(fn (SupportTicket $record): bool => filled($record->resolution_summary)),
            ]),
            Section::make('المحادثة')->schema([
                RepeatableEntry::make('messages')
                    ->label('')
                    ->schema([
                        TextEntry::make('sender_type')
                            ->label('المرسل')
                            ->formatStateUsing(fn ($state): string => $state instanceof SupportMessageSenderType
                                ? $state->adminLabel()
                                : (string) $state),
                        TextEntry::make('created_at')->label('الوقت')->dateTime('Y-m-d H:i'),
                        TextEntry::make('source')->label('المصدر')->badge(),
                        TextEntry::make('body')->label('النص')->columnSpanFull(),
                    ])
                    ->columns(3),
            ]),
            Section::make('سجل الحالات')->collapsed()->schema([
                RepeatableEntry::make('statusEvents')
                    ->label('')
                    ->schema([
                        TextEntry::make('from_status')->label('من')->formatStateUsing(
                            fn ($state): string => $state ? SupportTicketStatus::coerce($state)->adminLabel() : '—'
                        ),
                        TextEntry::make('to_status')->label('إلى')->formatStateUsing(
                            fn ($state): string => SupportTicketStatus::coerce($state)->adminLabel()
                        ),
                        TextEntry::make('reason')->label('السبب')->placeholder('—'),
                        TextEntry::make('status_update_text')->label('نص التحديث')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('created_at')->label('الوقت')->dateTime('Y-m-d H:i'),
                        TextEntry::make('actor.name')->label('بواسطة')->placeholder('—'),
                    ])
                    ->columns(3),
            ]),
        ]);
    }

    protected function getViewPageToolbarActions(): array
    {
        /** @var SupportTicket $ticket */
        $ticket = $this->getRecord();
        $user = auth()->user();
        $canReply = $user instanceof User && ($user->isAdmin() || $user->can('support_tickets.reply'));
        $canAssign = $user instanceof User && ($user->isAdmin() || $user->can('support_tickets.assign'));
        $canStatus = $user instanceof User && ($user->isAdmin() || $user->can('support_tickets.manage_status'));

        return [
            Action::make('reply')
                ->label('رد على المحادثة')
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn (): bool => $canReply)
                ->schema([
                    Textarea::make('body')
                        ->label('نص الرد للمستفيد')
                        ->required()
                        ->rows(5)
                        ->maxLength(4000),
                    Select::make('new_status')
                        ->label('تحديث الحالة (اختياري)')
                        ->options(function () use ($ticket): array {
                            $current = SupportTicketStatus::coerce($ticket->status);
                            $options = [$current->value => $current->adminLabel().' (بدون تغيير)'];
                            foreach ($current->allowedTransitions() as $to) {
                                $options[$to->value] = $to->adminLabel();
                            }

                            return $options;
                        })
                        ->native(false),
                    Textarea::make('status_update_text')
                        ->label('نص تحديث الحالة')
                        ->helperText('مطلوب عند الحل أو الإغلاق.')
                        ->rows(2)
                        ->maxLength(2000),
                    Select::make('priority')
                        ->label('الأولوية')
                        ->options(SupportTicketPriority::options())
                        ->default($ticket->priority?->value)
                        ->native(false),
                    Select::make('assigned_to')
                        ->label('المسند')
                        ->options(fn (): array => User::query()
                            ->where(function ($q): void {
                                $q->where('role_type', 'admin')
                                    ->orWhere('role_type', 'staff')
                                    ->orWhereHas('roles', fn ($r) => $r->whereIn('name', ['admin', 'staff']));
                            })
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->default($ticket->assigned_to)
                        ->searchable()
                        ->nullable()
                        ->visible($canAssign),
                    Textarea::make('resolution_summary')
                        ->label('ملخص الحل')
                        ->rows(2)
                        ->maxLength(2000),
                ])
                ->action(function (array $data) use ($ticket): void {
                    $actor = auth()->user();
                    if (! $actor instanceof User) {
                        return;
                    }

                    try {
                        app(SupportTicketService::class)->addSupportReply($ticket, $actor, $data);
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title('تعذّر إرسال الرد')->body(collect($e->errors())->flatten()->first() ?? '')->send();
                        throw $e;
                    }

                    Notification::make()->success()->title('تم إرسال الرد')->send();
                    $this->refreshFormData([
                        'status', 'priority', 'assigned_to', 'last_message_at', 'resolution_summary',
                    ]);
                }),
            Action::make('assign_to_me')
                ->label('تعيين لي')
                ->visible(fn (): bool => $canAssign)
                ->action(function () use ($ticket): void {
                    $actor = auth()->user();
                    if (! $actor instanceof User) {
                        return;
                    }
                    app(SupportTicketService::class)->assign($ticket, $actor, $actor);
                    Notification::make()->success()->title('تم التعيين')->send();
                    $this->refreshFormData(['assigned_to']);
                }),
            Action::make('reopen')
                ->label('إعادة فتح')
                ->visible(fn (): bool => $canStatus && in_array(
                    SupportTicketStatus::coerce($ticket->status),
                    [SupportTicketStatus::Closed, SupportTicketStatus::Resolved],
                    true
                ))
                ->schema([
                    Textarea::make('status_update_text')->label('سبب إعادة الفتح')->rows(2),
                ])
                ->action(function (array $data) use ($ticket): void {
                    $actor = auth()->user();
                    if (! $actor instanceof User) {
                        return;
                    }
                    app(SupportTicketService::class)->changeStatus(
                        $ticket,
                        $actor,
                        SupportTicketStatus::Open,
                        $data['status_update_text'] ?? null,
                        'reopen',
                    );
                    Notification::make()->success()->title('أُعيد فتح التذكرة')->send();
                    $this->refreshFormData(['status', 'closed_at']);
                }),
            Action::make('close')
                ->label('إغلاق')
                ->color('danger')
                ->visible(fn (): bool => $canStatus && SupportTicketStatus::coerce($ticket->status) !== SupportTicketStatus::Closed)
                ->schema([
                    Textarea::make('status_update_text')->label('سبب الإغلاق')->required()->rows(2),
                ])
                ->action(function (array $data) use ($ticket): void {
                    $actor = auth()->user();
                    if (! $actor instanceof User) {
                        return;
                    }
                    app(SupportTicketService::class)->changeStatus(
                        $ticket,
                        $actor,
                        SupportTicketStatus::Closed,
                        $data['status_update_text'] ?? null,
                        'close',
                    );
                    Notification::make()->success()->title('أُغلقت التذكرة')->send();
                    $this->refreshFormData(['status', 'closed_at']);
                }),
        ];
    }
}
