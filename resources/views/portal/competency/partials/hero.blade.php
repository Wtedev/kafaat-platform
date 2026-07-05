@php
$p = $profile;
$loc = $cvLocale ?? 'ar';
$jobTitle = trim((string) ($p?->job_title ?? ''));
$city = trim((string) ($p?->city ?? ''));
$phone = trim((string) ($user->phone ?? ''));
$email = trim((string) ($user->email ?? ''));
$editProfileLabel = $loc === 'en' ? 'Edit profile' : 'تعديل الملف';
$exportLabel = $loc === 'en' ? 'Export PDF' : 'تصدير PDF';
$langLabel = $loc === 'en' ? 'CV language' : 'لغة السيرة';
$meta = collect([
    $city !== '' ? $city : null,
    $email !== '' ? $email : null,
    $phone !== '' ? $phone : null,
])->filter()->values();
@endphp

<header class="mb-6 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 sm:px-5">
        <form method="POST" action="{{ route('portal.competency.update') }}" class="flex items-center gap-2.5">
            @csrf
            @method('PATCH')
            <input type="hidden" name="section" value="cv_display" />
            <label for="competency_cv_language" class="text-xs font-medium text-slate-500">{{ $langLabel }}</label>
            <select
                id="competency_cv_language"
                name="cv_language"
                class="h-9 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 focus:border-[#335483] focus:outline-none focus:ring-2 focus:ring-[#335483]/20"
                onchange="this.form.submit()"
            >
                <option value="ar" @selected(old('cv_language', $p?->cv_language ?? 'ar') === 'ar')>{{ $loc === 'en' ? 'Arabic' : 'العربية' }}</option>
                <option value="en" @selected(old('cv_language', $p?->cv_language ?? 'ar') === 'en')>English</option>
            </select>
        </form>

        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ route('portal.settings.profile') }}"
                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
            >
                <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                {{ $editProfileLabel }}
            </a>
            <a
                href="{{ route('portal.competency.export-pdf') }}"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-95"
                style="background:#335483"
            >
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                {{ $exportLabel }}
            </a>
        </div>
    </div>

    <div class="flex items-center gap-4 px-4 py-4 sm:px-5 sm:py-5">
        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-slate-100 text-lg font-bold text-slate-600 ring-2 ring-white sm:h-[4.5rem] sm:w-[4.5rem]">
            @if ($p?->avatarUrl())
            <img src="{{ $p->avatarUrl() }}" alt="" class="h-full w-full object-cover" />
            @else
            {{ \App\Models\Profile::initialsFromName($user->name) }}
            @endif
        </div>

        <div class="min-w-0 flex-1 text-right">
            <h1 class="truncate text-lg font-bold tracking-tight text-gray-900 sm:text-xl">{{ $user->name }}</h1>

            @if ($jobTitle !== '')
            <p class="mt-1 text-sm font-semibold text-[#335483]">{{ $jobTitle }}</p>
            @endif

            @if ($meta->isNotEmpty())
            <p class="mt-1.5 text-xs text-slate-500" dir="auto">{{ $meta->implode(' · ') }}</p>
            @elseif ($jobTitle === '')
            <p class="mt-1.5 text-xs text-slate-400">
                {{ $loc === 'en' ? 'Complete your profile to show title and contact details.' : 'أكمل ملفك الشخصي لإظهار المسمى وبيانات التواصل.' }}
                <a href="{{ route('portal.settings.profile') }}" class="font-medium text-[#335483] hover:underline">{{ $loc === 'en' ? 'Edit profile' : 'تعديل الملف' }}</a>
            </p>
            @endif
        </div>
    </div>
</header>
