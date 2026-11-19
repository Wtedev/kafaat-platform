@php
    $descriptionIsRich = filled($program?->description) && \App\Support\RichContentSupport::isRichContent($program->description);
@endphp
<div class="sui-form-grid">
    <x-staff-ui.input name="title" label="العنوان" :value="old('title', $program?->title)" required :error="$errors->first('title')" />
    <x-staff-ui.select name="program_kind" label="النوع" :selected="old('program_kind', $program?->program_kind?->value)" :options="$kindOptions" />
    <x-staff-ui.select name="competency_track" label="مسار الكفاءة" :selected="old('competency_track', $program?->competency_track?->value)" :options="$trackOptions" />
    <x-staff-ui.select name="delivery_mode" label="أسلوب التنفيذ" :selected="old('delivery_mode', $program?->delivery_mode?->value)" :options="$deliveryOptions" />
    <x-staff-ui.input class="sui-span-2" name="venue" label="مكان الانعقاد" :value="old('venue', $program?->venue)" hint="مطلوب للحضوري والهايبرد، ويُفرَّغ عن بُعد." :error="$errors->first('venue')" />
    <x-staff-ui.textarea class="sui-span-2" name="description" label="النبذة" rows="5" :hint="$descriptionIsRich ? 'النبذة الحالية منسّقة. تبقى كما هي ما لم تعدّل النص هنا؛ وإن عدّلته تُحفظ كنص عادي.' : null">{{ old('description', \App\Support\RichContentSupport::toEditableText($program?->description)) }}</x-staff-ui.textarea>
    <div class="sui-span-2">
        <x-staff-ui.field label="الغلاف" hint="JPEG أو PNG أو WebP — حتى 5 ميجابايت." :error="$errors->first('image')">
            <label class="sui-btn sui-btn--secondary sui-btn--sm">
                اختيار صورة
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-sui-cover hidden>
            </label>
            <span data-sui-cover-name></span>
        </x-staff-ui.field>
        <img class="sui-wizard-cover" alt="معاينة الغلاف" data-sui-cover-preview @if ($program?->image) src="{{ $program->imagePublicUrl() }}" @else hidden @endif>
    </div>
</div>

@php
    $presenterRows = old('program_presenters', $program?->program_presenters ?? []);
    $presenterRows = is_array($presenterRows) && $presenterRows !== [] ? array_values($presenterRows) : [['name' => '', 'role' => '']];
    $topicRows = old('session_topics', $program?->session_topics ?? []);
    $topicRows = is_array($topicRows) && $topicRows !== [] ? array_values($topicRows) : [['title' => '', 'facilitators' => '']];
    $topicsOn = (bool) old('session_topics_enabled', $program?->session_topics_enabled);
@endphp

<div class="sui-wizard-repeat-head">
    <h2 class="sui-wizard-sub">المقدمون</h2>
    <button type="button" class="sui-btn sui-btn--secondary sui-btn--sm" data-sui-add="presenter">إضافة مقدم</button>
</div>
<div data-sui-rows="presenter">
    @foreach ($presenterRows as $i => $row)
        <div class="sui-wizard-repeat" data-sui-row="presenter">
            <div class="sui-form-grid">
                <x-staff-ui.input name="program_presenters[{{ $i }}][name]" label="الاسم" :value="$row['name'] ?? ''" :error="$errors->first('program_presenters.'.$i.'.name')" />
                <x-staff-ui.input name="program_presenters[{{ $i }}][role]" label="الصفة" :value="$row['role'] ?? ''" />
            </div>
            <button type="button" class="sui-btn sui-btn--ghost sui-btn--sm" data-sui-remove>حذف</button>
        </div>
    @endforeach
</div>

<div class="sui-wizard-repeat-head">
    <h2 class="sui-wizard-sub">المحاور</h2>
    <x-staff-ui.toggle name="session_topics_enabled" value="1" label="عرض المحاور" :checked="$topicsOn" />
</div>
<div data-sui-topics @if (! $topicsOn) hidden @endif>
    <div data-sui-rows="topic">
        @foreach ($topicRows as $i => $topic)
            <div class="sui-wizard-repeat" data-sui-row="topic">
                <div class="sui-form-grid">
                    <x-staff-ui.input name="session_topics[{{ $i }}][title]" label="المحور" :value="$topic['title'] ?? ''" />
                    <x-staff-ui.input name="session_topics[{{ $i }}][facilitators]" label="المسؤولون" :value="$topic['facilitators'] ?? ''" />
                </div>
                <button type="button" class="sui-btn sui-btn--ghost sui-btn--sm" data-sui-remove>حذف</button>
            </div>
        @endforeach
    </div>
    <button type="button" class="sui-btn sui-btn--secondary sui-btn--sm" data-sui-add="topic">إضافة محور</button>
</div>
