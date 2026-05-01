@extends('layouts.portal')
@section('title', 'الرئيسية')

@section('content')
@php
$firstName = \Illuminate\Support\Str::before($user->name, ' ');
$hasActivities = $activities->isNotEmpty();
$hasVolunteering = $volunteerRows->isNotEmpty();
@endphp

<section class="mb-8 text-right">
    <p class="text-sm font-medium text-gray-500">مرحباً، <span class="text-gray-800">{{ $firstName }}</span></p>
    <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">لوحة التحكم</h1>
    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-gray-600">ملخص نشاطك التعليمي والتطوعي — بيانات مباشرة من حسابك.</p>
</section>

<section aria-labelledby="stats-heading" class="mb-8">
    <h2 id="stats-heading" class="sr-only">إحصائيات</h2>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500">مسارات وبرامج مسجّلة</p>
            <p class="mt-2 text-2xl font-bold tabular-nums sm:text-3xl" style="color:#253B5B">{{ $programsRegistered }}</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500">مكتملة</p>
            <p class="mt-2 text-2xl font-bold tabular-nums text-emerald-600 sm:text-3xl">{{ $programsCompleted }}</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500">ساعات تطوع معتمدة</p>
            <p class="mt-2 text-2xl font-bold tabular-nums text-sky-600 sm:text-3xl">{{ number_format($approvedHours, 1) }}</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500">الشهادات</p>
            <p class="mt-2 text-2xl font-bold tabular-nums text-violet-600 sm:text-3xl">{{ $certificatesCount }}</p>
        </div>
    </div>
</section>

<section class="mb-8" aria-labelledby="inbox-heading">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div class="text-right">
            <h2 id="inbox-heading" class="text-base font-bold text-gray-900 sm:text-lg">التنبيهات</h2>
            <p class="mt-0.5 text-xs text-gray-500 sm:text-sm">
                غير مقروء: <span class="font-semibold tabular-nums" style="color:#253B5B">{{ $inboxUnreadCount }}</span>
            </p>
        </div>
        <a href="{{ route('portal.notifications') }}" class="shrink-0 text-xs font-semibold underline-offset-2 hover:underline" style="color:#253B5B">عرض الكل</a>
    </div>

    @if ($inboxPreview->isEmpty())
    <x-portal.empty-state
        title="لا توجد تنبيهات بعد"
        description="ستظهر هنا آخر التنبيهات المتعلقة بتسجيلاتك وشهاداتك والأخبار عند توفرها."
    />
    @else
    <ul class="space-y-2 rounded-2xl border border-gray-100 bg-white p-3 shadow-sm">
        @foreach ($inboxPreview as $n)
        <li class="flex flex-wrap items-start justify-between gap-2 border-b border-gray-50 pb-2 last:border-0 last:pb-0">
            <div class="min-w-0 flex-1 text-right">
                <p class="text-xs font-medium text-gray-500">{{ $n->type->arabicLabel() }}</p>
                <p class="mt-0.5 text-sm font-bold text-gray-900">{{ $n->title }}</p>
                <time class="mt-1 block text-[10px] text-gray-400" datetime="{{ $n->created_at->toIso8601String() }}">{{ $n->created_at->translatedFormat('j F Y، H:i') }}</time>
            </div>
            @if ($n->read_at === null)
            <span class="shrink-0 rounded-md bg-sky-50 px-1.5 py-0.5 text-[10px] font-bold text-sky-800">جديد</span>
            @endif
        </li>
        @endforeach
    </ul>
    @endif
</section>

<section class="mb-8" aria-labelledby="programs-heading">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div class="text-right">
            <h2 id="programs-heading" class="text-base font-bold text-gray-900 sm:text-lg">البرامج واللقاءات</h2>
            <p class="mt-0.5 text-xs text-gray-500 sm:text-sm">حالة التسجيل والتقدّم لكل عنصر</p>
        </div>
        <div class="flex shrink-0 gap-2">
            <a href="{{ route('portal.paths') }}" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-gray-600 ring-1 ring-gray-200 transition hover:bg-gray-50">المسارات</a>
            <a href="{{ route('portal.programs') }}" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-gray-600 ring-1 ring-gray-200 transition hover:bg-gray-50">البرامج</a>
        </div>
    </div>

    @if (! $hasActivities)
    <x-portal.empty-state
        title="لا توجد برامج أو لقاءات لعرضها"
        description="لم يظهر عندك أي تسجيل أو فرصة مقترحة بعد. استكشف البرامج والمسارات من الموقع العام والتسجيل من هناك."
    >
        <a href="{{ route('public.programs.index') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#253B5B">استكشف البرامج</a>
        <a href="{{ route('public.paths.index') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-gray-700 ring-1 ring-gray-200 transition hover:bg-gray-50">استكشف المسارات</a>
    </x-portal.empty-state>
    @else
    <div class="-mx-1 flex gap-4 overflow-x-auto overflow-y-visible pb-2 pt-1 [scrollbar-width:thin]">
        @foreach ($activities as $activity)
        @include('portal.partials.dashboard-activity-card', ['activity' => $activity])
        @endforeach
    </div>
    @endif
