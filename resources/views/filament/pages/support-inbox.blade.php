<x-filament-panels::page>
    @php
        $tickets = $this->tickets();
        $counts = $this->tabCounts();
        $selected = $this->selectedTicket();
        $messages = $this->messages();
        $notes = $this->internalNotes();
        $canReply = $this->canReply();
        $canStatus = $this->canManageStatus();
        $canNotes = $this->canInternalNotes();
        $status = $selected ? \App\Enums\SupportTicketStatus::coerce($selected->status) : null;
        $allowsChat = $status?->allowsChat() ?? false;
        $tabs = [
            'all' => 'الكل',
            'unread' => 'غير مقروء',
            'open' => 'مفتوحة',
            'in_progress' => 'قيد المعالجة',
            'closed' => 'مغلقة',
        ];
    @endphp

    <div
        class="support-inbox"
        dir="rtl"
        data-mobile-screen="{{ $mobileScreen }}"
        x-data
        x-on:support-inbox-copy.window="
            if (navigator.clipboard && $event.detail.value) {
                navigator.clipboard.writeText($event.detail.value).catch(() => {});
            }
        "
    >
        {{-- List pane --}}
        <aside class="support-inbox__list" wire:poll.10s="refreshList">
            <div class="support-inbox__list-head">
                <h2 class="support-inbox__list-title">صندوق الدعم</h2>
                <div class="support-inbox__search">
                    <x-heroicon-o-magnifying-glass class="support-inbox__search-icon" />
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        class="support-inbox__search-input"
                        placeholder="بحث بالرقم أو الموضوع أو الاسم…"
                        autocomplete="off"
                    >
                </div>
                <div class="support-inbox__tabs" role="tablist" aria-label="تصفية التذاكر">
                    @foreach ($tabs as $key => $label)
                        <button
                            type="button"
                            role="tab"
                            wire:click="setFilterTab('{{ $key }}')"
                            @class(['support-inbox__tab', 'is-active' => $filterTab === $key])
                            aria-selected="{{ $filterTab === $key ? 'true' : 'false' }}"
                        >
                            <span>{{ $label }}</span>
                            <span class="support-inbox__tab-count">{{ $counts[$key] ?? 0 }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="support-inbox__ticket-list" role="listbox" aria-label="قائمة التذاكر">
                @forelse ($tickets as $ticket)
                    @php
                        $isActive = $selectedTicketId === $ticket->id;
                        $unread = (int) ($ticket->unread_beneficiary_count ?? 0);
                        $ticketStatus = \App\Enums\SupportTicketStatus::coerce($ticket->status);
                    @endphp
                    <button
                        type="button"
                        role="option"
                        aria-selected="{{ $isActive ? 'true' : 'false' }}"
                        wire:click="selectTicket({{ $ticket->id }})"
                        @class(['support-inbox__ticket', 'is-active' => $isActive, 'has-unread' => $unread > 0])
                    >
                        <div class="support-inbox__ticket-top">
                            <span class="support-inbox__ticket-number">{{ e($ticket->displayNumber()) }}</span>
                            @if ($unread > 0)
                                <span class="support-inbox__unread-dot" title="{{ $unread }} غير مقروءة">{{ $unread }}</span>
                            @endif
                        </div>
                        <p class="support-inbox__ticket-subject">{{ e($ticket->subject) }}</p>
                        <div class="support-inbox__ticket-meta">
                            <span class="support-inbox__status support-inbox__status--{{ $ticketStatus->value }}">
                                {{ $ticketStatus->adminLabel() }}
                            </span>
                            <time>
                                {{ optional($ticket->last_message_at ?? $ticket->created_at)?->timezone(config('app.timezone'))->format('m-d H:i') }}
                            </time>
                        </div>
                        <p class="support-inbox__ticket-name">{{ e($ticket->name) }}</p>
                    </button>
                @empty
                    <div class="support-inbox__empty-list">
                        <p>لا توجد تذاكر مطابقة.</p>
                    </div>
                @endforelse
            </div>
        </aside>

        {{-- Conversation pane --}}
        <section class="support-inbox__thread" wire:poll.10s="refreshThread">
            @if ($selected)
                <header class="support-inbox__thread-head">
                    <button type="button" class="support-inbox__back" wire:click="showList" aria-label="العودة للقائمة">
                        <x-heroicon-o-arrow-right class="h-5 w-5" />
                    </button>
                    <div class="support-inbox__thread-head-text">
                        <p class="support-inbox__thread-number">{{ e($selected->displayNumber()) }}</p>
                        <h3 class="support-inbox__thread-subject">{{ e($selected->subject) }}</h3>
                    </div>
                    <button type="button" class="support-inbox__meta-toggle" wire:click="showMeta" aria-label="تفاصيل التذكرة">
                        <x-heroicon-o-information-circle class="h-5 w-5" />
                    </button>
                </header>

                <div
                    class="support-inbox__messages"
                    wire:key="messages-{{ $selectedTicketId }}-{{ $threadRevision }}"
                    x-data="{
                        pinned: true,
                        check() {
                            this.pinned = this.$el.scrollHeight - this.$el.scrollTop - this.$el.clientHeight < 72;
                        },
                        scrollDown() {
                            if (!this.pinned) return;
                            this.$el.scrollTop = this.$el.scrollHeight;
                        },
                        init() {
                            this.$nextTick(() => this.scrollDown());
                            const obs = new MutationObserver(() => this.scrollDown());
                            obs.observe(this.$el, { childList: true, subtree: true });
                        }
                    }"
                    @scroll.passive="check()"
                >
                    @foreach ($messages as $message)
                        @php
                            $sender = $message->sender_type instanceof \App\Enums\SupportMessageSenderType
                                ? $message->sender_type
                                : \App\Enums\SupportMessageSenderType::tryFrom((string) $message->sender_type);
                            $isSystem = $message->is_system || $sender === \App\Enums\SupportMessageSenderType::System;
                            $isSupport = $sender === \App\Enums\SupportMessageSenderType::Support;
                            $isBeneficiary = $sender === \App\Enums\SupportMessageSenderType::Beneficiary;
                            $staffName = $message->author?->name;
                        @endphp

                        @if ($isSystem)
                            <div class="support-inbox__bubble support-inbox__bubble--system">
                                <p>{{ e($message->body) }}</p>
                                <time>{{ $message->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</time>
                            </div>
                        @else
                            <div @class([
                                'support-inbox__bubble-row',
                                'is-support' => $isSupport,
                                'is-beneficiary' => $isBeneficiary,
                            ])>
                                <div @class([
                                    'support-inbox__bubble',
                                    'support-inbox__bubble--support' => $isSupport,
                                    'support-inbox__bubble--beneficiary' => $isBeneficiary,
                                ])>
                                    <p class="support-inbox__bubble-author">
                                        @if ($isSupport)
                                            {{ e($staffName ?: 'فريق الدعم') }}
                                        @else
                                            المستفيد
                                        @endif
                                    </p>
                                    <p class="support-inbox__bubble-body">{{ e($message->body) }}</p>
                                    <time>{{ $message->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</time>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                @if ($mailStatus === 'queued')
                    <p class="support-inbox__mail-status" role="status">سيتم إرسال البريد الإلكتروني للمستفيد قريباً (في قائمة الانتظار).</p>
                @elseif ($mailStatus === 'failed')
                    <p class="support-inbox__mail-status is-error" role="status">تعذّر تجهيز إشعار البريد. الرد محفوظ في المحادثة.</p>
                @endif

                @if ($allowsChat && $canReply)
                    <div class="support-inbox__composer">
                        <label class="sr-only" for="support-inbox-reply">اكتب رداً</label>
                        <textarea
                            id="support-inbox-reply"
                            wire:model="replyBody"
                            rows="3"
                            maxlength="4000"
                            class="support-inbox__composer-input"
                            placeholder="اكتب ردك للمستفيد… (Ctrl/Cmd+Enter للإرسال)"
                            @keydown.meta.enter.prevent="if (!$wire.sending) $wire.sendReply()"
                            @keydown.ctrl.enter.prevent="if (!$wire.sending) $wire.sendReply()"
                            @disabled($sending)
                        ></textarea>
                        <div class="support-inbox__composer-actions">
                            <span class="support-inbox__composer-hint">Enter لسطر جديد · Ctrl/Cmd+Enter للإرسال</span>
                            <button
                                type="button"
                                class="support-inbox__btn support-inbox__btn--primary"
                                wire:click="sendReply"
                                wire:loading.attr="disabled"
                                @disabled($sending)
                            >
                                <span wire:loading.remove wire:target="sendReply">إرسال</span>
                                <span wire:loading wire:target="sendReply">جاري الإرسال…</span>
                            </button>
                        </div>
                    </div>
                @elseif ($selected && ! $allowsChat)
                    <div class="support-inbox__composer-closed">
                        <p>هذه التذكرة {{ $status?->adminLabel() }} ولا يمكن الرد عليها. يمكن للمستفيد فتح تذكرة جديدة.</p>
                    </div>
                @endif
            @else
                <div class="support-inbox__thread-empty">
                    <x-heroicon-o-chat-bubble-left-right class="h-10 w-10 opacity-40" />
                    <p>اختر تذكرة من القائمة لعرض المحادثة</p>
                </div>
            @endif
        </section>

        {{-- Meta pane --}}
        <aside class="support-inbox__meta">
            @if ($selected && $status)
                <div class="support-inbox__meta-mobile-head">
                    <button type="button" class="support-inbox__back" wire:click="showThread" aria-label="العودة للمحادثة">
                        <x-heroicon-o-arrow-right class="h-5 w-5" />
                    </button>
                    <span>تفاصيل التذكرة</span>
                </div>

                <div class="support-inbox__meta-block">
                    <h3 class="support-inbox__meta-title">التذكرة</h3>
                    <dl class="support-inbox__meta-dl">
                        <div>
                            <dt>الرقم</dt>
                            <dd>
                                <span>{{ e($selected->displayNumber()) }}</span>
                                <button type="button" class="support-inbox__copy" wire:click="copyTicketNumber" title="نسخ">نسخ</button>
                            </dd>
                        </div>
                        <div>
                            <dt>الموضوع</dt>
                            <dd>{{ e($selected->subject) }}</dd>
                        </div>
                        <div>
                            <dt>الحالة</dt>
                            <dd>
                                <span class="support-inbox__status support-inbox__status--{{ $status->value }}">
                                    {{ $status->adminLabel() }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt>المستفيد</dt>
                            <dd>{{ e($selected->name) }}</dd>
                        </div>
                        <div>
                            <dt>البريد</dt>
                            <dd>
                                <span class="support-inbox__truncate">{{ e($selected->email) }}</span>
                                @if (filled($selected->email))
                                    <button type="button" class="support-inbox__copy" wire:click="copyEmail" title="نسخ">نسخ</button>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>أُنشئت</dt>
                            <dd>{{ $selected->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</dd>
                        </div>
                        <div>
                            <dt>آخر نشاط</dt>
                            <dd>{{ optional($selected->last_message_at)?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}</dd>
                        </div>
                        @if (filled($selected->page_url))
                            <div>
                                <dt>صفحة المصدر</dt>
                                <dd>
                                    <a href="{{ e($selected->page_url) }}" target="_blank" rel="noopener noreferrer" class="support-inbox__link support-inbox__truncate">
                                        {{ e($selected->page_url) }}
                                    </a>
                                    <button type="button" class="support-inbox__copy" wire:click="copyPageUrl" title="نسخ">نسخ</button>
                                </dd>
                            </div>
                        @endif
                        @if ($selected->relatedProgram)
                            <div>
                                <dt>البرنامج المرتبط</dt>
                                <dd>{{ e($selected->relatedProgram->title) }}</dd>
                            </div>
                        @endif
                        @if ($selected->assignee)
                            <div>
                                <dt>المسند</dt>
                                <dd>{{ e($selected->assignee->name) }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                @if ($canStatus && $allowsChat)
                    <div class="support-inbox__meta-actions">
                        @if ($status !== \App\Enums\SupportTicketStatus::InProgress && $status->canTransitionTo(\App\Enums\SupportTicketStatus::InProgress))
                            <button type="button" class="support-inbox__btn support-inbox__btn--ghost" wire:click="setInProgress">
                                تعيين قيد المعالجة
                            </button>
                        @endif
                        <button type="button" class="support-inbox__btn support-inbox__btn--danger" wire:click="openCloseConfirm">
                            إغلاق التذكرة
                        </button>
                    </div>
                @endif

                @if ($closeConfirmOpen)
                    <div class="support-inbox__close-confirm" role="dialog" aria-label="تأكيد الإغلاق">
                        <p class="support-inbox__close-confirm-text">إغلاق التذكرة نهائي — لا يمكن إعادة فتحها. يمكن للمستفيد فتح تذكرة جديدة لاحقاً.</p>
                        <label class="sr-only" for="support-inbox-close-reason">سبب الإغلاق</label>
                        <textarea
                            id="support-inbox-close-reason"
                            wire:model="closeReason"
                            rows="2"
                            maxlength="2000"
                            class="support-inbox__composer-input"
                            placeholder="سبب الإغلاق (مطلوب)…"
                        ></textarea>
                        <div class="support-inbox__close-confirm-actions">
                            <button type="button" class="support-inbox__btn support-inbox__btn--ghost" wire:click="cancelCloseConfirm">إلغاء</button>
                            <button type="button" class="support-inbox__btn support-inbox__btn--danger" wire:click="closeTicket">تأكيد الإغلاق</button>
                        </div>
                    </div>
                @endif

                @if ($canNotes)
                    <div class="support-inbox__notes">
                        <h3 class="support-inbox__meta-title">ملاحظات داخلية</h3>
                        <p class="support-inbox__notes-hint">لا تظهر للمستفيد ولا في المحادثة.</p>

                        <div class="support-inbox__notes-list">
                            @forelse ($notes as $note)
                                <article class="support-inbox__note">
                                    <header>
                                        <strong>{{ e($note->author?->name ?? '—') }}</strong>
                                        <time>{{ $note->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</time>
                                    </header>
                                    <p>{{ e($note->body) }}</p>
                                </article>
                            @empty
                                <p class="support-inbox__notes-empty">لا توجد ملاحظات بعد.</p>
                            @endforelse
                        </div>

                        <div class="support-inbox__note-composer">
                            <label class="sr-only" for="support-inbox-note">ملاحظة داخلية</label>
                            <textarea
                                id="support-inbox-note"
                                wire:model="noteBody"
                                rows="2"
                                maxlength="4000"
                                class="support-inbox__composer-input"
                                placeholder="أضف ملاحظة داخلية…"
                            ></textarea>
                            <button type="button" class="support-inbox__btn support-inbox__btn--ghost" wire:click="addNote">
                                حفظ الملاحظة
                            </button>
                        </div>
                    </div>
                @endif
            @else
                <div class="support-inbox__meta-empty">
                    <p>لا توجد تذكرة محددة</p>
                </div>
            @endif
        </aside>
    </div>
</x-filament-panels::page>
