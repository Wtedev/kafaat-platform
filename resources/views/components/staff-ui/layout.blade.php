@props([
    'name' => 'مدير النظام',
    'email' => 'admin@kafaat.org.sa',
    'crumb' => 'لوحة التحكم',
    'preview' => false,
    'dashboardActive' => true,
    'activeNav' => null,
])

<!DOCTYPE html>
<html lang="ar-u-nu-latn" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $crumb }} — كفاءات</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/staff-ui.css'])
</head>
<body class="sui-body">
    <div class="sui-shell" data-sui-shell>
        <div class="sui-backdrop" data-sui-backdrop></div>
        <aside class="sui-sidebar" aria-label="القائمة الجانبية">
            <div class="sui-sidebar__brand">
                <img class="sui-sidebar__logo" src="{{ asset('images/brand/kafaat-mark.svg') }}" alt="">
                <span class="sui-sidebar__name">كفاءات</span>
                <button type="button" class="sui-icon-btn sui-sidebar__collapse" data-sui-collapse aria-label="طي القائمة">
                    <i data-lucide="chevrons-right" class="sui-icon sui-collapse-when-open"></i>
                    <i data-lucide="chevrons-left" class="sui-icon sui-collapse-when-closed"></i>
                </button>
            </div>

            @if ($preview)
                <div class="sui-switcher">
                    <x-staff-ui.dropdown>
                        <x-slot:trigger>
                            <button type="button" class="sui-switcher__btn">
                                <i data-lucide="layers" class="sui-icon"></i>
                                <span class="sui-switcher__text">
                                    <span class="sui-switcher__kicker">البرنامج الحالي</span>
                                    <span class="sui-switcher__value">برنامج تجريبي</span>
                                </span>
                                <i data-lucide="chevrons-up-down" class="sui-icon sui-collapse-when-open"></i>
                            </button>
                        </x-slot:trigger>
                        <button type="button" class="sui-menu-item">برنامج تجريبي</button>
                        <button type="button" class="sui-menu-item">مسار المهارات</button>
                        <button type="button" class="sui-menu-item">ملتقى البيانات</button>
                    </x-staff-ui.dropdown>
                </div>
            @endif

            <nav class="sui-nav">
                @if ($preview)
                    <p class="sui-nav__label">عام</p>
                    <x-staff-ui.nav-item icon="layout-dashboard" :href="route('staff-ui.demo')" active>لوحة التحكم</x-staff-ui.nav-item>
                    <x-staff-ui.nav-item icon="graduation-cap" count="6">البرامج</x-staff-ui.nav-item>
                    <x-staff-ui.nav-item icon="clipboard-list" count="18">التسجيلات</x-staff-ui.nav-item>
                    <x-staff-ui.nav-item icon="award">الشهادات</x-staff-ui.nav-item>

                    <p class="sui-nav__label">الأدوات</p>
                    <x-staff-ui.nav-item icon="bar-chart-3">التقارير</x-staff-ui.nav-item>
                    <x-staff-ui.nav-item icon="bell" count="4">الإشعارات</x-staff-ui.nav-item>

                    <p class="sui-nav__label">الحساب</p>
                    <x-staff-ui.nav-item icon="user">الملف الشخصي</x-staff-ui.nav-item>
                    <x-staff-ui.nav-item icon="settings">الإعدادات</x-staff-ui.nav-item>
                @else
                    @php
                        $currentNav = $activeNav ?? ($dashboardActive ? 'dashboard' : null);
                    @endphp
                    @foreach (config('staff_ui.nav', []) as $item)
                        @continue(isset($item['ability'], $item['model']) && ! auth()->user()?->can($item['ability'], $item['model']))
                        <x-staff-ui.nav-item
                            :icon="$item['icon']"
                            :href="route($item['route'])"
                            :active="$currentNav === $item['key']"
                        >{{ $item['label'] }}</x-staff-ui.nav-item>
                    @endforeach
                @endif
            </nav>

            <div class="sui-sidebar__user">
                <a class="sui-sidebar__user-link" href="{{ route('staff-ui.profile') }}">
                    <x-staff-ui.avatar :name="$name" />
                    <span class="sui-sidebar__user-meta">
                        <span class="sui-sidebar__user-name">{{ $name }}</span>
                        <span class="sui-sidebar__user-email">{{ $email }}</span>
                    </span>
                </a>
                <x-staff-ui.dropdown align="end">
                    <x-slot:trigger>
                        <button type="button" class="sui-icon-btn" aria-label="قائمة الحساب">
                            <i data-lucide="chevron-up" class="sui-icon"></i>
                        </button>
                    </x-slot:trigger>
                    <a class="sui-menu-item" href="{{ route('staff-ui.profile') }}">الملف الشخصي</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="sui-menu-item">تسجيل الخروج</button>
                    </form>
                </x-staff-ui.dropdown>
            </div>
        </aside>

        <div class="sui-main">
            <header class="sui-topbar">
                <div class="sui-topbar__cluster">
                    <button type="button" class="sui-icon-btn sui-menu-btn" data-sui-drawer aria-label="فتح القائمة">
                        <i data-lucide="menu" class="sui-icon"></i>
                    </button>
                    <button type="button" class="sui-icon-btn" onclick="history.back()" aria-label="رجوع">
                        <i data-lucide="chevron-right" class="sui-icon"></i>
                    </button>
                    <button type="button" class="sui-icon-btn" onclick="history.forward()" aria-label="تقدم">
                        <i data-lucide="chevron-left" class="sui-icon"></i>
                    </button>
                    <nav class="sui-crumb" aria-label="مسار التنقل">
                        <span>الصفحات</span>
                        <i data-lucide="chevron-left" class="sui-icon sui-icon--sm"></i>
                        <span class="is-current">{{ $crumb }}</span>
                    </nav>
                </div>
                <div class="sui-topbar__cluster sui-topbar__end">
                    @if (config('staff_ui.topbar.search'))
                        <label class="sui-search">
                            <i data-lucide="search" class="sui-icon"></i>
                            <input type="search" data-sui-global-search placeholder="ابحث..." aria-label="ابحث">
                        </label>
                    @endif
                    @if (config('staff_ui.topbar.help'))
                        <button type="button" class="sui-icon-btn" data-sui-toast data-tone="info" data-title="المساعدة" data-body="هذه أيقونة للمعاينة." aria-label="مساعدة">
                            <i data-lucide="circle-help" class="sui-icon"></i>
                        </button>
                    @endif
                    <x-staff-ui.dropdown align="end" class="sui-inbox">
                        <x-slot:trigger>
                            <button type="button" class="sui-icon-btn sui-bell" aria-label="الإشعارات">
                                <i data-lucide="bell" class="sui-icon"></i>
                                @if (($staffInboxUnreadCount ?? 0) > 0)
                                    <span class="sui-badge">{{ ($staffInboxUnreadCount ?? 0) > 99 ? '99+' : $staffInboxUnreadCount }}</span>
                                @endif
                            </button>
                        </x-slot:trigger>
                        <div class="sui-inbox__head">
                            <strong>الإشعارات</strong>
                            @if (($staffInboxUnreadCount ?? 0) > 0)
                                <form method="POST" action="{{ route('staff-ui.notifications.read-all') }}">
                                    @csrf
                                    <button type="submit" class="sui-inbox__mark">تعليم الكل كمقروء</button>
                                </form>
                            @endif
                        </div>
                        @forelse (($staffInboxNotifications ?? collect()) as $notification)
                            <div @class(['sui-inbox__item', 'is-unread' => $notification->read_at === null])>
                                <p class="sui-inbox__title">{{ \App\Support\InboxNotificationDisplay::heading($notification) }}</p>
                                @if (filled($notification->message))
                                    <p class="sui-inbox__body">{{ \Illuminate\Support\Str::limit(trim((string) $notification->message), 80) }}</p>
                                @endif
                                @php
                                    $downloadUrl = is_array($notification->context) ? ($notification->context['download_url'] ?? null) : null;
                                    $downloadUrl = is_string($downloadUrl) && (str_starts_with($downloadUrl, url('/')) || str_starts_with($downloadUrl, '/'))
                                        ? $downloadUrl
                                        : null;
                                @endphp
                                @if ($downloadUrl)
                                    <a class="sui-inbox__mark" href="{{ $downloadUrl }}">تنزيل الملف</a>
                                @endif
                                <div class="sui-inbox__meta">
                                    <time datetime="{{ $notification->created_at?->toIso8601String() }}">{{ $notification->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</time>
                                    @if ($notification->read_at === null)
                                        <form method="POST" action="{{ route('staff-ui.notifications.read', $notification) }}">
                                            @csrf
                                            <button type="submit" class="sui-inbox__mark">تعليم كمقروء</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="sui-inbox__empty">لا توجد إشعارات.</p>
                        @endforelse
                    </x-staff-ui.dropdown>
                    @if (config('staff_ui.topbar.settings'))
                        <button type="button" class="sui-icon-btn" data-sui-toast data-tone="info" data-title="الإعدادات" data-body="صفحة الإعدادات لم تُبنَ بعد." aria-label="الإعدادات">
                            <i data-lucide="settings" class="sui-icon"></i>
                        </button>
                    @endif
                    <x-staff-ui.dropdown align="end">
                        <x-slot:trigger>
                            <button type="button" class="sui-icon-btn" aria-label="حسابك" style="width: auto; padding-inline: 4px;">
                                <x-staff-ui.avatar :name="$name" />
                            </button>
                        </x-slot:trigger>
                        <span class="sui-menu-item">{{ $email }}</span>
                        <a class="sui-menu-item" href="{{ route('staff-ui.profile') }}">الملف الشخصي</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="sui-menu-item">تسجيل الخروج</button>
                        </form>
                    </x-staff-ui.dropdown>
                </div>
            </header>
            <main class="sui-content">
                {{ $slot }}
            </main>
        </div>
    </div>
    <x-staff-ui.toast-stack />
    <script src="https://unpkg.com/lucide@0.460.0/dist/umd/lucide.min.js"></script>
    <script src="{{ asset('js/chart.umd.min.js') }}"></script>
    @vite(['resources/js/staff-ui.js'])
</body>
</html>
