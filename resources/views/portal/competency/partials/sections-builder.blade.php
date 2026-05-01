@php
use App\Services\Portal\CvFormOptions;
use App\Services\Portal\CvLanguagePresets;
use App\Services\Portal\CvUiTranslator;
$p = $profile;
$cvLocale = $cvLocale ?? 'ar';
$L = $cvLabels ?? CvUiTranslator::sectionLabels($cvLocale);
$mergedTimeline = $mergedExperience ?? [];
$mergedCoursesView = $mergedCourses ?? [];
$linkTypeLabels = CvFormOptions::linkTypeLabels();

$skillItems = old('skill_items', $p?->cvSkillsStructured() ?? []);
if (! is_array($skillItems)) { $skillItems = []; }
if (count($skillItems) === 0) { $skillItems[] = ['skill_name' => '', 'level' => 'متوسط', 'category' => '']; }

$langItems = old('language_items', $p?->cvLanguageRowsForForm() ?? []);
if (! is_array($langItems)) { $langItems = []; }
if (count($langItems) === 0) { $langItems[] = ['language_code' => 'ar', 'language_custom' => null, 'level' => 'متوسط', 'highlight_english' => false]; }

$toolItems = old('tool_items', $p?->cvOfficeToolsStructured() ?? []);
if (! is_array($toolItems)) { $toolItems = []; }
if (count($toolItems) === 0) { $toolItems[] = ['tool_name' => '', 'level' => 'متوسط']; }

$eduItems = old('education_items', $p?->cvEducationStructured() ?? []);
if (! is_array($eduItems)) { $eduItems = []; }
if (count($eduItems) === 0) { $eduItems[] = ['institution' => '', 'degree_or_program' => '', 'field' => '', 'start_year' => '', 'end_year' => '', 'is_current' => false]; }

$expItems = old('experience_items', $p?->cvExperienceStructured() ?? []);
if (! is_array($expItems)) { $expItems = []; }
if (count($expItems) === 0) { $expItems[] = ['title' => '', 'organization' => '', 'type' => 'on_site', 'employment_type' => 'participation', 'start_date' => '', 'end_date' => '', 'is_current' => false, 'description' => '']; }

$extItems = old('external_course_items', $p?->cvExternalCoursesStructured() ?? []);
if (! is_array($extItems)) { $extItems = []; }
if (count($extItems) === 0) { $extItems[] = ['title' => '', 'provider' => '', 'date' => '', 'certificate_url' => '', 'description' => '']; }

$rawLinks = $p?->cvLinksList() ?? [];
$linkRows = old('link_items', array_map(fn ($l) => ['label' => $l['label'], 'url' => $l['url'], 'type' => $l['type'] ?? ''], $rawLinks));
if (! is_array($linkRows)) { $linkRows = []; }
if (count($linkRows) === 0) { $linkRows[] = ['label' => '', 'url' => '', 'type' => '']; }
$tEdit = $cvLocale === 'en' ? 'Edit' : 'تعديل';
$emptyBox = 'mb-3 rounded-lg border border-dashed border-gray-200 bg-slate-50/70 px-4 py-4 text-sm text-gray-500';
@endphp

{{-- نبذة --}}
<section class="mb-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
    <div class="mb-3 flex items-start justify-between gap-2 border-b border-gray-50 pb-2">
        <h2 class="text-lg font-bold text-gray-900">{{ $L['summary'] ?? 'نبذة' }}</h2>
        <div class="flex shrink-0 items-center gap-0.5">
            @include('portal.competency.partials.cv-visibility-toggle', ['visible' => $p?->cvSectionVisible('bio'), 'toggle' => 'bio', 'cvLocale' => $cvLocale])
            <x-portal.cv-edit-dropdown :edit-title="$tEdit">
                <form method="POST" action="{{ route('portal.competency.update') }}" class="space-y-3">
                    @csrf @method('PATCH')
                    <input type="hidden" name="section" value="bio" />
                    <textarea name="bio" rows="4" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm">{{ old('bio', $p?->bio) }}</textarea>
                    <div class="flex justify-end"><button type="submit" class="rounded-xl px-6 py-2 text-sm font-semibold text-white" style="background:#253B5B">{{ $cvLocale === 'en' ? 'Save' : 'حفظ' }}</button></div>
                </form>
            </x-portal.cv-edit-dropdown>
        </div>
    </div>
    @if ($p?->cvSectionVisible('bio'))
    @if (filled($p?->bio))
    <p class="mb-3 whitespace-pre-wrap text-right text-sm text-gray-700">{{ $p->bio }}</p>
    @else
    <p class="{{ $emptyBox }} {{ $cvLocale === 'en' ? 'text-left' : 'text-right' }}">{{ $cvLocale === 'en' ? 'No professional summary yet. Add a short bio via Edit.' : 'لا توجد نبذة مهنية بعد. أضف نبذة موجزة من «'.$tEdit.'».' }}</p>
    @endif
    @else
    <p class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">{{ $cvLocale === 'en' ? 'This section is hidden from your exported CV. Use the eye icon to show it again.' : 'هذا القسم مخفي من ملف السيرة عند التصدير. يمكنك إظهاره من أيقونة العين.' }}</p>
    @endif
</section>

