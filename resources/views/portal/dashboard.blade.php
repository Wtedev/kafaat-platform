@extends('layouts.portal')
@section('title', 'الرئيسية')

@section('content')
@php
$firstName = \Illuminate\Support\Str::before($user->name, ' ');
$hasActivities = $activities->isNotEmpty();
$hasVolunteering = $volunteerRows->isNotEmpty();
$hasCurrent = $hasActivities || $hasVolunteering;
@endphp

<section class="mb-6 flex flex-wrap items-end justify-between gap-3 text-right">
    <div>
        <p class="text-sm font-medium text-gray-500">مرحباً، <span class="text-gray-800">{{ $firstName }}</span></p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">لوحة التحكم</h1>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-gray-600">ملخص نشاطك التعليمي والتطوعي والتنبيهات في مكان واحد.</p>
    </div>
    <a href="{{ route('portal.competency') }}" class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 shadow-sm transition hover:border-[#335483]/30 hover:text-[#335483]">
        صفحة الكفاءة
    </a>
</section>

<section aria-labelledby="stats-heading" class="mb-6">
    <h2 id="stats-heading" class="sr-only">إحصائيات</h2>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 text-right">
                    <p class="text-xs font-medium leading-snug text-gray-500">مسارات وبرامج مسجّلة</p>
                    <p class="mt-1.5 text-2xl font-bold tabular-nums tracking-tight sm:text-3xl" style="color:#335483">{{ $programsRegistered }}</p>
                </div>
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#e9eff6] text-[#335483]" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                </span>
            </div>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 text-right">
                    <p class="text-xs font-medium leading-snug text-gray-500">مكتملة</p>
                    <p class="mt-1.5 text-2xl font-bold tabular-nums tracking-tight text-brand-secondary sm:text-3xl">{{ $programsCompleted }}</p>
                </div>
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#e6f5f6] text-brand-secondary" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 text-right">
                    <p class="text-xs font-medium leading-snug text-gray-500">ساعات تطوع معتمدة</p>
                    <p class="mt-1.5 text-2xl font-bold tabular-nums tracking-tight text-brand sm:text-3xl">{{ en_num($approvedHours, 1) }}</p>
                </div>
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#e9eff6] text-[#335483]" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 text-right">
                    <p class="text-xs font-medium leading-snug text-gray-500">الشهادات</p>
                    <p class="mt-1.5 text-2xl font-bold tabular-nums tracking-tight text-brand-accent sm:text-3xl">{{ $certificatesCount }}</p>
                </div>
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#fef6e6] text-[#c99316]" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12.75L11.25 15 15 9.75M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                </span>
            </div>
        </div>
    </div>
</section>

<div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(17rem,22rem)]">
    <div class="min-w-0 space-y-6">
        <section aria-labelledby="current-heading">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                <div class="text-right">
                    <h2 id="current-heading" class="text-base font-bold text-gray-900 sm:text-lg">نشاطي الحالي</h2>
                    <p class="mt-0.5 text-xs text-gray-500 sm:text-sm">برامج ومسارات وفرص تطوعية مسجّل فيها</p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <a href="{{ route('portal.programs') }}" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-gray-600 ring-1 ring-gray-200 transition hover:bg-gray-50">البرامج</a>
                    <a href="{{ route('portal.volunteering') }}" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-gray-600 ring-1 ring-gray-200 transition hover:bg-gray-50">التطوع</a>
                </div>
            </div>

            @if (! $hasCurrent)
            <x-portal.empty-state
                title="لا يوجد نشاط حالياً"
                description="سجّل في برنامج أو فرصة تطوعية ليظهر هنا لمتابعتك بسهولة."
            >
                <a href="{{ route('public.programs.index') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#335483">استكشف البرامج</a>
                <a href="{{ route('public.volunteering.index') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-gray-700 ring-1 ring-gray-200 transition hover:bg-gray-50">الفرص التطوعية</a>
            </x-portal.empty-state>
            @else
            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5">
                @if ($hasActivities)
                <div class="mb-4">
                    <h3 class="mb-3 text-sm font-bold text-gray-800">برامج حالية</h3>
                    <div class="-mx-1 flex gap-3 overflow-x-auto overflow-y-visible pb-1 pt-0.5 [scrollbar-width:thin] snap-x snap-mandatory">
                        @foreach ($activities as $activity)
                        @include('portal.partials.dashboard-activity-card', ['activity' => $activity])
                        @endforeach
                    </div>
                </div>
                @endif

                @if ($hasVolunteering)
                <div @class(['border-t border-gray-100 pt-4' => $hasActivities])>
                    <h3 class="mb-3 text-sm font-bold text-gray-800">فرص تطوعية حالية</h3>
                    <div class="-mx-1 flex gap-3 overflow-x-auto overflow-y-visible pb-1 pt-0.5 [scrollbar-width:thin] snap-x snap-mandatory">
                        @foreach ($volunteerRows as $row)
                        @include('portal.partials.dashboard-volunteer-card', ['row' => $row])
                        @endforeach
                    </div>
                </div>
                @elseif ($hasActivities)
                <div class="border-t border-gray-100 pt-4">
                    <p class="text-sm text-gray-500">لا توجد فرص تطوعية مسجّلة حالياً.
                        <a href="{{ route('public.volunteering.index') }}" class="font-semibold hover:underline" style="color:#335483">استكشف الفرص</a>
                    </p>
                </div>
                @endif
            </div>
            @endif
        </section>

        @if ($showVolunteerTeamDashboard)
        <section aria-labelledby="vol-team-heading">
            <div class="mb-3 text-right">
                <h2 id="vol-team-heading" class="text-base font-bold text-gray-900 sm:text-lg">الفريق التطوعي</h2>
                <p class="mt-0.5 text-xs text-gray-500 sm:text-sm">زملاؤك في الفرق النشطة التي انضممت إليها</p>
            </div>

            @if ($volunteerTeamMemberRows->isEmpty())
            <x-portal.empty-state
                title="لا يوجد فريق مرتبط بحسابك بعد"
                description="عند إضافتك إلى فريق تطوعي من قبل الإدارة، سيظهر هنا أعضاء الفريق."
            />
            @else
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($volunteerTeamMemberRows as $m)
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-right text-sm font-bold text-gray-900">{{ $m['name'] }}</p>
                    @if (! empty($m['email']))
                    <p class="mt-1 text-right text-xs text-gray-500">{{ $m['email'] }}</p>
                    @endif
                    <p class="mt-2 text-right text-xs font-medium text-gray-600">{{ $m['team_name'] }}</p>
                </div>
                @endforeach
            </div>
            @endif
        </section>

        <section aria-labelledby="vol-team-notif-heading">
            <div class="mb-3 text-right">
                <h2 id="vol-team-notif-heading" class="text-base font-bold text-gray-900 sm:text-lg">تنبيهات الفريق</h2>
                <p class="mt-0.5 text-xs text-gray-500 sm:text-sm">إعلانات منسّقي الفرق لأعضاء فريقك</p>
            </div>

            @if ($volunteerTeamNotifications->isEmpty())
            <x-portal.empty-state
                title="لا توجد تنبيهات منشورة"
                description="عند نشر إدارة التطوع إعلاناً لفريقك، سيظهر هنا."
            />
            @else
            <ul class="space-y-3">
                @foreach ($volunteerTeamNotifications as $n)
                <li class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <h3 class="text-right text-sm font-bold text-gray-900">{{ $n['title'] }}</h3>
                        @if (! empty($n['published_at']))
                        <time class="shrink-0 text-xs text-gray-500" datetime="{{ $n['published_at']->toIso8601String() }}">{{ ar_date_time($n['published_at']) }}</time>
                        @endif
                    </div>
                    <p class="mt-1 text-right text-xs font-medium text-gray-500">{{ $n['team_name'] }}</p>
                    @if (! empty($n['body']))
                    <p class="mt-2 whitespace-pre-wrap text-right text-sm leading-relaxed text-gray-700">{{ $n['body'] }}</p>
                    @endif
                </li>
                @endforeach
            </ul>
            @endif
        </section>
        @endif
    </div>

    <aside class="min-w-0 lg:sticky lg:top-20" aria-labelledby="inbox-heading">
        <h2 id="inbox-heading" class="sr-only">التنبيهات</h2>
        @include('portal.partials.inbox-notifications-panel', [
            'items' => $inboxPreview,
            'unreadCount' => $inboxUnreadCount,
            'panelTitle' => 'التنبيهات',
            'panelSubtitle' => 'آخر التحديثات على حسابك',
            'showViewAll' => true,
            'compact' => true,
        ])
    </aside>
</div>
@endsection
