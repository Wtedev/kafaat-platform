@extends('layouts.portal')
@section('title', 'ملفي الشخصي')

@php
$p = $user->profile;
$levels = $p?->competency_levels ?? [];
$cv = is_array($p?->cv_sections) ? $p->cv_sections : [];
$cvLinks = old('cv_links', $cv['links'] ?? []);
if (! is_array($cvLinks)) {
    $cvLinks = [];
}
$cvLinks = array_values($cvLinks);
while (count($cvLinks) < 6) {
    $cvLinks[] = ['label' => '', 'url' => ''];
}
@endphp

@section('content')
<h1 class="mb-2 text-2xl font-bold text-gray-900">ملفي الشخصي</h1>
<p class="mb-8 text-sm text-gray-600">حدّث بياناتك، كفاءاتك، وسيرتك الذاتية — تظهر في لوحة التحكم عند التحديث.</p>

<div class="max-w-3xl space-y-8">
    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('portal.profile.update') }}" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PATCH')

            @if ($errors->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <h2 class="mb-4 border-b border-gray-100 pb-2 text-base font-bold text-gray-900">البيانات الأساسية</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-gray-700">الاسم الكامل <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#253B5B]/40 @error('name') border-red-400 @enderror" />
                    @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">رقم الجوال</label>
                    <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#253B5B]/40 @error('phone') border-red-400 @enderror" />
                    @error('phone') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">المدينة</label>
                    <input type="text" name="city" value="{{ old('city', $p?->city) }}" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#253B5B]/40 @error('city') border-red-400 @enderror" />
                    @error('city') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">تاريخ الميلاد</label>
                    <input type="date" name="birth_date" value="{{ old('birth_date', optional($p?->birth_date)->format('Y-m-d')) }}" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#253B5B]/40 @error('birth_date') border-red-400 @enderror" />
                    @error('birth_date') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">الجنس</label>
                    <select name="gender" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#253B5B]/40 @error('gender') border-red-400 @enderror">
                        <option value="">— اختر —</option>
                        <option value="male" @selected(old('gender', $p?->gender) === 'male')>ذكر</option>
                        <option value="female" @selected(old('gender', $p?->gender) === 'female')>أنثى</option>
                    </select>
                    @error('gender') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <h2 id="cv-summary" class="mb-4 mt-10 border-b border-gray-100 pb-2 text-base font-bold text-gray-900">نبذة احترافية</h2>
            <p class="mb-3 text-xs text-gray-500">تظهر في أعلى صفحة «الكفاءة» كملخص مهني.</p>
            <div class="mb-2">
                <textarea name="bio" rows="5" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#253B5B]/40 @error('bio') border-red-400 @enderror" placeholder="اكتب نبذة موجزة عن خلفيتك وأهدافك المهنية...">{{ old('bio', $p?->bio) }}</textarea>
                @error('bio') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <h2 id="competencies-form" class="mb-4 mt-10 border-b border-gray-100 pb-2 text-base font-bold text-gray-900">مستويات مهارية (موجزة)</h2>
            <p class="mb-4 text-xs text-gray-500">تُعرض في صفحة «الكفاءة» ضمن قسم المهارات. المهارة الأيقونية في الشريط الجانبي تُدار من الإدارة فقط.</p>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">اللغة الإنجليزية</label>
                    <input type="text" name="competency_english" value="{{ old('competency_english', $levels['english'] ?? '') }}" placeholder="مثال: متوسط — B1" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#253B5B]/40" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">برامج الأوفيس</label>
                    <input type="text" name="competency_office" value="{{ old('competency_office', $levels['office'] ?? '') }}" placeholder="مثال: متقدم في Excel" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#253B5B]/40" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">الدورات</label>
                    <input type="text" name="competency_courses" value="{{ old('competency_courses', $levels['courses'] ?? '') }}" placeholder="مثال: +12 دورة معتمدة" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#253B5B]/40" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">التعلم المستمر</label>
                    <input type="text" name="competency_continuous" value="{{ old('competency_continuous', $levels['continuous_learning'] ?? '') }}" placeholder="مثال: ساعات تعلّم ذاتي / أسبوع" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#253B5B]/40" />
                </div>
            </div>

            <h2 class="mb-2 mt-10 border-b border-gray-100 pb-2 text-base font-bold text-gray-900">ملف الكفاءة التفصيلي</h2>
            <p class="mb-4 text-xs text-gray-500">يُعرض في صفحة «الكفاءة». استخدم الأزرار «تعديل» هناك للوصول السريع إلى كل قسم.</p>

            <h3 id="cv-education" class="mb-2 mt-6 text-sm font-bold text-gray-800">التعليم</h3>
            <textarea name="cv_education" rows="4" class="mb-6 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#253B5B]/40" placeholder="المؤهلات، الجامعات، التخصصات...">{{ old('cv_education', $cv['education'] ?? '') }}</textarea>
            @error('cv_education') <p class="mb-4 text-xs text-red-500">{{ $message }}</p> @enderror

            <h3 id="cv-languages" class="mb-2 text-sm font-bold text-gray-800">اللغات</h3>
            <textarea name="cv_languages" rows="3" class="mb-6 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#253B5B]/40" placeholder="العربية (لغة أم)، الإنجليزية — مستوى...">{{ old('cv_languages', $cv['languages'] ?? '') }}</textarea>
            @error('cv_languages') <p class="mb-4 text-xs text-red-500">{{ $message }}</p> @enderror

            <h3 id="cv-skills-manual" class="mb-2 text-sm font-bold text-gray-800">المهارات</h3>
            <textarea name="cv_skills" rows="4" class="mb-6 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#253B5B]/40" placeholder="مهارات تقنية، إدارية، برمجية...">{{ old('cv_skills', $cv['skills'] ?? '') }}</textarea>
            @error('cv_skills') <p class="mb-4 text-xs text-red-500">{{ $message }}</p> @enderror

            <h3 id="cv-external" class="mb-2 text-sm font-bold text-gray-800">الدورات والشهادات الخارجية</h3>
            <textarea name="cv_external_training" rows="4" class="mb-6 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#253B5B]/40" placeholder="دورات خارج المنصة، جهات الإصدار، التواريخ...">{{ old('cv_external_training', $cv['external_training'] ?? '') }}</textarea>
            @error('cv_external_training') <p class="mb-4 text-xs text-red-500">{{ $message }}</p> @enderror

            <h3 id="cv-experience" class="mb-2 text-sm font-bold text-gray-800">الخبرات أو المشاركات</h3>
            <textarea name="cv_experience" rows="5" class="mb-6 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#253B5B]/40" placeholder="خبرات عمل، تطوع، مبادرات، فعاليات...">{{ old('cv_experience', $cv['experience'] ?? '') }}</textarea>
            @error('cv_experience') <p class="mb-4 text-xs text-red-500">{{ $message }}</p> @enderror

            <h3 id="cv-links" class="mb-2 text-sm font-bold text-gray-800">روابط مهمة</h3>
            <p class="mb-3 text-xs text-gray-500">حتى ست روابط (مثال: لينكدإن، معرض أعمال، GitHub).</p>
            <div class="mb-6 space-y-3">
                @foreach ($cvLinks as $idx => $linkRow)
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <input type="text" name="cv_links[{{ $idx }}][label]" value="{{ old("cv_links.$idx.label", $linkRow['label'] ?? '') }}" placeholder="التسمية" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm sm:w-40" />
                    <input type="text" name="cv_links[{{ $idx }}][url]" value="{{ old("cv_links.$idx.url", $linkRow['url'] ?? '') }}" placeholder="https://..." class="min-w-0 flex-1 rounded-xl border border-gray-300 px-3 py-2 text-sm" dir="ltr" />
                </div>
                @endforeach
            </div>
            @error('cv_links') <p class="mb-4 text-xs text-red-500">{{ $message }}</p> @enderror

            <h2 class="mb-4 mt-10 border-b border-gray-100 pb-2 text-base font-bold text-gray-900">مرفق السيرة الذاتية</h2>
            <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50/80 p-4">
                <label class="mb-1 block text-sm font-medium text-gray-700">رفع ملف (PDF أو Word — بحد أقصى 10 ميجا)</label>
                <input type="file" name="cv" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="mt-1 w-full text-sm text-gray-600 file:me-3 file:rounded-lg file:border-0 file:bg-[#EAF2FA] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#253B5B]" />
                @error('cv') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                @if ($p?->cv_path)
                <p class="mt-3 text-sm">
                    <a href="{{ asset('storage/'.$p->cv_path) }}" target="_blank" rel="noopener noreferrer" class="font-semibold underline" style="color:#253B5B">تحميل السيرة الحالية</a>
                </p>
                @endif
            </div>

            <div class="mt-8 flex justify-end">
                <button type="submit" class="rounded-xl px-8 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#253B5B">
                    حفظ التغييرات
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
