@extends('layouts.portal')
@section('title', 'الرئيسية')

@push('styles')
<style>
    .portal-dash {
        --pd-brand: #335483;
        --pd-brand-soft: #e9eff6;
        --pd-brand-mid: #c5d4e4;
        --pd-ink: #0f172a;
        --pd-muted: #64748b;
    }

    .portal-dash-hero {
        position: relative;
        overflow: hidden;
        border-radius: 1.5rem;
        border: 1px solid rgba(197, 212, 228, 0.65);
        background:
            radial-gradient(120% 140% at 100% 0%, rgba(51, 84, 131, 0.14), transparent 55%),
            radial-gradient(90% 120% at 0% 100%, rgba(26, 147, 153, 0.08), transparent 50%),
            linear-gradient(165deg, #ffffff 0%, #f4f7fb 58%, #eef3f8 100%);
        box-shadow: 0 10px 36px -18px rgba(51, 84, 131, 0.28);
        animation: portal-dash-in 0.55s cubic-bezier(.22, 1, .36, 1) both;
    }

    .portal-dash-hero::before {
        content: '';
        position: absolute;
        inset-inline-end: -10%;
        top: -36%;
        width: 13rem;
        height: 13rem;
        border-radius: 9999px;
        background: radial-gradient(circle, rgba(51, 84, 131, 0.12), transparent 70%);
        pointer-events: none;
    }

    .portal-dash-stat {
        border-radius: 1.25rem;
        border: 1px solid rgba(197, 212, 228, 0.55);
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(247, 250, 252, 0.94));
        box-shadow: 0 8px 28px -16px rgba(51, 84, 131, 0.18);
        transition: transform 0.28s cubic-bezier(.22, 1, .36, 1), box-shadow 0.28s ease;
        animation: portal-dash-in 0.5s cubic-bezier(.22, 1, .36, 1) both;
    }

    .portal-dash-stat:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 32px -14px rgba(51, 84, 131, 0.24);
    }

    .portal-dash-stat:nth-child(1) { animation-delay: 0.04s; }
    .portal-dash-stat:nth-child(2) { animation-delay: 0.08s; }
    .portal-dash-stat:nth-child(3) { animation-delay: 0.12s; }
    .portal-dash-stat:nth-child(4) { animation-delay: 0.16s; }

    .portal-dash-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 1rem;
        border: 1px solid rgba(197, 212, 228, 0.7);
        background: rgba(255, 255, 255, 0.85);
        padding: 0.625rem 0.875rem;
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--pd-brand);
        transition: background 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
    }

    .portal-dash-chip:hover {
        background: var(--pd-brand-soft);
        border-color: rgba(51, 84, 131, 0.28);
        transform: translateY(-1px);
    }

    .portal-dash-section {
        animation: portal-dash-in 0.5s cubic-bezier(.22, 1, .36, 1) both;
    }

    .portal-dash-suggest {
        position: relative;
        overflow: hidden;
        border-radius: 1.5rem;
        border: 1px solid rgba(197, 212, 228, 0.6);
        background:
            radial-gradient(90% 120% at 0% 0%, rgba(51, 84, 131, 0.08), transparent 55%),
            linear-gradient(180deg, #ffffff, #f7fafc);
        box-shadow: 0 12px 40px -24px rgba(51, 84, 131, 0.3);
        animation: portal-dash-in 0.55s cubic-bezier(.22, 1, .36, 1) both;
    }

    .portal-dash-card {
        position: relative;
        overflow: hidden;
        border-radius: 1.25rem;
        border: 1px solid rgba(197, 212, 228, 0.55);
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(247, 250, 252, 0.94));
        box-shadow: 0 8px 28px -16px rgba(51, 84, 131, 0.2);
        transition: transform 0.28s cubic-bezier(.22, 1, .36, 1), box-shadow 0.28s ease, border-color 0.28s ease;
        animation: portal-dash-card-in 0.5s cubic-bezier(.22, 1, .36, 1) both;
    }

    .portal-dash-card:hover {
        transform: translateY(-2px);
        border-color: rgba(51, 84, 131, 0.28);
        box-shadow: 0 14px 36px -14px rgba(51, 84, 131, 0.28);
    }

    .portal-dash-card:nth-child(1) { animation-delay: 0.04s; }
    .portal-dash-card:nth-child(2) { animation-delay: 0.1s; }
    .portal-dash-card:nth-child(3) { animation-delay: 0.16s; }
    .portal-dash-card:nth-child(4) { animation-delay: 0.22s; }
    .portal-dash-card:nth-child(n+5) { animation-delay: 0.26s; }

    @keyframes portal-dash-in {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes portal-dash-card-in {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (prefers-reduced-motion: reduce) {
        .portal-dash-hero,
        .portal-dash-stat,
        .portal-dash-section,
        .portal-dash-suggest,
        .portal-dash-card {
            animation: none;
        }
        .portal-dash-stat:hover,
        .portal-dash-card:hover,
        .portal-dash-chip:hover {
            transform: none;
        }
    }
</style>
@endpush

@section('content')
@php
$firstName = \Illuminate\Support\Str::before($user->name, ' ');
$hasActivities = $activities->isNotEmpty();
$hasVolunteering = $volunteerRows->isNotEmpty();
$hasSuggestions = $suggestedPrograms->isNotEmpty() || $suggestedOpportunities->isNotEmpty();
@endphp

<div class="portal-dash space-y-8">
    <header class="portal-dash-hero px-5 py-5 sm:px-6 sm:py-6">
        <div class="relative z-[1] flex flex-wrap items-end justify-between gap-4">
            <div class="min-w-0 text-right">
                <p class="text-sm font-medium text-slate-500">مرحباً، <span class="text-slate-800">{{ $firstName }}</span></p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">لوحة التحكم</h1>
                <p class="mt-2 max-w-xl text-sm leading-relaxed text-slate-600">متابعة تعلّمك وتطوّعك، مع اقتراحات مناسبة من برامج وفرص كفاءات.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('portal.competency') }}" class="portal-dash-chip">
                    <svg class="h-4 w-4 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    صفحة الكفاءة
                </a>
                <a href="{{ route('portal.notifications') }}" class="portal-dash-chip">
                    <svg class="h-4 w-4 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    التنبيهات
                    @if ($inboxUnreadCount > 0)
                    <span class="rounded-full bg-brand px-1.5 py-0.5 text-[10px] font-bold text-white tabular-nums">{{ $inboxUnreadCount > 99 ? '99+' : $inboxUnreadCount }}</span>
                    @endif
                </a>
            </div>
        </div>
    </header>

    <section aria-labelledby="stats-heading">
        <h2 id="stats-heading" class="sr-only">إحصائيات</h2>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
            <div class="portal-dash-stat p-4">
                <p class="text-xs font-medium text-slate-500">مسارات وبرامج مسجّلة</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-brand sm:text-3xl">{{ $programsRegistered }}</p>
            </div>
            <div class="portal-dash-stat p-4">
                <p class="text-xs font-medium text-slate-500">مكتملة</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-brand-secondary sm:text-3xl">{{ $programsCompleted }}</p>
            </div>
            <div class="portal-dash-stat p-4">
                <p class="text-xs font-medium text-slate-500">ساعات تطوع معتمدة</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-brand sm:text-3xl">{{ en_num($approvedHours, 1) }}</p>
            </div>
            <div class="portal-dash-stat p-4">
                <p class="text-xs font-medium text-slate-500">الشهادات</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-brand-accent sm:text-3xl">{{ $certificatesCount }}</p>
            </div>
        </div>
    </section>

    <section class="portal-dash-section" aria-labelledby="inbox-heading" style="animation-delay:0.08s">
        @include('portal.partials.inbox-notifications-panel', [
            'items' => $inboxPreview,
            'unreadCount' => $inboxUnreadCount,
            'panelTitle' => 'التنبيهات',
            'panelSubtitle' => 'آخر التحديثات على حسابك',
            'showViewAll' => true,
            'compact' => true,
        ])
    </section>

    <section class="portal-dash-suggest p-5 sm:p-6" aria-labelledby="suggest-heading" style="animation-delay:0.1s">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div class="text-right">
                <h2 id="suggest-heading" class="text-lg font-bold text-slate-900 sm:text-xl">برامج كفاءات مقترحة لك</h2>
                <p class="mt-1 text-xs text-slate-500 sm:text-sm">برامج ومسارات وفرص تطوعية تناسب ملفك أو متاحة للتسجيل</p>
            </div>
            <div class="flex shrink-0 gap-2">
                <a href="{{ route('public.programs.index') }}" class="rounded-xl px-3 py-2 text-xs font-semibold text-brand ring-1 ring-brand-border transition hover:bg-brand-light">كل البرامج</a>
                <a href="{{ route('public.volunteering.index') }}" class="rounded-xl px-3 py-2 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50">الفرص التطوعية</a>
            </div>
        </div>

        @if (! $hasSuggestions)
        <x-portal.empty-state
            title="لا توجد اقتراحات حالياً"
            description="عند نشر برامج أو فرص تطوعية جديدة ستظهر هنا. يمكنك تصفّح الكتالوج العام في أي وقت."
        >
            <a href="{{ route('public.programs.index') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 bg-brand">استكشف البرامج</a>
            <a href="{{ route('public.volunteering.index') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 transition hover:bg-slate-50">استكشف الفرص</a>
        </x-portal.empty-state>
        @else
            @if ($suggestedPrograms->isNotEmpty())
            <div class="mb-6">
                <h3 class="mb-3 text-sm font-bold text-slate-800">برامج ومسارات</h3>
                <div class="-mx-1 flex gap-4 overflow-x-auto overflow-y-visible pb-2 pt-1 [scrollbar-width:thin] snap-x">
                    @foreach ($suggestedPrograms as $activity)
                    @include('portal.partials.dashboard-activity-card', ['activity' => $activity])
                    @endforeach
                </div>
            </div>
            @endif

            @if ($suggestedOpportunities->isNotEmpty())
            <div>
                <h3 class="mb-3 text-sm font-bold text-slate-800">فرص تطوعية</h3>
                <div class="-mx-1 flex gap-3 overflow-x-auto overflow-y-visible pb-2 pt-1 [scrollbar-width:thin] snap-x">
                    @foreach ($suggestedOpportunities as $row)
                    @include('portal.partials.dashboard-volunteer-card', ['row' => $row])
                    @endforeach
                </div>
            </div>
            @endif
        @endif
    </section>

    <section class="portal-dash-section" aria-labelledby="programs-heading" style="animation-delay:0.14s">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <div class="text-right">
                <h2 id="programs-heading" class="text-base font-bold text-slate-900 sm:text-lg">تعلّمي الحالي</h2>
                <p class="mt-0.5 text-xs text-slate-500 sm:text-sm">مساراتك والبرامج المستقلة المسجّل فيها</p>
            </div>
            <div class="flex shrink-0 gap-2">
                <a href="{{ route('portal.paths') }}" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50">المسارات</a>
                <a href="{{ route('portal.programs') }}" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50">البرامج</a>
            </div>
        </div>

        @if (! $hasActivities)
        <x-portal.empty-state
            title="لا توجد برامج أو مسارات مسجّلة بعد"
            description="ابدأ من الاقتراحات أعلاه أو استكشف كتالوج كفاءات."
        >
            <a href="#suggest-heading" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 bg-brand">عرض الاقتراحات</a>
        </x-portal.empty-state>
        @else
        <div class="-mx-1 flex gap-4 overflow-x-auto overflow-y-visible pb-2 pt-1 [scrollbar-width:thin] snap-x">
            @foreach ($activities as $activity)
            @include('portal.partials.dashboard-activity-card', ['activity' => $activity])
            @endforeach
        </div>
        @endif
    </section>

    <section class="portal-dash-section" aria-labelledby="vol-heading" style="animation-delay:0.18s">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <div class="text-right">
                <h2 id="vol-heading" class="text-base font-bold text-slate-900 sm:text-lg">تطوّعي الحالي</h2>
                <p class="mt-0.5 text-xs text-slate-500 sm:text-sm">الفرص التي سجّلت فيها ومتابعتها</p>
            </div>
            <a href="{{ route('portal.volunteering') }}" class="shrink-0 text-xs font-semibold underline-offset-2 hover:underline text-brand">عرض الكل</a>
        </div>

        @if (! $hasVolunteering)
        <x-portal.empty-state
            title="لم تسجّل في فرصة تطوعية بعد"
            description="تصفّح الفرص المقترحة أعلاه أو الصفحة العامة للفرص التطوعية."
        >
            <a href="#suggest-heading" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 bg-brand">الفرص المقترحة</a>
            <a href="{{ route('portal.competency') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 transition hover:bg-slate-50">طوّر صفحة الكفاءة</a>
        </x-portal.empty-state>
        @else
        <div class="-mx-1 flex gap-3 overflow-x-auto overflow-y-visible pb-2 pt-1 [scrollbar-width:thin] snap-x">
            @foreach ($volunteerRows as $row)
            @include('portal.partials.dashboard-volunteer-card', ['row' => $row])
            @endforeach
        </div>
        @endif
    </section>

    @if ($showVolunteerTeamDashboard)
    <section class="portal-dash-section" aria-labelledby="vol-team-heading" style="animation-delay:0.2s">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <div class="text-right">
                <h2 id="vol-team-heading" class="text-base font-bold text-slate-900 sm:text-lg">الفريق التطوعي</h2>
                <p class="mt-0.5 text-xs text-slate-500 sm:text-sm">زملاؤك في الفرق النشطة التي انضممت إليها</p>
            </div>
        </div>

        @if ($volunteerTeamMemberRows->isEmpty())
        <x-portal.empty-state
            title="لا يوجد فريق مرتبط بحسابك بعد"
            description="عند إضافتك إلى فريق تطوعي من قبل الإدارة، سيظهر هنا أعضاء الفريق."
        />
        @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($volunteerTeamMemberRows as $m)
            <div class="portal-dash-card p-4">
                <p class="text-right text-sm font-bold text-slate-900">{{ $m['name'] }}</p>
                @if (! empty($m['email']))
                <p class="mt-1 text-right text-xs text-slate-500">{{ $m['email'] }}</p>
                @endif
                <p class="mt-2 text-right text-xs font-medium text-slate-600">{{ $m['team_name'] }}</p>
            </div>
            @endforeach
        </div>
        @endif
    </section>

    <section class="portal-dash-section mb-2" aria-labelledby="vol-team-notif-heading" style="animation-delay:0.22s">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <div class="text-right">
                <h2 id="vol-team-notif-heading" class="text-base font-bold text-slate-900 sm:text-lg">تنبيهات الفريق</h2>
                <p class="mt-0.5 text-xs text-slate-500 sm:text-sm">إعلانات منسّقي الفرق لأعضاء فريقك</p>
            </div>
        </div>

        @if ($volunteerTeamNotifications->isEmpty())
        <x-portal.empty-state
            title="لا توجد تنبيهات منشورة"
            description="عند نشر إدارة التطوع إعلاناً لفريقك، سيظهر هنا."
        />
        @else
        <ul class="space-y-3">
            @foreach ($volunteerTeamNotifications as $n)
            <li class="portal-dash-card p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <h3 class="text-right text-sm font-bold text-slate-900">{{ $n['title'] }}</h3>
                    @if (! empty($n['published_at']))
                    <time class="shrink-0 text-xs text-slate-500" datetime="{{ $n['published_at']->toIso8601String() }}">{{ ar_date_time($n['published_at']) }}</time>
                    @endif
                </div>
                <p class="mt-1 text-right text-xs font-medium text-slate-500">{{ $n['team_name'] }}</p>
                @if (! empty($n['body']))
                <p class="mt-2 text-right text-sm leading-relaxed text-slate-700 whitespace-pre-wrap">{{ $n['body'] }}</p>
                @endif
            </li>
            @endforeach
        </ul>
        @endif
    </section>
    @endif
</div>
@endsection
