@php
    use App\Filament\Pages\InAppNotificationCenter;
    use App\Services\Inbox\InboxNotificationService;

    $user = auth()->user();
@endphp
@if ($user !== null && $user->can('view_notifications'))
    @php
        $unread = app(InboxNotificationService::class)->unreadCount($user);
        $notificationsUrl = InAppNotificationCenter::getUrl();
    @endphp
    <a
        href="{{ $notificationsUrl }}"
        title="التنبيهات"
        class="relative me-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-500 outline-none ring-1 ring-transparent transition hover:bg-gray-50 hover:text-gray-700 focus-visible:ring-primary-500 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white"
    >
        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>
        @if ($unread > 0)
            <span class="absolute end-0.5 top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-none text-white shadow-sm">
                {{ $unread > 99 ? '99+' : $unread }}
            </span>
        @endif
    </a>
@endif
