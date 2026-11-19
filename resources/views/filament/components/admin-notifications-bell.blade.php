@php
    use App\Filament\Pages\InAppNotificationCenter;
    use App\Services\Inbox\InboxNotificationService;
    use App\Services\StaffAlerts\StaffAlerts;

    $user = auth()->user();
@endphp
@if ($user !== null && $user->canAccessFilamentAdmin())
    @php
        $alertService = app(StaffAlerts::class);
        $alertService->refreshIfStale();
        $alerts = $alertService->visibleFor($user);
        $canInbox = $user->can('view_notifications');
        $inboxUnread = $canInbox ? app(InboxNotificationService::class)->unreadCount($user) : 0;
        $unread = $alerts->where('unread', true)->count() + $inboxUnread;
        $tones = [
            'registrations' => ['#e8f5f5', '#127a7f'],
            'delivery' => ['#fef2f2', '#dc2626'],
            'program' => ['#fff7e6', '#d97706'],
        ];
    @endphp
    <div x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false" style="position: relative;" class="me-1">
        <button
            type="button"
            x-on:click="open = ! open"
            title="التنبيهات"
            aria-label="التنبيهات"
            class="relative inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-500 outline-none ring-1 ring-transparent transition hover:bg-gray-50 hover:text-gray-700 focus-visible:ring-primary-500 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white"
        >
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
            </svg>
            @if ($unread > 0)
                <span class="absolute end-0.5 top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-danger px-1 text-[10px] font-bold leading-none text-white shadow-sm">
                    {{ $unread > 99 ? '99+' : $unread }}
                </span>
            @endif
        </button>

        <div x-show="open" x-cloak x-transition.opacity
             style="position: absolute; inset-inline-end: 0; top: 44px; z-index: 50; width: 360px; max-height: 480px; overflow: auto; background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgb(0 0 0 / .12); border: 1px solid #f3f4f6; direction: rtl; text-align: right;">
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; border-bottom: 1px solid #f3f4f6; position: sticky; top: 0; background: #fff;">
                <strong style="font-size: 14px; color: #335483;">التنبيهات</strong>
                @if ($unread > 0)
                    <form method="POST" action="{{ route('staff-alerts.read-all') }}" style="margin: 0;">
                        @csrf
                        <button type="submit" style="border: 0; background: none; color: #127a7f; font-size: 12px; cursor: pointer;">تعليم الكل كمقروء</button>
                    </form>
                @endif
            </div>

            @forelse ($alerts as $alert)
                @php [$bg, $fg] = $tones[$alert->group()] ?? ['#f3f4f6', '#4b5563']; @endphp
                <div style="display: flex; align-items: flex-start; gap: 4px; padding: 6px 8px; border-bottom: 1px solid #f3f4f6; {{ $alert->unread ? 'background: #e8f5f5;' : '' }}">
                    <a href="{{ route('staff-alerts.open', $alert) }}" style="display: flex; flex: 1; gap: 10px; min-width: 0; padding: 4px; color: inherit; text-decoration: none;">
                        <span style="flex: none; width: 10px; height: 10px; margin-top: 5px; border-radius: 999px; background: {{ $fg }}; box-shadow: 0 0 0 4px {{ $bg }};"></span>
                        <span style="display: grid; gap: 2px; min-width: 0;">
                            <span style="font-size: 13px; font-weight: 700; color: #335483;">{{ $alert->title }}</span>
                            @if (filled($alert->body))
                                <span style="font-size: 12px; color: #6b7280; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ \Illuminate\Support\Str::limit($alert->body, 70) }}</span>
                            @endif
                        </span>
                    </a>
                    @if ($alertService->isDismissible($alert))
                        <form method="POST" action="{{ route('staff-alerts.dismiss', $alert) }}" style="margin: 0;">
                            @csrf
                            <button type="submit" title="إخفاء لدى الجميع حتى يتغيّر" aria-label="إخفاء" style="border: 0; background: none; color: #9ca3af; cursor: pointer; font-size: 16px; line-height: 1; padding: 6px;">×</button>
                        </form>
                    @endif
                </div>
            @empty
                <p style="margin: 0; padding: 16px 12px; font-size: 13px; color: #6b7280;">لا يوجد ما يتطلب إجراءً منك الآن.</p>
            @endforelse

            @if ($canInbox)
                <a href="{{ InAppNotificationCenter::getUrl() }}" style="display: block; padding: 10px 12px; font-size: 12px; color: #127a7f; text-decoration: none;">
                    إشعاراتك الشخصية @if ($inboxUnread > 0)({{ $inboxUnread }} جديدة)@endif
                </a>
            @endif
        </div>
    </div>
@endif
