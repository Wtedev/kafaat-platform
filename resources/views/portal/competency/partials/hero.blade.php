@php
$p = $profile;
$headline = $p ? $p->headlineLabel($membership) : $membership->label();
$city = trim((string) ($p?->city ?? ''));
@endphp
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
                    <p class="mt-1 text-base font-semibold text-[#253B5B] sm:text-lg">{{ $headline }}</p>
                    @if ($city !== '')
                    <p class="mt-1 text-sm text-gray-600">{{ ($cvLabels ?? [])['city'] ?? 'المدينة' }}: {{ $city }}</p>
                    @endif
                    @if (filled($user->email))
                    <p class="mt-1 text-sm text-gray-500" dir="ltr">{{ $user->email }}</p>
                    @endif
                    <div class="mt-3 flex flex-wrap items-center justify-center gap-2 sm:justify-end">
                        <span class="text-[11px] text-gray-400">{{ ($cvLocale ?? 'ar') === 'en' ? 'Account type' : 'نوع الحساب' }}: <span class="font-medium text-gray-600">{{ $membership->label() }}</span></span>
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
                    {{ ($cvLocale ?? 'ar') === 'en' ? 'Export CV (PDF)' : 'تصدير كفاءتي كسيرة ذاتية' }}
                </a>
                <p class="text-center text-[11px] text-gray-500 sm:text-right">{{ ($cvLocale ?? 'ar') === 'en' ? 'Exports respect section visibility and your CV language setting.' : 'التصدير يلتزم بإظهار الأقسام ولغة عناوين السيرة التي اخترتها.' }}</p>
            </div>
        </div>
    </div>
</header>
