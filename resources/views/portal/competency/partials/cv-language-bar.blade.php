@php
$p = $profile;
$loc = $cvLocale ?? 'ar';
@endphp
<div class="mb-5 rounded-xl border border-gray-100 bg-[#F8FAFC] px-3 py-2 shadow-sm sm:px-4">
    <form method="POST" action="{{ route('portal.competency.update') }}" class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3" dir="{{ $loc === 'en' ? 'ltr' : 'rtl' }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="section" value="cv_display" />
        <label for="competency_cv_language" class="text-sm font-semibold text-gray-900">{{ $loc === 'en' ? 'My CV language' : 'لغة سيرتي الذاتية' }}</label>
        <div class="flex shrink-0 items-center sm:justify-end">
            <select id="competency_cv_language" name="cv_language" class="h-9 min-w-[9.5rem] rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 shadow-sm focus:border-[#253B5B] focus:outline-none focus:ring-2 focus:ring-[#253B5B]/20" onchange="this.form.submit()">
                <option value="ar" @selected(old('cv_language', $p?->cv_language ?? 'ar') === 'ar')>{{ $loc === 'en' ? 'Arabic' : 'العربية' }}</option>
                <option value="en" @selected(old('cv_language', $p?->cv_language ?? 'ar') === 'en')>English</option>
            </select>
        </div>
    </form>
</div>