<div class="mb-5 grid gap-5 lg:grid-cols-2">
{{-- مهارات --}}
<section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
    <div class="mb-2 flex items-start justify-between gap-2">
        <h2 class="text-lg font-bold text-gray-900">{{ $L['skills'] ?? 'المهارات' }}</h2>
        <div class="flex shrink-0 items-center gap-0.5">
            @include('portal.competency.partials.cv-visibility-toggle', ['visible' => $p?->cvSectionVisible('skills'), 'toggle' => 'skills', 'cvLocale' => $cvLocale])
            <x-portal.cv-edit-dropdown :edit-title="$tEdit">
                <form method="POST" action="{{ route('portal.competency.update') }}" class="space-y-3">
                @csrf @method('PATCH')
                <input type="hidden" name="section" value="skills" />
                <table class="w-full text-sm">
                    <thead><tr class="text-right text-xs text-gray-500"><th class="pb-2">المهارة</th><th class="pb-2">المستوى</th><th class="pb-2">التصنيف</th><th class="w-10"></th></tr></thead>
                    <tbody id="skill-rows">
                        @foreach ($skillItems as $i => $row)
                        <tr data-cv-row class="border-t border-gray-100">
                            <td class="py-2 pe-2"><input type="text" name="skill_items[{{ $i }}][skill_name]" value="{{ $row['skill_name'] ?? '' }}" class="w-full rounded-lg border border-gray-300 px-2 py-1.5" placeholder="مثال: تحليل بيانات" /></td>
                            <td class="py-2 pe-2">
                                <select name="skill_items[{{ $i }}][level]" class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5">
                                    @foreach (CvFormOptions::SKILL_LEVELS as $lv)
                                    <option value="{{ $lv }}" @selected(($row['level'] ?? 'متوسط') === $lv)>{{ $lv }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-2 pe-2">
                                <select name="skill_items[{{ $i }}][category]" class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5">
                                    <option value="">—</option>
                                    @foreach (CvFormOptions::SKILL_CATEGORIES as $c)
                                    <option value="{{ $c }}" @selected(($row['category'] ?? '') === $c)>{{ $c }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-2"><button type="button" class="cv-remove-row text-xs text-red-600 hover:underline">حذف</button></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <button type="button" id="add-skill-row" class="text-xs font-semibold text-[#253B5B] hover:underline">+ إضافة مهارة</button>
                <script type="text/template" id="skill-tpl">
                    <tr data-cv-row class="border-t border-gray-100">
                        <td class="py-2 pe-2"><input type="text" name="skill_items[__IDX__][skill_name]" class="w-full rounded-lg border border-gray-300 px-2 py-1.5" /></td>
                        <td class="py-2 pe-2"><select name="skill_items[__IDX__][level]" class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5">@foreach (CvFormOptions::SKILL_LEVELS as $lv)<option value="{{ $lv }}">{{ $lv }}</option>@endforeach</select></td>
                        <td class="py-2 pe-2"><select name="skill_items[__IDX__][category]" class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5"><option value="">—</option>@foreach (CvFormOptions::SKILL_CATEGORIES as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select></td>
                        <td class="py-2"><button type="button" class="cv-remove-row text-xs text-red-600 hover:underline">حذف</button></td>
                    </tr>
                </script>
                <div class="flex justify-end"><button type="submit" class="rounded-xl px-6 py-2 text-sm font-semibold text-white" style="background:#253B5B">{{ $cvLocale === 'en' ? 'Save' : 'حفظ المهارات' }}</button></div>
            </form>
            </x-portal.cv-edit-dropdown>
        </div>
    </div>
    @if ($p?->cvSectionVisible('skills'))
    @if (count($p?->cvSkillsStructured() ?? []) > 0)
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($p->cvSkillsStructured() as $s)
        <span class="inline-flex flex-col rounded-xl bg-[#F0F4F8] px-3 py-2 text-right ring-1 ring-gray-100">
            <span class="text-sm font-semibold text-gray-900">{{ $s['skill_name'] }}</span>
            <span class="text-xs text-[#253B5B]">{{ \App\Services\Portal\CvFormOptions::skillLevelLabel($s['level'] ?? '', $cvLocale) }}@if(!empty($s['category'])) · {{ $s['category'] }}@endif</span>
        </span>
        @endforeach
    </div>
    @else
    <p class="{{ $emptyBox }} {{ $cvLocale === 'en' ? 'text-left' : 'text-right' }}">{{ $cvLocale === 'en' ? 'No skills yet. Add skills, levels, and categories via Edit.' : 'لم تُسجَّل مهارات بعد. استخدم «'.$tEdit.'» لإضافة المهارات والمستوى والتصنيف.' }}</p>
    @endif
    @else
    <p class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">{{ $cvLocale === 'en' ? 'This section is hidden from your exported CV. Use the eye icon to show it again.' : 'هذا القسم مخفي من ملف السيرة عند التصدير. يمكنك إظهاره من أيقونة العين.' }}</p>
    @endif
</section>

{{-- لغات --}}
<section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
    <div class="mb-2 flex items-start justify-between gap-2">
        <h2 class="text-lg font-bold text-gray-900">{{ $L['languages'] ?? 'اللغات' }}</h2>
        <div class="flex shrink-0 items-center gap-0.5">
            @include('portal.competency.partials.cv-visibility-toggle', ['visible' => $p?->cvSectionVisible('languages'), 'toggle' => 'languages', 'cvLocale' => $cvLocale])
            <x-portal.cv-edit-dropdown :edit-title="$tEdit">
            <form method="POST" action="{{ route('portal.competency.update') }}" class="space-y-3">
                @csrf @method('PATCH')
                <input type="hidden" name="section" value="languages" />
                <div id="lang-rows" class="space-y-3">
                    @foreach ($langItems as $i => $row)
                    @php
                        $code = $row['language_code'] ?? 'ar';
                        $isCustom = $code === 'custom';
                    @endphp
                    <div data-cv-row class="rounded-xl border border-gray-200 bg-white p-3">
                        <div class="mb-2 flex justify-between gap-2">
                            <span class="text-xs font-bold text-gray-400">#{{ $i + 1 }}</span>
                            <button type="button" class="cv-remove-row text-xs text-red-600 hover:underline">حذف</button>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="text-xs text-gray-500">اللغة</label>
                                <select name="language_items[{{ $i }}][language_code]" class="lang-code-select mt-0.5 w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-sm">
                                    @foreach (CvLanguagePresets::CODES as $c)
                                    <option value="{{ $c }}" @selected($code === $c)>{{ CvLanguagePresets::label($c, $cvLocale === 'en' ? 'en' : 'ar') }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">المستوى</label>
                                <select name="language_items[{{ $i }}][level]" class="mt-0.5 w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-sm">
                                    @foreach (CvFormOptions::LANGUAGE_LEVELS as $lv)
                                    <option value="{{ $lv }}" @selected(($row['level'] ?? 'متوسط') === $lv)>{{ \App\Services\Portal\CvFormOptions::languageLevelLabel($lv, $cvLocale) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="lang-custom-field sm:col-span-2 {{ $isCustom ? '' : 'hidden' }}">
                                <label class="text-xs text-gray-500">اسم اللغة (عند اختيار «أخرى»)</label>
                                <input type="text" name="language_items[{{ $i }}][language_custom]" value="{{ $row['language_custom'] ?? '' }}" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" placeholder="مثال: اليابانية" />
                            </div>
                            <div class="sm:col-span-2 flex items-center gap-2">
                                <input type="hidden" name="language_items[{{ $i }}][highlight_english]" value="0" />
                                <input type="checkbox" name="language_items[{{ $i }}][highlight_english]" value="1" class="rounded border-gray-300" @checked(!empty($row['highlight_english'])) />
                                <span class="text-xs text-gray-700">تمييز اللغة الإنجليزية</span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <button type="button" id="add-lang-row" class="text-xs font-semibold text-[#253B5B] hover:underline">+ إضافة لغة</button>
                <script type="text/template" id="lang-tpl">
                    <div data-cv-row class="rounded-xl border border-gray-200 bg-white p-3">
                        <div class="mb-2 flex justify-between gap-2"><span class="text-xs font-bold text-gray-400">جديد</span><button type="button" class="cv-remove-row text-xs text-red-600 hover:underline">حذف</button></div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div><label class="text-xs text-gray-500">اللغة</label><select name="language_items[__IDX__][language_code]" class="lang-code-select mt-0.5 w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-sm">@foreach (CvLanguagePresets::CODES as $c)<option value="{{ $c }}">{{ CvLanguagePresets::label($c, $cvLocale === 'en' ? 'en' : 'ar') }}</option>@endforeach</select></div>
                            <div><label class="text-xs text-gray-500">المستوى</label><select name="language_items[__IDX__][level]" class="mt-0.5 w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-sm">@foreach (CvFormOptions::LANGUAGE_LEVELS as $lv)<option value="{{ $lv }}">{{ \App\Services\Portal\CvFormOptions::languageLevelLabel($lv, $cvLocale) }}</option>@endforeach</select></div>
                            <div class="lang-custom-field hidden sm:col-span-2"><label class="text-xs text-gray-500">اسم اللغة (عند اختيار «أخرى»)</label><input type="text" name="language_items[__IDX__][language_custom]" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" /></div>
                            <div class="sm:col-span-2 flex items-center gap-2"><input type="hidden" name="language_items[__IDX__][highlight_english]" value="0" /><input type="checkbox" name="language_items[__IDX__][highlight_english]" value="1" class="rounded border-gray-300" /><span class="text-xs text-gray-700">تمييز اللغة الإنجليزية</span></div>
                        </div>
                    </div>
                </script>
                <div class="flex justify-end"><button type="submit" class="rounded-xl px-6 py-2 text-sm font-semibold text-white" style="background:#253B5B">{{ $cvLocale === 'en' ? 'Save' : 'حفظ اللغات' }}</button></div>
            </form>
            </x-portal.cv-edit-dropdown>
        </div>
    </div>
    @if ($p?->cvSectionVisible('languages'))
    @if (count($p?->cvLanguagesStructured() ?? []) > 0)
    <ul class="mb-4 space-y-2 text-right">
        @foreach ($p->cvLanguagesStructured() as $lng)
        <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-[#F8FAFC] px-4 py-2 ring-1 ring-gray-100">
            <span class="font-semibold @if($lng['highlight_english']) text-[#253B5B] @endif">{{ $lng['language_name'] }}</span>
            <span class="text-sm text-gray-600">{{ \App\Services\Portal\CvFormOptions::languageLevelLabel($lng['level'] ?? '', $cvLocale) }}</span>
        </li>
        @endforeach
    </ul>
    @else
    <p class="{{ $emptyBox }} {{ $cvLocale === 'en' ? 'text-left' : 'text-right' }}">{{ $cvLocale === 'en' ? 'No languages yet. Add languages and proficiency via Edit.' : 'لم تُضف لغات بعد. أضف اللغات ومستوى الإتقان من «'.$tEdit.'».' }}</p>
    @endif
    @else
    <p class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">{{ $cvLocale === 'en' ? 'This section is hidden from your exported CV. Use the eye icon to show it again.' : 'هذا القسم مخفي من ملف السيرة عند التصدير. يمكنك إظهاره من أيقونة العين.' }}</p>
    @endif
</section>
</div>

{{-- أدوات --}}
<section class="mb-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
    <div class="mb-2 flex items-start justify-between gap-2">
        <h2 class="text-lg font-bold text-gray-900">{{ $L['tools'] ?? 'الأدوات الرقمية' }}</h2>
        <div class="flex shrink-0 items-center gap-0.5">
            @include('portal.competency.partials.cv-visibility-toggle', ['visible' => $p?->cvSectionVisible('office_tools'), 'toggle' => 'office_tools', 'cvLocale' => $cvLocale])
            <x-portal.cv-edit-dropdown :edit-title="$tEdit">
            <form method="POST" action="{{ route('portal.competency.update') }}" class="space-y-3">
                @csrf @method('PATCH')
                <input type="hidden" name="section" value="office_tools" />
                <table class="w-full text-sm">
                    <thead><tr class="text-right text-xs text-gray-500"><th class="pb-2">الأداة</th><th class="pb-2">المستوى</th><th class="w-10"></th></tr></thead>
                    <tbody id="tool-rows">
                        @foreach ($toolItems as $i => $row)
                        <tr data-cv-row class="border-t border-gray-100">
                            <td class="py-2 pe-2"><input type="text" name="tool_items[{{ $i }}][tool_name]" value="{{ $row['tool_name'] ?? '' }}" class="w-full rounded-lg border border-gray-300 px-2 py-1.5" placeholder="Excel" /></td>
                            <td class="py-2 pe-2">
                                <select name="tool_items[{{ $i }}][level]" class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5">
                                    @foreach (CvFormOptions::SKILL_LEVELS as $lv)
                                    <option value="{{ $lv }}" @selected(($row['level'] ?? 'متوسط') === $lv)>{{ $lv }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-2"><button type="button" class="cv-remove-row text-xs text-red-600 hover:underline">حذف</button></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <button type="button" id="add-tool-row" class="text-xs font-semibold text-[#253B5B] hover:underline">+ إضافة أداة</button>
                <script type="text/template" id="tool-tpl">
                    <tr data-cv-row class="border-t border-gray-100">
                        <td class="py-2 pe-2"><input type="text" name="tool_items[__IDX__][tool_name]" class="w-full rounded-lg border border-gray-300 px-2 py-1.5" /></td>
                        <td class="py-2 pe-2"><select name="tool_items[__IDX__][level]" class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5">@foreach (CvFormOptions::SKILL_LEVELS as $lv)<option value="{{ $lv }}">{{ $lv }}</option>@endforeach</select></td>
                        <td class="py-2"><button type="button" class="cv-remove-row text-xs text-red-600 hover:underline">حذف</button></td>
                    </tr>
                </script>
                <div class="flex justify-end"><button type="submit" class="rounded-xl px-6 py-2 text-sm font-semibold text-white" style="background:#253B5B">{{ $cvLocale === 'en' ? 'Save' : 'حفظ' }}</button></div>
            </form>
            </x-portal.cv-edit-dropdown>
        </div>
    </div>
    @if ($p?->cvSectionVisible('office_tools'))
    @if (count($p?->cvOfficeToolsStructured() ?? []) > 0)
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($p->cvOfficeToolsStructured() as $t)
        <span class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-800">{{ $t['tool_name'] }} <span class="text-xs text-slate-500">({{ \App\Services\Portal\CvFormOptions::skillLevelLabel($t['level'] ?? '', $cvLocale) }})</span></span>
        @endforeach
    </div>
    @else
    <p class="{{ $emptyBox }} {{ $cvLocale === 'en' ? 'text-left' : 'text-right' }}">{{ $cvLocale === 'en' ? 'No tools yet. List software you use via Edit.' : 'لا توجد أدوات مضافة بعد. سجّل الأدوات التي تستخدمها من «'.$tEdit.'».' }}</p>
    @endif
    @else
    <p class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">{{ $cvLocale === 'en' ? 'This section is hidden from your exported CV. Use the eye icon to show it again.' : 'هذا القسم مخفي من ملف السيرة عند التصدير. يمكنك إظهاره من أيقونة العين.' }}</p>
    @endif
</section>

<div class="mb-5 grid gap-5 lg:grid-cols-2">
{{-- تعليم --}}
<section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
    <div class="mb-2 flex items-start justify-between gap-2">
        <h2 class="text-lg font-bold text-gray-900">{{ $L['education'] ?? 'التعليم' }}</h2>
        <div class="flex shrink-0 items-center gap-0.5">
            @include('portal.competency.partials.cv-visibility-toggle', ['visible' => $p?->cvSectionVisible('education'), 'toggle' => 'education', 'cvLocale' => $cvLocale])
            <x-portal.cv-edit-dropdown :edit-title="$tEdit">
            <form method="POST" action="{{ route('portal.competency.update') }}" class="space-y-4">
                @csrf @method('PATCH')
                <input type="hidden" name="section" value="education" />
                <div id="edu-rows" class="space-y-4">
                    @foreach ($eduItems as $i => $row)
                    <div data-cv-row class="rounded-xl border border-gray-200 bg-white p-4">
                        <div class="mb-2 flex justify-between gap-2">
                            <span class="text-xs font-bold text-gray-400">سجل {{ $i + 1 }}</span>
                            <button type="button" class="cv-remove-row text-xs text-red-600 hover:underline">حذف السجل</button>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2"><label class="text-xs text-gray-500">المؤسسة</label><input type="text" name="education_items[{{ $i }}][institution]" value="{{ $row['institution'] ?? '' }}" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" /></div>
                            <div><label class="text-xs text-gray-500">الدرجة / البرنامج</label><input type="text" name="education_items[{{ $i }}][degree_or_program]" value="{{ $row['degree_or_program'] ?? '' }}" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" /></div>
                            <div><label class="text-xs text-gray-500">التخصص / المجال</label><input type="text" name="education_items[{{ $i }}][field]" value="{{ $row['field'] ?? '' }}" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" /></div>
                            <div><label class="text-xs text-gray-500">سنة البداية</label><input type="text" name="education_items[{{ $i }}][start_year]" value="{{ $row['start_year'] ?? '' }}" maxlength="4" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" dir="ltr" /></div>
                            <div><label class="text-xs text-gray-500">سنة النهاية</label><input type="text" name="education_items[{{ $i }}][end_year]" value="{{ $row['end_year'] ?? '' }}" maxlength="4" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" dir="ltr" /></div>
                            <div class="sm:col-span-2 flex items-center gap-2">
                                <input type="hidden" name="education_items[{{ $i }}][is_current]" value="0" />
                                <input type="checkbox" name="education_items[{{ $i }}][is_current]" value="1" class="rounded border-gray-300" @checked(!empty($row['is_current'])) />
                                <span class="text-sm">ما زلت أدرس هنا</span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <button type="button" id="add-edu-row" class="text-xs font-semibold text-[#253B5B] hover:underline">+ إضافة سجل تعليم</button>
                <script type="text/template" id="edu-tpl">
                    <div data-cv-row class="rounded-xl border border-gray-200 bg-white p-4">
                        <div class="mb-2 flex justify-between gap-2"><span class="text-xs font-bold text-gray-400">سجل جديد</span><button type="button" class="cv-remove-row text-xs text-red-600 hover:underline">حذف السجل</button></div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2"><label class="text-xs text-gray-500">المؤسسة</label><input type="text" name="education_items[__IDX__][institution]" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" /></div>
                            <div><label class="text-xs text-gray-500">الدرجة / البرنامج</label><input type="text" name="education_items[__IDX__][degree_or_program]" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" /></div>
                            <div><label class="text-xs text-gray-500">التخصص / المجال</label><input type="text" name="education_items[__IDX__][field]" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" /></div>
                            <div><label class="text-xs text-gray-500">سنة البداية</label><input type="text" name="education_items[__IDX__][start_year]" maxlength="4" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" dir="ltr" /></div>
                            <div><label class="text-xs text-gray-500">سنة النهاية</label><input type="text" name="education_items[__IDX__][end_year]" maxlength="4" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" dir="ltr" /></div>
                            <div class="sm:col-span-2 flex items-center gap-2"><input type="hidden" name="education_items[__IDX__][is_current]" value="0" /><input type="checkbox" name="education_items[__IDX__][is_current]" value="1" class="rounded border-gray-300" /><span class="text-sm">ما زلت أدرس هنا</span></div>
                        </div>
                    </div>
                </script>
                <div class="flex justify-end"><button type="submit" class="rounded-xl px-6 py-2 text-sm font-semibold text-white" style="background:#253B5B">{{ $cvLocale === 'en' ? 'Save' : 'حفظ التعليم' }}</button></div>
            </form>
            </x-portal.cv-edit-dropdown>
        </div>
    </div>
    @if ($p?->cvSectionVisible('education'))
    @if (count($p?->cvEducationStructured() ?? []) > 0)
    <div class="mb-4 space-y-3 border-r-2 border-[#253B5B] pr-4">
        @foreach ($p->cvEducationStructured() as $ed)
        <div class="rounded-xl bg-white ring-1 ring-gray-100">
            <p class="font-semibold text-gray-900">{{ $ed['institution'] }}</p>
            <p class="text-sm text-gray-600">{{ $ed['degree_or_program'] ?? '' }} @if(!empty($ed['field']))· {{ $ed['field'] }} @endif</p>
            <p class="text-xs text-gray-500">
                @if (!empty($ed['is_current'])){{ $ed['start_year'] ?? '' }} — الآن @else {{ $ed['start_year'] ?? '' }} — {{ $ed['end_year'] ?? '' }} @endif
            </p>
        </div>
        @endforeach
    </div>
    @else
    <p class="{{ $emptyBox }} {{ $cvLocale === 'en' ? 'text-left' : 'text-right' }}">{{ $cvLocale === 'en' ? 'No education entries yet. Add your qualifications via Edit.' : 'لا توجد بيانات تعليمية بعد. أضف مؤهلاتك من «'.$tEdit.'».' }}</p>
    @endif
    @else
    <p class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">{{ $cvLocale === 'en' ? 'This section is hidden from your exported CV. Use the eye icon to show it again.' : 'هذا القسم مخفي من ملف السيرة عند التصدير. يمكنك إظهاره من أيقونة العين.' }}</p>
    @endif
</section>

{{-- خبرات --}}
<section class="mb-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
    <div class="mb-2 flex items-start justify-between gap-2">
        <h2 class="text-lg font-bold text-gray-900">{{ $L['experience'] ?? 'الخبرات' }}</h2>
        <div class="flex shrink-0 items-center gap-0.5">
            @include('portal.competency.partials.cv-visibility-toggle', ['visible' => $p?->cvSectionVisible('experience'), 'toggle' => 'experience', 'cvLocale' => $cvLocale])
            <x-portal.cv-edit-dropdown :edit-title="$tEdit">
            <form method="POST" action="{{ route('portal.competency.update') }}" class="space-y-4">
                @csrf @method('PATCH')
                <input type="hidden" name="section" value="experience" />
                <div id="exp-rows" class="space-y-4">
                    @foreach ($expItems as $i => $row)
                    @php
                        $wm = CvFormOptions::normalizeWorkMode((string) ($row['type'] ?? 'on_site'));
                        $em = CvFormOptions::normalizeEmployment((string) ($row['employment_type'] ?? 'participation'));
                    @endphp
                    <div data-cv-row class="rounded-xl border border-gray-200 bg-white p-4">
                        <div class="mb-2 flex justify-between"><button type="button" class="cv-remove-row text-xs text-red-600 hover:underline">حذف</button></div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2"><label class="text-xs text-gray-500">المسمى / العنوان</label><input type="text" name="experience_items[{{ $i }}][title]" value="{{ $row['title'] ?? '' }}" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" /></div>
                            <div class="sm:col-span-2"><label class="text-xs text-gray-500">الجهة / المنظمة</label><input type="text" name="experience_items[{{ $i }}][organization]" value="{{ $row['organization'] ?? '' }}" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" /></div>
                            <div>
                                <label class="text-xs text-gray-500">نوع الحضور</label>
                                <select name="experience_items[{{ $i }}][type]" class="mt-0.5 w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-sm">
                                    @foreach (CvFormOptions::WORK_MODE_KEYS as $wk)
                                    <option value="{{ $wk }}" @selected($wm === $wk)>{{ CvFormOptions::workModeLabel($wk, $cvLocale) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">نوع التوظيف / المشاركة</label>
                                <select name="experience_items[{{ $i }}][employment_type]" class="mt-0.5 w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-sm">
                                    @foreach (CvFormOptions::EMPLOYMENT_KEYS as $ek)
                                    <option value="{{ $ek }}" @selected($em === $ek)>{{ CvFormOptions::employmentLabel($ek, $cvLocale) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div><label class="text-xs text-gray-500">تاريخ البداية</label><input type="date" name="experience_items[{{ $i }}][start_date]" value="{{ $row['start_date'] ?? '' }}" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" /></div>
                            <div><label class="text-xs text-gray-500">تاريخ النهاية</label><input type="date" name="experience_items[{{ $i }}][end_date]" value="{{ $row['end_date'] ?? '' }}" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" /></div>
                            <div class="sm:col-span-2 flex items-center gap-2">
                                <input type="hidden" name="experience_items[{{ $i }}][is_current]" value="0" />
                                <input type="checkbox" name="experience_items[{{ $i }}][is_current]" value="1" class="rounded border-gray-300" @checked(!empty($row['is_current'])) />
                                <span class="text-sm">ما زلت على رأس العمل / المشاركة</span>
                            </div>
                            <div class="sm:col-span-2"><label class="text-xs text-gray-500">وصف</label><textarea name="experience_items[{{ $i }}][description]" rows="2" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm">{{ $row['description'] ?? '' }}</textarea></div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <button type="button" id="add-exp-row" class="text-xs font-semibold text-[#253B5B] hover:underline">+ إضافة خبرة</button>
                <script type="text/template" id="exp-tpl">
                    <div data-cv-row class="rounded-xl border border-gray-200 bg-white p-4">
                        <div class="mb-2 flex justify-between"><button type="button" class="cv-remove-row text-xs text-red-600 hover:underline">حذف</button></div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2"><label class="text-xs text-gray-500">المسمى / العنوان</label><input type="text" name="experience_items[__IDX__][title]" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" /></div>
                            <div class="sm:col-span-2"><label class="text-xs text-gray-500">الجهة / المنظمة</label><input type="text" name="experience_items[__IDX__][organization]" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" /></div>
                            <div><label class="text-xs text-gray-500">نوع الحضور</label><select name="experience_items[__IDX__][type]" class="mt-0.5 w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-sm">@foreach (CvFormOptions::WORK_MODE_KEYS as $wk)<option value="{{ $wk }}">{{ CvFormOptions::workModeLabel($wk, $cvLocale) }}</option>@endforeach</select></div>
                            <div><label class="text-xs text-gray-500">نوع التوظيف / المشاركة</label><select name="experience_items[__IDX__][employment_type]" class="mt-0.5 w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-sm">@foreach (CvFormOptions::EMPLOYMENT_KEYS as $ek)<option value="{{ $ek }}">{{ CvFormOptions::employmentLabel($ek, $cvLocale) }}</option>@endforeach</select></div>
                            <div><label class="text-xs text-gray-500">تاريخ البداية</label><input type="date" name="experience_items[__IDX__][start_date]" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" /></div>
                            <div><label class="text-xs text-gray-500">تاريخ النهاية</label><input type="date" name="experience_items[__IDX__][end_date]" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" /></div>
                            <div class="sm:col-span-2 flex items-center gap-2"><input type="hidden" name="experience_items[__IDX__][is_current]" value="0" /><input type="checkbox" name="experience_items[__IDX__][is_current]" value="1" class="rounded border-gray-300" /><span class="text-sm">ما زلت على رأس العمل / المشاركة</span></div>
                            <div class="sm:col-span-2"><label class="text-xs text-gray-500">وصف</label><textarea name="experience_items[__IDX__][description]" rows="2" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm"></textarea></div>
                        </div>
                    </div>
                </script>
                <div class="flex justify-end"><button type="submit" class="rounded-xl px-6 py-2 text-sm font-semibold text-white" style="background:#253B5B">{{ $cvLocale === 'en' ? 'Save' : 'حفظ الخبرات' }}</button></div>
            </form>
            </x-portal.cv-edit-dropdown>
        </div>
    </div>
    @if ($p?->cvSectionVisible('experience'))
    @if (count($mergedTimeline) > 0)
    <div class="mb-4 space-y-4 border-r-2 border-emerald-600/40 pr-4">
        @foreach ($mergedTimeline as $ex)
        <div class="rounded-xl bg-[#F8FAFC] p-4 ring-1 ring-gray-100">
            <p class="font-bold text-gray-900">{{ $ex['title'] }} @if(filled($ex['organization']))<span class="font-normal text-gray-600">— {{ $ex['organization'] }}</span>@endif</p>
            <p class="mt-1 text-xs text-gray-500">
                <span class="rounded bg-white px-2 py-0.5 ring-1 ring-gray-100">{{ CvFormOptions::employmentLabel((string) ($ex['employment_type'] ?? ''), $cvLocale) }}</span>
                <span class="rounded bg-white px-2 py-0.5 ring-1 ring-gray-100">{{ CvFormOptions::workModeLabel((string) ($ex['type'] ?? ''), $cvLocale) }}</span>
                @if (($ex['source'] ?? '') === 'platform_volunteer')
                <span class="rounded bg-white px-2 py-0.5 ring-1 ring-gray-100">{{ $cvLocale === 'en' ? 'Platform' : 'من المنصة' }}</span>
                @endif
            </p>
            <p class="mt-1 text-xs text-gray-500">@if (!empty($ex['is_current'])) {{ $ex['start_date'] ?? '' }} — الآن @else {{ $ex['start_date'] ?? '' }} — {{ $ex['end_date'] ?? '' }} @endif</p>
            @if (filled($ex['description']))<p class="mt-2 whitespace-pre-wrap text-sm text-gray-700">{{ $ex['description'] }}</p>@endif
        </div>
        @endforeach
    </div>
    @else
    <p class="{{ $emptyBox }} {{ $cvLocale === 'en' ? 'text-left' : 'text-right' }}">{{ $cvLocale === 'en' ? 'No roles or volunteering to show yet. Add experience via Edit or complete volunteering on the platform.' : 'لا توجد خبرات أو تطوع يظهر هنا بعد. أضف خبراتك من «'.$tEdit.'» أو أكمل تطوعاً على المنصة.' }}</p>
    @endif
    @else
    <p class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">{{ $cvLocale === 'en' ? 'This section is hidden from your exported CV. Use the eye icon to show it again.' : 'هذا القسم مخفي من ملف السيرة عند التصدير. يمكنك إظهاره من أيقونة العين.' }}</p>
    @endif
</section>
</div>

{{-- دورات وشهادات (يشمل دمج شهادات البرامج من المنصة في التصدير) --}}
<section class="mb-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
    <div class="mb-2 flex items-start justify-between gap-2">
        <h2 class="text-lg font-bold text-gray-900">{{ $L['courses'] ?? 'الدورات والشهادات' }}</h2>
        <div class="flex shrink-0 items-center gap-0.5">
            @include('portal.competency.partials.cv-visibility-toggle', ['visible' => $p?->cvSectionVisible('external_courses'), 'toggle' => 'external_courses', 'cvLocale' => $cvLocale])
            <x-portal.cv-edit-dropdown :edit-title="$tEdit">
            <form method="POST" action="{{ route('portal.competency.update') }}" class="space-y-4">
                @csrf @method('PATCH')
                <input type="hidden" name="section" value="external_courses" />
                <div id="ext-rows" class="space-y-4">
                    @foreach ($extItems as $i => $row)
                    <div data-cv-row class="rounded-xl border border-gray-200 bg-white p-4">
                        <div class="mb-2 flex justify-between"><button type="button" class="cv-remove-row text-xs text-red-600 hover:underline">{{ $cvLocale === 'en' ? 'Remove' : 'حذف' }}</button></div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2"><label class="text-xs text-gray-500">{{ $cvLocale === 'en' ? 'Course or certificate title' : 'عنوان الدورة / الشهادة' }}</label><input type="text" name="external_course_items[{{ $i }}][title]" value="{{ $row['title'] ?? '' }}" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" /></div>
                            <div><label class="text-xs text-gray-500">{{ $cvLocale === 'en' ? 'Provider' : 'الجهة المقدّمة' }}</label><input type="text" name="external_course_items[{{ $i }}][provider]" value="{{ $row['provider'] ?? '' }}" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" /></div>
                            <div><label class="text-xs text-gray-500">{{ $cvLocale === 'en' ? 'Date' : 'التاريخ' }}</label><input type="text" name="external_course_items[{{ $i }}][date]" value="{{ $row['date'] ?? '' }}" placeholder="2025-03" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" dir="ltr" /></div>
                            <div class="sm:col-span-2"><label class="text-xs text-gray-500">{{ $cvLocale === 'en' ? 'Certificate URL (optional)' : 'رابط الشهادة (اختياري)' }}</label><input type="text" name="external_course_items[{{ $i }}][certificate_url]" value="{{ $row['certificate_url'] ?? '' }}" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" dir="ltr" /></div>
                            <div class="sm:col-span-2"><label class="text-xs text-gray-500">{{ $cvLocale === 'en' ? 'Short description' : 'وصف مختصر' }}</label><textarea name="external_course_items[{{ $i }}][description]" rows="2" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm">{{ $row['description'] ?? '' }}</textarea></div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <button type="button" id="add-ext-row" class="text-xs font-semibold text-[#253B5B] hover:underline">{{ $cvLocale === 'en' ? '+ Add course' : '+ إضافة دورة' }}</button>
                <script type="text/template" id="ext-tpl">
                    <div data-cv-row class="rounded-xl border border-gray-200 bg-white p-4">
                        <div class="mb-2 flex justify-between"><button type="button" class="cv-remove-row text-xs text-red-600 hover:underline">{{ $cvLocale === 'en' ? 'Remove' : 'حذف' }}</button></div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2"><label class="text-xs text-gray-500">{{ $cvLocale === 'en' ? 'Course or certificate title' : 'عنوان الدورة / الشهادة' }}</label><input type="text" name="external_course_items[__IDX__][title]" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" /></div>
                            <div><label class="text-xs text-gray-500">{{ $cvLocale === 'en' ? 'Provider' : 'الجهة المقدّمة' }}</label><input type="text" name="external_course_items[__IDX__][provider]" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" /></div>
                            <div><label class="text-xs text-gray-500">{{ $cvLocale === 'en' ? 'Date' : 'التاريخ' }}</label><input type="text" name="external_course_items[__IDX__][date]" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" dir="ltr" /></div>
                            <div class="sm:col-span-2"><label class="text-xs text-gray-500">{{ $cvLocale === 'en' ? 'Certificate URL (optional)' : 'رابط الشهادة (اختياري)' }}</label><input type="text" name="external_course_items[__IDX__][certificate_url]" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm" dir="ltr" /></div>
                            <div class="sm:col-span-2"><label class="text-xs text-gray-500">{{ $cvLocale === 'en' ? 'Short description' : 'وصف مختصر' }}</label><textarea name="external_course_items[__IDX__][description]" rows="2" class="mt-0.5 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm"></textarea></div>
                        </div>
                    </div>
                </script>
                <div class="flex justify-end"><button type="submit" class="rounded-xl px-6 py-2 text-sm font-semibold text-white" style="background:#253B5B">{{ $cvLocale === 'en' ? 'Save' : 'حفظ' }}</button></div>
            </form>
            </x-portal.cv-edit-dropdown>
        </div>
    </div>
    @if ($p?->cvSectionVisible('external_courses'))
    @if (count($mergedCoursesView) > 0)
    <div class="mb-4 grid gap-3 sm:grid-cols-1">
        @foreach ($mergedCoursesView as $c)
        <div class="rounded-xl border border-gray-100 bg-[#F8FAFC] p-4">
            <p class="font-semibold text-gray-900">{{ $c['title'] }}</p>
            <p class="text-sm text-gray-600">{{ $c['provider'] ?? '' }} @if(filled($c['date'])) · {{ $c['date'] }} @endif</p>
            @if (($c['source'] ?? '') === 'platform_program')
            <span class="mt-1 inline-block rounded bg-white px-2 py-0.5 text-[10px] font-bold text-slate-600 ring-1 ring-gray-100">{{ $cvLocale === 'en' ? 'Platform' : 'من المنصة' }}</span>
            @endif
            @if (filled($c['certificate_url']))<a href="{{ $c['certificate_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-block text-xs font-semibold text-[#253B5B] hover:underline">{{ $cvLocale === 'en' ? 'Certificate link' : 'رابط الشهادة' }}</a>@endif
            @if (filled($c['description']))<p class="mt-2 text-xs text-gray-700">{{ $c['description'] }}</p>@endif
        </div>
        @endforeach
    </div>
    @else
    <p class="{{ $emptyBox }} {{ $cvLocale === 'en' ? 'text-left' : 'text-right' }}">{{ $cvLocale === 'en' ? 'No courses yet. Add external training via Edit; platform certificates appear when you complete programs.' : 'لا توجد دورات أو شهادات بعد. أضف دوراتك من «'.$tEdit.'»؛ وتظهر شهادات برامج المنصة عند إكمالها.' }}</p>
    @endif
    @else
    <p class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">{{ $cvLocale === 'en' ? 'This section is hidden from your exported CV. Use the eye icon to show it again.' : 'هذا القسم مخفي من ملف السيرة عند التصدير. يمكنك إظهاره من أيقونة العين.' }}</p>
    @endif
</section>

{{-- روابط --}}
<section class="mb-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
    <div class="mb-2 flex items-start justify-between gap-2">
        <h2 class="text-lg font-bold text-gray-900">{{ $L['links'] ?? 'روابط مهمة' }}</h2>
        <div class="flex shrink-0 items-center gap-0.5">
            @include('portal.competency.partials.cv-visibility-toggle', ['visible' => $p?->cvSectionVisible('links'), 'toggle' => 'links', 'cvLocale' => $cvLocale])
            <x-portal.cv-edit-dropdown :edit-title="$tEdit">
            <form method="POST" action="{{ route('portal.competency.update') }}" class="space-y-3">
                @csrf @method('PATCH')
                <input type="hidden" name="section" value="links" />
                <table class="w-full text-sm">
                    <thead><tr class="text-right text-xs text-gray-500"><th class="pb-2">{{ $cvLocale === 'en' ? 'Label' : 'التسمية' }}</th><th class="pb-2">{{ $cvLocale === 'en' ? 'URL' : 'الرابط' }}</th><th class="pb-2">{{ $cvLocale === 'en' ? 'Type' : 'النوع' }}</th><th class="w-10"></th></tr></thead>
                    <tbody id="link-rows">
                        @foreach ($linkRows as $i => $row)
                        <tr data-cv-row class="border-t border-gray-100">
                            <td class="py-2 pe-2"><input type="text" name="link_items[{{ $i }}][label]" value="{{ $row['label'] ?? '' }}" class="w-full rounded-lg border border-gray-300 px-2 py-1.5" /></td>
                            <td class="py-2 pe-2"><input type="text" name="link_items[{{ $i }}][url]" value="{{ $row['url'] ?? '' }}" class="w-full rounded-lg border border-gray-300 px-2 py-1.5" dir="ltr" /></td>
                            <td class="py-2 pe-2">
                                <select name="link_items[{{ $i }}][type]" class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5">
                                    <option value="">—</option>
                                    @foreach (CvFormOptions::LINK_TYPES as $lt)
                                    <option value="{{ $lt }}" @selected(($row['type'] ?? '') === $lt)>{{ $linkTypeLabels[$lt] ?? $lt }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-2"><button type="button" class="cv-remove-row text-xs text-red-600 hover:underline">{{ $cvLocale === 'en' ? 'Remove' : 'حذف' }}</button></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <button type="button" id="add-link-row" class="text-xs font-semibold text-[#253B5B] hover:underline">{{ $cvLocale === 'en' ? '+ Add link' : '+ إضافة رابط' }}</button>
                <script type="text/template" id="link-tpl">
                    <tr data-cv-row class="border-t border-gray-100">
                        <td class="py-2 pe-2"><input type="text" name="link_items[__IDX__][label]" class="w-full rounded-lg border border-gray-300 px-2 py-1.5" /></td>
                        <td class="py-2 pe-2"><input type="text" name="link_items[__IDX__][url]" class="w-full rounded-lg border border-gray-300 px-2 py-1.5" dir="ltr" /></td>
                        <td class="py-2 pe-2"><select name="link_items[__IDX__][type]" class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5"><option value="">—</option>@foreach (CvFormOptions::LINK_TYPES as $lt)<option value="{{ $lt }}">{{ $linkTypeLabels[$lt] ?? $lt }}</option>@endforeach</select></td>
                        <td class="py-2"><button type="button" class="cv-remove-row text-xs text-red-600 hover:underline">{{ $cvLocale === 'en' ? 'Remove' : 'حذف' }}</button></td>
                    </tr>
                </script>
                <div class="flex justify-end"><button type="submit" class="rounded-xl px-6 py-2 text-sm font-semibold text-white" style="background:#253B5B">{{ $cvLocale === 'en' ? 'Save' : 'حفظ الروابط' }}</button></div>
            </form>
            </x-portal.cv-edit-dropdown>
        </div>
    </div>
    @if ($p?->cvSectionVisible('links'))
    @if (count($p?->cvLinksList() ?? []) > 0)
    <ul class="mb-4 space-y-2 text-right">
        @foreach ($p->cvLinksList() as $link)
        <li>
            <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" class="text-sm font-semibold text-[#253B5B] hover:underline">{{ $link['label'] }}</a>
            @if (!empty($link['type']))<span class="me-2 text-xs text-gray-400">({{ $linkTypeLabels[$link['type']] ?? $link['type'] }})</span>@endif
        </li>
        @endforeach
    </ul>
    @else
    <p class="{{ $emptyBox }} {{ $cvLocale === 'en' ? 'text-left' : 'text-right' }}">{{ $cvLocale === 'en' ? 'No links yet. Add portfolio, LinkedIn, or other links via Edit.' : 'لا توجد روابط بعد. أضف روابط مثل لينكدإن أو معرض أعمالك من «'.$tEdit.'».' }}</p>
    @endif
    @else
    <p class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">{{ $cvLocale === 'en' ? 'This section is hidden from your exported CV. Use the eye icon to show it again.' : 'هذا القسم مخفي من ملف السيرة عند التصدير. يمكنك إظهاره من أيقونة العين.' }}</p>
    @endif
</section>

{{-- مرفق السيرة --}}
<section class="mb-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
    <div class="mb-2 flex items-start justify-between gap-2">
        <h2 class="text-lg font-bold text-gray-900">{{ $cvLocale === 'en' ? 'CV file attachment' : 'مرفق السيرة الذاتية' }}</h2>
        <x-portal.cv-edit-dropdown :edit-title="$tEdit">
        <form method="POST" action="{{ route('portal.competency.update') }}" enctype="multipart/form-data" class="space-y-3">
            @csrf @method('PATCH')
            <input type="hidden" name="section" value="cv_attachment" />
            <input type="file" name="cv" required accept=".pdf,.doc,.docx" class="w-full text-sm file:me-3 file:rounded-lg file:border-0 file:bg-[#EAF2FA] file:px-4 file:py-2 file:font-semibold file:text-[#253B5B]" />
            <div class="flex justify-end"><button type="submit" class="rounded-xl px-6 py-2 text-sm font-semibold text-white" style="background:#253B5B">{{ $cvLocale === 'en' ? 'Upload' : 'رفع' }}</button></div>
        </form>
        </x-portal.cv-edit-dropdown>
    </div>
    @if ($p?->cv_path)
    <p class="mb-3 text-sm"><a href="{{ asset('storage/'.$p->cv_path) }}" target="_blank" class="font-semibold text-[#253B5B] hover:underline">{{ $cvLocale === 'en' ? 'Download current file' : 'تحميل الملف الحالي' }}</a></p>
    @else
    <p class="{{ $emptyBox }} {{ $cvLocale === 'en' ? 'text-left' : 'text-right' }}">{{ $cvLocale === 'en' ? 'No CV file uploaded yet. Upload a PDF or Word file via Edit.' : 'لم يُرفع ملف سيرة ذاتية بعد. يمكنك رفع PDF أو Word من «'.$tEdit.'».' }}</p>
    @endif
</section>

<div id="cv-builder-meta" class="hidden" data-skill-count="{{ count($skillItems) }}" data-lang-count="{{ count($langItems) }}" data-tool-count="{{ count($toolItems) }}" data-link-count="{{ count($linkRows) }}" data-edu-count="{{ count($eduItems) }}" data-exp-count="{{ count($expItems) }}" data-ext-count="{{ count($extItems) }}"></div>
<script>
(function() {
    var meta = document.getElementById('cv-builder-meta');
    if (!meta) return;
    var skillIndex = parseInt(meta.dataset.skillCount, 10) || 0;
    var langIndex = parseInt(meta.dataset.langCount, 10) || 0;
    var toolIndex = parseInt(meta.dataset.toolCount, 10) || 0;
    var linkIndex = parseInt(meta.dataset.linkCount, 10) || 0;
    var eduIndex = parseInt(meta.dataset.eduCount, 10) || 0;
    var expIndex = parseInt(meta.dataset.expCount, 10) || 0;
    var extIndex = parseInt(meta.dataset.extCount, 10) || 0;

    function addRow(btnId, tplId, containerId, indexRef) {
        var btn = document.getElementById(btnId);
        var tpl = document.getElementById(tplId);
        var container = document.getElementById(containerId);
        if (!btn || !tpl || !container) return;
        btn.addEventListener('click', function() {
            var raw = (tpl.textContent || tpl.innerHTML || '').trim();
            var html = raw.replace(/__IDX__/g, String(indexRef.v));
            indexRef.v++;
            container.insertAdjacentHTML('beforeend', html);
        });
    }

    addRow('add-skill-row', 'skill-tpl', 'skill-rows', { v: skillIndex });
    addRow('add-lang-row', 'lang-tpl', 'lang-rows', { v: langIndex });
    addRow('add-tool-row', 'tool-tpl', 'tool-rows', { v: toolIndex });
    addRow('add-link-row', 'link-tpl', 'link-rows', { v: linkIndex });
    addRow('add-edu-row', 'edu-tpl', 'edu-rows', { v: eduIndex });
    addRow('add-exp-row', 'exp-tpl', 'exp-rows', { v: expIndex });
    addRow('add-ext-row', 'ext-tpl', 'ext-rows', { v: extIndex });

    document.addEventListener('click', function(e) {
        var del = e.target.closest('.cv-remove-row');
        if (!del) return;
        e.preventDefault();
        var row = del.closest('[data-cv-row]');
        if (!row || !row.parentNode) return;
        var siblings = row.parentNode.querySelectorAll('[data-cv-row]');
        if (siblings.length <= 1) return;
        row.remove();
    });

    function syncLangCustomFields(root) {
        var scope = (root && root.querySelectorAll) ? root : document;
        scope.querySelectorAll('.lang-code-select').forEach(function(sel) {
            var r = sel.closest('[data-cv-row]');
            if (!r) return;
            var wrap = r.querySelector('.lang-custom-field');
            if (wrap) wrap.classList.toggle('hidden', sel.value !== 'custom');
        });
    }
    syncLangCustomFields(document.getElementById('lang-rows'));
    document.addEventListener('change', function(e) {
        if (!e.target.classList || !e.target.classList.contains('lang-code-select')) return;
        var r = e.target.closest('[data-cv-row]');
        if (!r) return;
        var wrap = r.querySelector('.lang-custom-field');
        if (wrap) wrap.classList.toggle('hidden', e.target.value !== 'custom');
    });
    var langBtn = document.getElementById('add-lang-row');
    if (langBtn) {
        langBtn.addEventListener('click', function() {
            setTimeout(function() {
                var lr = document.getElementById('lang-rows');
                if (lr) syncLangCustomFields(lr);
            }, 0);
        });
    }
})();
</script>