</section>

<section class="mb-4" aria-labelledby="vol-heading">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div class="text-right">
            <h2 id="vol-heading" class="text-base font-bold text-gray-900 sm:text-lg">الفرص التطوعية</h2>
            <p class="mt-0.5 text-xs text-gray-500 sm:text-sm">فرص منشورة وحالتك في كل منها</p>
        </div>
        <a href="{{ route('portal.volunteering') }}" class="shrink-0 text-xs font-semibold underline-offset-2 hover:underline" style="color:#253B5B">عرض الكل</a>
    </div>

    @if (! $hasVolunteering)
    <x-portal.empty-state
        title="لا توجد فرص تطوعية منشورة"
        description="لا تتوفر حالياً فرص في لوحة التحكم. يمكنك تصفّح صفحة الفرص على الموقع العام أو إكمال ملفك ليصلك إشعار لاحقاً."
    >
        <a href="{{ route('public.volunteering.index') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#253B5B">استكشف الفرص التطوعية</a>
        <a href="{{ route('portal.competency') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-gray-700 ring-1 ring-gray-200 transition hover:bg-gray-50">طوّر صفحة الكفاءة</a>
    </x-portal.empty-state>
    @else
    <div class="-mx-1 flex gap-3 overflow-x-auto overflow-y-visible pb-2 pt-1 [scrollbar-width:thin]">
        @foreach ($volunteerRows as $row)
        <article class="flex min-w-[17.5rem] max-w-[17.5rem] flex-none snap-start flex-col rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:min-w-[19rem] sm:max-w-[19rem]">
            <h3 class="text-right text-sm font-bold leading-snug text-gray-900">{{ $row['title'] }}</h3>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                @if ($row['hours'] !== null)
                <span class="rounded-lg bg-gray-50 px-2 py-1 font-medium text-gray-600">{{ number_format((float) $row['hours'], 0) }} ساعة</span>
                @else
                <span class="text-gray-400">—</span>
                @endif
                @php
                $vt = [
                    'emerald' => 'bg-emerald-50 text-emerald-900 ring-1 ring-emerald-200/80',
                    'indigo' => 'bg-indigo-50 text-indigo-900 ring-1 ring-indigo-200/80',
                    'slate' => 'bg-slate-100 text-slate-800 ring-1 ring-slate-200/80',
                ];
                @endphp
                <span class="rounded-lg px-2 py-1 font-semibold {{ $vt[$row['state_tone']] ?? $vt['slate'] }}">{{ $row['state_label'] }}</span>
            </div>
            <div class="mt-4 flex justify-end">
                <a href="{{ $row['cta_url'] }}" class="text-sm font-semibold hover:underline" style="color:#253B5B">{{ $row['cta_label'] }}</a>
            </div>
        </article>
        @endforeach
    </div>
    @endif
</section>

@if ($showVolunteerTeamDashboard)
<section class="mb-8" aria-labelledby="vol-team-heading">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div class="text-right">
            <h2 id="vol-team-heading" class="text-base font-bold text-gray-900 sm:text-lg">الفريق التطوعي</h2>
            <p class="mt-0.5 text-xs text-gray-500 sm:text-sm">زملاؤك في الفرق النشطة التي انضممت إليها</p>
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

<section class="mb-4" aria-labelledby="vol-team-notif-heading">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div class="text-right">
            <h2 id="vol-team-notif-heading" class="text-base font-bold text-gray-900 sm:text-lg">تنبيهات الفريق</h2>
            <p class="mt-0.5 text-xs text-gray-500 sm:text-sm">إعلانات منسّقي الفرق لأعضاء فريقك</p>
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
        <li class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <h3 class="text-right text-sm font-bold text-gray-900">{{ $n['title'] }}</h3>
                @if (! empty($n['published_at']))
                <time class="shrink-0 text-xs text-gray-500" datetime="{{ $n['published_at']->toIso8601String() }}">{{ $n['published_at']->translatedFormat('j F Y، H:i') }}</time>
                @endif
            </div>
            <p class="mt-1 text-right text-xs font-medium text-gray-500">{{ $n['team_name'] }}</p>
            @if (! empty($n['body']))
            <p class="mt-2 text-right text-sm leading-relaxed text-gray-700 whitespace-pre-wrap">{{ $n['body'] }}</p>
            @endif
        </li>
        @endforeach
    </ul>
    @endif
</section>
@endif
@endsection
