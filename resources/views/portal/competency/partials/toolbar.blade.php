@php
$p = $profile;
$loc = $cvLocale ?? 'ar';
@endphp
<div class="mb-6 rounded-3xl border border-slate-100 bg-white p-6 shadow-sm lg:p-8">
    <div class="grid grid-cols-1 items-start gap-8 lg:grid-cols-2 lg:gap-10">
        <div class="{{ $loc === 'en' ? 'lg:order-1' : 'lg:order-2' }}">
            <p class="max-w-xl text-sm leading-relaxed text-gray-600 lg:pt-1">
                @if ($loc === 'en')
                Complete each section for a clearer, more professional competency profile. You can hide any section from the exported CV when needed.
                @else
                أكمل بياناتك لعرض ملف كفاءة أكثر وضوحًا واحترافية. كل قسم تضيفه يساعد في بناء صورة أدق عن مهاراتك وخبراتك. يمكنك إخفاء أي قسم من ملف السيرة عند التصدير.
                @endif
            </p>
        </div>
        <div class="{{ $loc === 'en' ? 'lg:order-2' : 'lg:order-1' }}">
            <form method="POST" action="{{ route('portal.competency.update') }}" class="flex flex-col gap-5" dir="{{ $loc === 'en' ? 'ltr' : 'rtl' }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="section" value="cv_display" />
                <div class="flex flex-wrap items-end gap-4">
                    <div class="min-w-0 w-full flex-1 basis-full sm:basis-64">
                        <label for="job_title" class="mb-2 block text-sm font-semibold text-gray-700">{{ $loc === 'en' ? 'Job title (CV headline)' : 'المسمى الوظيفي (يظهر في السيرة)' }}</label>
                        <input type="text" id="job_title" name="job_title" value="{{ old('job_title', $p?->job_title) }}" maxlength="160" placeholder="{{ $loc === 'en' ? 'e.g. Data analyst' : 'مثال: محلل بيانات' }}" class="h-[52px] w-full rounded-2xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-sm focus:border-[#253B5B] focus:outline-none focus:ring-2 focus:ring-[#253B5B]/20" />
                    </div>
                    <div class="min-w-0 w-full sm:w-48 sm:flex-initial">
                        <label for="cv_language" class="mb-2 block text-sm font-semibold text-gray-700">{{ $loc === 'en' ? 'CV labels language' : 'لغة عناوين السيرة' }}</label>
                        <select id="cv_language" name="cv_language" class="h-[52px] w-full rounded-2xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-sm focus:border-[#253B5B] focus:outline-none focus:ring-2 focus:ring-[#253B5B]/20">
                            <option value="ar" @selected(($p?->cv_language ?? 'ar') === 'ar')>العربية</option>
                            <option value="en" @selected(($p?->cv_language ?? 'ar') === 'en')>English</option>
                        </select>
                    </div>
                    <div class="w-full sm:w-auto sm:shrink-0 sm:self-end">
                        <button type="submit" class="h-[52px] w-full rounded-2xl px-8 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 sm:w-auto" style="background:#253B5B">{{ $loc === 'en' ? 'Save' : 'حفظ' }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
