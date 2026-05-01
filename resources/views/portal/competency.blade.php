@extends('layouts.portal')
@section('title', 'الكفاءة — ملفي المهني')

@php
$p = $profile;
$edit = route('portal.profile');
$cvLinks = $p?->cvLinksList() ?? [];
@endphp

@section('content')
<div class="mx-auto max-w-4xl">
    {{-- Hero --}}
    <header class="relative mb-8 overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
        <div class="absolute inset-0 opacity-[0.07]" style="background: linear-gradient(135deg, #253B5B 0%, #3CB878 100%);"></div>
        <div class="relative px-6 py-8 sm:px-10 sm:py-10">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                    <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-gray-200 text-2xl font-bold text-gray-600 ring-4 ring-white shadow-md sm:h-28 sm:w-28">
                        @if ($p?->avatarUrl())
                        <img src="{{ $p->avatarUrl() }}" alt="" class="h-full w-full object-cover" />
                        @else
                        {{ \App\Models\Profile::initialsFromName($user->name) }}
                        @endif
                    </div>
                    <div class="text-center sm:text-right">
                        <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">{{ $user->name }}</h1>
                        @if (filled($user->email))
                        <p class="mt-1 text-sm text-gray-500" dir="ltr">{{ $user->email }}</p>
                        @endif
                        <div class="mt-4 flex flex-wrap items-center justify-center gap-2 sm:justify-end">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $membership->badgeClasses() }}">
                                <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
                                {{ $membership->label() }}
                            </span>
                            @if (filled($p?->iconic_skill))
                            <span class="inline-flex max-w-full items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold shadow-sm ring-1 ring-amber-200/80" style="background: linear-gradient(135deg, #FFF7ED, #FFFBEB); color:#92400E">
                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                                <span class="truncate">{{ $p->iconic_skill }}</span>
                            </span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex shrink-0 flex-col items-stretch gap-2 sm:items-end">
                    <a href="{{ route('portal.competency.export-pdf') }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 text-sm font-semibold text-white shadow-md transition hover:opacity-95" style="background:#253B5B">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        تصدير كفاءتي كسيرة ذاتية
                    </a>
                    <p class="text-center text-[11px] text-gray-500 sm:text-right">ملف PDF يجمع بياناتك المعروضة أدناه</p>
                </div>
            </div>
        </div>
    </header>

    @php
    $sectionEdit = fn (string $hash) => $edit.$hash;
    @endphp

    {{-- نبذة --}}
    <section class="mb-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-3 flex items-start justify-between gap-3 border-b border-gray-50 pb-3">
            <h2 class="text-lg font-bold text-gray-900">نبذة</h2>
            <a href="{{ $sectionEdit('#cv-summary') }}" class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold text-[#253B5B] ring-1 ring-[#c5ddef] transition hover:bg-[#EAF2FA]">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                تعديل
            </a>
        </div>
        @if (filled($p?->bio))
        <div class="prose prose-sm max-w-none text-right text-gray-700">
            <p class="whitespace-pre-wrap">{{ $p->bio }}</p>
        </div>
        @else
        <p class="text-sm text-gray-400">لا توجد نبذة بعد. أضفها من ملفك الشخصي.</p>
        @endif
    </section>

    {{-- التعليم --}}
    <section class="mb-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-3 flex items-start justify-between gap-3 border-b border-gray-50 pb-3">
            <h2 class="text-lg font-bold text-gray-900">التعليم</h2>
            <a href="{{ $sectionEdit('#cv-education') }}" class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold text-[#253B5B] ring-1 ring-[#c5ddef] transition hover:bg-[#EAF2FA]">تعديل</a>
        </div>
        @if (filled($p?->cvSection('education')))
        <p class="whitespace-pre-wrap text-right text-sm leading-relaxed text-gray-700">{{ $p->cvSection('education') }}</p>
        @else
        <p class="text-sm text-gray-400">لم يُضف قسم التعليم بعد.</p>
        @endif
    </section>

    {{-- اللغات --}}
    <section class="mb-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-3 flex items-start justify-between gap-3 border-b border-gray-50 pb-3">
            <h2 class="text-lg font-bold text-gray-900">اللغات</h2>
            <a href="{{ $sectionEdit('#cv-languages') }}" class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold text-[#253B5B] ring-1 ring-[#c5ddef] transition hover:bg-[#EAF2FA]">تعديل</a>
        </div>
        @if (filled($p?->cvSection('languages')))
        <p class="whitespace-pre-wrap text-right text-sm leading-relaxed text-gray-700">{{ $p->cvSection('languages') }}</p>
        @else
        <p class="text-sm text-gray-400">لم تُضف اللغات بعد.</p>
        @endif
    </section>

    {{-- المهارات --}}
    <section class="mb-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-3 flex flex-wrap items-start justify-between gap-2 border-b border-gray-50 pb-3">
            <h2 class="text-lg font-bold text-gray-900">المهارات</h2>
            <div class="flex flex-wrap gap-2">
                <a href="{{ $sectionEdit('#cv-skills-manual') }}" class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold text-[#253B5B] ring-1 ring-[#c5ddef] transition hover:bg-[#EAF2FA]">تعديل النص</a>
                <a href="{{ $sectionEdit('#competencies-form') }}" class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold text-gray-600 ring-1 ring-gray-200 transition hover:bg-gray-50">المستويات الموجزة</a>
            </div>
        </div>
        @if (filled($p?->cvSection('skills')))
        <p class="mb-4 whitespace-pre-wrap text-right text-sm leading-relaxed text-gray-700">{{ $p->cvSection('skills') }}</p>
        @endif
        @if (count($competencyCards) > 0)
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($competencyCards as $card)
            <div class="rounded-xl bg-[#F8FAFC] px-4 py-3 ring-1 ring-gray-100">
                <p class="text-xs font-medium text-gray-500">{{ $card['title'] }}</p>
                <p class="mt-1 text-sm font-semibold text-gray-900">{{ $card['level'] }}</p>
            </div>
            @endforeach
        </div>
        @endif
        @if (! filled($p?->cvSection('skills')) && count($competencyCards) === 0)
        <p class="text-sm text-gray-400">لم تُضف مهارات بعد.</p>
        @endif
    </section>

    {{-- خارجية --}}
    <section class="mb-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-3 flex items-start justify-between gap-3 border-b border-gray-50 pb-3">
            <h2 class="text-lg font-bold text-gray-900">الدورات والشهادات الخارجية</h2>
            <a href="{{ $sectionEdit('#cv-external') }}" class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold text-[#253B5B] ring-1 ring-[#c5ddef] transition hover:bg-[#EAF2FA]">تعديل</a>
        </div>
        @if (filled($p?->cvSection('external_training')))
        <p class="whitespace-pre-wrap text-right text-sm leading-relaxed text-gray-700">{{ $p->cvSection('external_training') }}</p>
        @else
        <p class="text-sm text-gray-400">لا توجد بيانات خارجية بعد.</p>
        @endif
    </section>

    {{-- خبرات --}}
    <section class="mb-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-3 flex items-start justify-between gap-3 border-b border-gray-50 pb-3">
            <h2 class="text-lg font-bold text-gray-900">الخبرات أو المشاركات</h2>
            <a href="{{ $sectionEdit('#cv-experience') }}" class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold text-[#253B5B] ring-1 ring-[#c5ddef] transition hover:bg-[#EAF2FA]">تعديل</a>
        </div>
        @if (filled($p?->cvSection('experience')))
        <p class="whitespace-pre-wrap text-right text-sm leading-relaxed text-gray-700">{{ $p->cvSection('experience') }}</p>
        @else
        <p class="text-sm text-gray-400">لم تُضف خبرات بعد.</p>
        @endif
    </section>

    {{-- روابط --}}
    <section class="mb-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-3 flex items-start justify-between gap-3 border-b border-gray-50 pb-3">
            <h2 class="text-lg font-bold text-gray-900">روابط مهمة</h2>
            <a href="{{ $sectionEdit('#cv-links') }}" class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold text-[#253B5B] ring-1 ring-[#c5ddef] transition hover:bg-[#EAF2FA]">تعديل</a>
        </div>
        @if (count($cvLinks) > 0)
        <ul class="space-y-2 text-right">
            @foreach ($cvLinks as $link)
            <li>
                <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" class="text-sm font-semibold underline-offset-2 hover:underline" style="color:#253B5B">{{ $link['label'] }}</a>
            </li>
            @endforeach
        </ul>
        @else
        <p class="text-sm text-gray-400">لا توجد روابط بعد.</p>
        @endif
    </section>

    {{-- منصة: مسارات --}}
    <section class="mb-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-3 flex items-start justify-between gap-3 border-b border-gray-50 pb-3">
            <h2 class="text-lg font-bold text-gray-900">المسارات المكتملة</h2>
            <span class="rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-600">من المنصة</span>
        </div>
        @forelse ($completedPaths as $reg)
        <div class="border-b border-gray-50 py-3 last:border-0">
            <p class="font-semibold text-gray-900">{{ $reg->learningPath?->title ?? 'مسار' }}</p>
            <p class="mt-0.5 text-xs text-emerald-700">مكتمل @if($reg->completed_at) — {{ $reg->completed_at->format('Y-m-d') }} @endif</p>
        </div>
        @empty
        <p class="text-sm text-gray-400">لا توجد مسارات مكتملة مسجّلة بعد.</p>
        @endforelse
    </section>

    {{-- برامج --}}
    <section class="mb-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-3 flex items-start justify-between gap-3 border-b border-gray-50 pb-3">
            <h2 class="text-lg font-bold text-gray-900">البرامج المكتملة</h2>
            <span class="rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-600">من المنصة</span>
        </div>
        @forelse ($completedPrograms as $reg)
        <div class="border-b border-gray-50 py-3 last:border-0">
            <p class="font-semibold text-gray-900">{{ $reg->trainingProgram?->title ?? 'برنامج' }}</p>
            <p class="mt-0.5 text-xs text-emerald-700">مكتمل</p>
        </div>
        @empty
        <p class="text-sm text-gray-400">لا توجد برامج مكتملة بعد.</p>
        @endforelse
    </section>

    {{-- تطوع مكتمل --}}
    <section class="mb-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-3 flex items-start justify-between gap-3 border-b border-gray-50 pb-3">
            <h2 class="text-lg font-bold text-gray-900">الفرص التطوعية المكتملة</h2>
            <span class="rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-600">من المنصة</span>
        </div>
        @forelse ($completedVolunteering as $reg)
        <div class="border-b border-gray-50 py-3 last:border-0">
            <p class="font-semibold text-gray-900">{{ $reg->opportunity?->title ?? 'فرصة تطوع' }}</p>
            <p class="mt-0.5 text-xs text-emerald-700">مكتمل</p>
        </div>
        @empty
        <p class="text-sm text-gray-400">لا توجد فرص تطوعية مكتملة بعد.</p>
        @endforelse
    </section>

    {{-- شهادات --}}
    <section class="mb-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-3 flex items-start justify-between gap-3 border-b border-gray-50 pb-3">
            <h2 class="text-lg font-bold text-gray-900">الشهادات الصادرة من المنصة</h2>
            <span class="rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-600">من المنصة</span>
        </div>
        @forelse ($platformCertificates as $cert)
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-50 py-3 last:border-0">
            <div class="text-right">
                <p class="font-semibold text-gray-900">{{ \App\Services\Portal\CompetencyProfilePresenter::certificateTitle($cert) }}</p>
                <p class="mt-0.5 text-xs text-gray-500">{{ $cert->certificate_number }} @if($cert->issued_at) — {{ $cert->issued_at->translatedFormat('j F Y') }} @endif</p>
            </div>
            @if ($cert->fileUrl())
            <a href="{{ $cert->fileUrl() }}" target="_blank" class="text-xs font-semibold text-[#253B5B] hover:underline">تحميل PDF</a>
            @endif
        </div>
        @empty
        <p class="text-sm text-gray-400">لا توجد شهادات من المنصة بعد.</p>
        @endforelse
    </section>

    {{-- ساعات --}}
    <section class="mb-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-3 flex items-start justify-between gap-3 border-b border-gray-50 pb-3">
            <h2 class="text-lg font-bold text-gray-900">ساعات التطوع المعتمدة</h2>
            <span class="rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-600">من المنصة</span>
        </div>
        <p class="text-3xl font-bold tabular-nums" style="color:#253B5B">{{ number_format($approvedVolunteerHours, 1) }}</p>
        <p class="mt-1 text-xs text-gray-500">إجمالي الساعات المعتمدة في سجل المنصة</p>
    </section>

    {{-- توصيات --}}
    <section class="mb-8 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-4 flex items-start justify-between gap-3 border-b border-gray-50 pb-3">
            <h2 class="text-lg font-bold text-gray-900">التوصيات</h2>
        </div>
        @forelse ($recommendations as $rec)
        <blockquote class="mb-4 border-r-4 border-[#253B5B] pr-4 last:mb-0">
            <p class="text-right text-sm leading-relaxed text-gray-700">«{{ $rec->body }}»</p>
            <footer class="mt-3 text-right text-xs text-gray-500">
                <span class="font-semibold text-gray-800">{{ $rec->author_name }}</span>
                @if (filled($rec->author_title))
                <span class="text-gray-400"> — {{ $rec->author_title }}</span>
                @endif
            </footer>
        </blockquote>
        @empty
        <x-portal.empty-state
            title="لا توجد توصيات مضافة حتى الآن"
            description="يُمكن إضافة التوصيات لاحقاً من قبل الإدارة عند توفرها."
        />
        @endforelse
    </section>
</div>
@endsection
