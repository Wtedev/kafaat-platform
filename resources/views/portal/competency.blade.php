@extends('layouts.portal')
@section('title', 'الكفاءة')

@section('content')
<style>
    details.portal-edit > summary { list-style: none; }
    details.portal-edit > summary::-webkit-details-marker { display: none; }
    details.rounded-xl > summary { list-style: none; }
    details.rounded-xl > summary::-webkit-details-marker { display: none; }
</style>

<div class="mx-auto max-w-4xl">
    <section class="mb-6 text-right">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">الكفاءة</h1>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-gray-600">ملفك المهني — أضف خبراتك ومؤهلاتك، وادمج إنجازاتك من المنصة، ثم صدّر سيرتك بصيغة PDF.</p>
    </section>

    @include('portal.competency.partials.completion-banner')

    @if ($errors->any())
    <div class="mb-5 rounded-2xl {{ config('brand.classes.alert_danger') }}">
        <ul class="list-inside list-disc space-y-1 text-right">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @include('portal.competency.partials.hero')
    <div id="competency-sections">
        @include('portal.competency.partials.sections-builder')
    </div>

    @php $cvL = $cvLabels ?? []; $platVis = $profile?->cvSectionVisible('platform') ?? true; @endphp
    <section class="mb-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-gray-50 pb-3">
            <h2 class="text-lg font-bold text-gray-900">{{ $cvL['platform'] ?? 'إنجازات المنصة' }}</h2>
            <div class="flex items-center gap-2">
                <span class="rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-600">{{ $cvLocale === 'en' ? 'Platform' : 'من المنصة' }}</span>
                @include('portal.competency.partials.cv-visibility-toggle', ['visible' => $platVis, 'toggle' => 'platform', 'cvLocale' => $cvLocale])
            </div>
        </div>
        @unless ($platVis)
        <p class="mb-4 rounded-lg bg-[#fef6e6] px-3 py-2 text-xs text-brand">{{ $cvLocale === 'en' ? 'Hidden from your exported CV. Toggle the eye to include it again.' : 'هذا القسم مخفي في ملف السيرة والتصدير. استخدم أيقونة العين لإظهاره.' }}</p>
        @endunless
        @if ($platVis)
        @php $platEmpty = 'rounded-lg border border-dashed border-gray-200 bg-slate-50/70 px-4 py-4 text-sm text-gray-500'; @endphp
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-gray-50 bg-[#F8FAFC] p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $cvL['learning_paths'] ?? 'المسارات' }}</p>
                <ul class="mt-2 space-y-1 text-sm text-gray-800">
                    @forelse ($completedPaths as $reg)
                    <li>{{ $reg->learningPath?->title ?? '—' }}</li>
                    @empty
                    <li class="list-none ps-0">
                        <p class="{{ $platEmpty }} mb-0 {{ $cvLocale === 'en' ? 'text-left' : 'text-right' }}">{{ $cvLocale === 'en' ? 'No completed learning paths yet.' : 'لا مسارات تعليمية مكتملة بعد.' }}</p>
                    </li>
                    @endforelse
                </ul>
            </div>
            <div class="rounded-xl border border-gray-50 bg-[#F8FAFC] p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $cvL['volunteer_hours'] ?? 'ساعات التطوع' }}</p>
                <p class="mt-2 text-2xl font-bold tabular-nums" style="color:#335483">{{ en_num($approvedVolunteerHours, 1) }}</p>
                @if ($approvedVolunteerHours <= 0)
                <p class="mt-2 text-sm text-gray-500">{{ $cvLocale === 'en' ? 'No approved volunteer hours yet.' : 'لا ساعات تطوع معتمدة بعد.' }}</p>
                @endif
            </div>
        </div>
        @if ($platformCertificates->isNotEmpty())
        <div class="mt-4 border-t border-gray-100 pt-4">
            <p class="mb-2 text-xs font-bold text-slate-600">{{ $cvLocale === 'en' ? 'Certificates (download)' : 'الشهادات (تحميل)' }}</p>
            <ul class="space-y-2 text-sm">
                @foreach ($platformCertificates as $cert)
                <li class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-gray-900">{{ \App\Services\Portal\CompetencyProfilePresenter::certificateTitle($cert) }}</span>
                    @if ($cert->downloadUrl())
                    <a href="{{ $cert->downloadUrl() }}" target="_blank" rel="noopener noreferrer" class="text-xs font-semibold text-[#335483] hover:underline">PDF</a>
                    @endif
                </li>
                @endforeach
            </ul>
        </div>
        @else
        <div class="mt-4 border-t border-gray-100 pt-4">
            <p class="mb-2 text-xs font-bold text-slate-600">{{ $cvLocale === 'en' ? 'Certificates (download)' : 'الشهادات (تحميل)' }}</p>
            <p class="{{ $platEmpty }} {{ $cvLocale === 'en' ? 'text-left' : 'text-right' }}">{{ $cvLocale === 'en' ? 'No platform certificates with a file to download yet.' : 'لا شهادات من المنصة مع ملف للتحميل بعد.' }}</p>
        </div>
        @endif
        @endif
    </section>

    @php $recVis = $profile?->cvSectionVisible('recommendations') ?? true; @endphp
    <section class="mb-8 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-4 flex items-start justify-between gap-3 border-b border-gray-50 pb-3">
            <h2 class="text-lg font-bold text-gray-900">{{ ($cvLabels ?? [])['recommendations'] ?? 'التوصيات' }}</h2>
            @include('portal.competency.partials.cv-visibility-toggle', ['visible' => $recVis, 'toggle' => 'recommendations', 'cvLocale' => $cvLocale])
        </div>
        @unless ($recVis)
        <p class="mb-4 rounded-lg bg-[#fef6e6] px-3 py-2 text-xs text-brand">{{ $cvLocale === 'en' ? 'Hidden from your exported CV.' : 'مخفي من ملف السيرة والتصدير.' }}</p>
        @endunless
        @if ($recVis)
        @forelse ($recommendations as $rec)
        <blockquote class="mb-4 border-r-4 border-[#335483] pr-4 last:mb-0">
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
            :title="$cvLocale === 'en' ? 'No recommendations yet' : 'لا توجد توصيات مضافة حتى الآن'"
            :description="$cvLocale === 'en' ? 'Recommendations may be added by the team when available.' : 'يُمكن إضافة التوصيات لاحقاً من قبل الإدارة عند توفرها.'"
        />
        @endforelse
        @endif
    </section>

    @if ($employmentConsentAvailable ?? false)
    <section class="mb-8 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="mb-2 text-lg font-bold text-gray-900">مشاركة البيانات لغرض التوظيف</h2>
        <p class="mb-4 text-sm leading-relaxed text-gray-600">{{ $employmentConsentText }}</p>

        <form method="POST" action="{{ route('portal.competency.employment-consent') }}" class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-4">
            @csrf
            <label class="flex cursor-pointer items-start gap-3">
                <input type="checkbox" name="employment_consent" value="1"
                    @checked(old('employment_consent', $employmentConsentGranted ?? false))
                    class="mt-1 rounded border-gray-300 text-brand focus:ring-brand/25" />
                <span class="text-sm text-gray-800">{{ config('candidate_pool.employment_consent_checkbox_label') }}</span>
            </label>
            @error('employment_consent') <p class="mt-2 text-xs text-brand-danger">{{ $message }}</p> @enderror
            <p class="mt-3 text-xs text-gray-500">يمكنك سحب الموافقة في أي وقت بإلغاء التحديد ثم الضغط على حفظ.</p>
            <button type="submit" class="mt-4 rounded-xl bg-brand px-5 py-2.5 text-sm font-semibold text-white transition hover:opacity-95">
                حفظ اختيار المشاركة
            </button>
        </form>
    </section>
    @endif

    <p class="mb-8 text-center text-xs text-gray-400">
        <a href="{{ route('public.privacy') }}" class="hover:text-brand hover:underline">سياسة الخصوصية</a>
    </p>
</div>
@endsection
