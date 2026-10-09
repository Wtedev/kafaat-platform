@php
    use App\Enums\ProfileGender;
    use App\Enums\ProgramDeliveryMode;
    use App\Enums\TrainingProgramKind;

    $isSession = ($program?->program_kind ?? null) === TrainingProgramKind::Session
        || old('program_kind') === TrainingProgramKind::Session->value;
    $action = $program
        ? route('staff-ui.programs.wizard.store', [$program, $step])
        : route('staff-ui.programs.wizard.store-new');
    $genders = old('acceptance_genders', $program?->acceptance_conditions['genders'] ?? []);
    $genders = is_array($genders) ? $genders : [];
    $showMale = $genders === [] || in_array(ProfileGender::Male->value, $genders, true);
    $showFemale = $genders === [] || in_array(ProfileGender::Female->value, $genders, true);
    $capacityMode = old('capacity_mode', $program?->capacity_male || $program?->capacity_female ? 'per_gender' : ($program?->capacity ? 'shared' : 'unlimited'));
    $steps = [
        1 => 'البيانات الأساسية',
        2 => 'الجدول',
        3 => 'القبول',
        4 => 'الرسالة والمجموعات',
        5 => 'النشر',
        6 => 'المراجعة',
    ];
@endphp

<x-staff-ui.layout :name="$staffName" :email="$staffEmail" crumb="البرامج" :dashboard-active="false" active-nav="programs">
    <header class="sui-page-head sui-staff-head">
        <h1>إضافة برنامج</h1>
        <x-staff-ui.button variant="secondary" size="sm" :href="route('staff-ui.programs.index')" icon="arrow-right">القائمة</x-staff-ui.button>
    </header>

    @if (session('status'))
        <p class="sui-wizard-status">{{ session('status') }}</p>
    @endif

    <ol class="sui-wizard-steps">
        @foreach ($steps as $number => $label)
            <li @class(['is-current' => $step === $number])>
                @if ($program && $number !== $step)
                    <button form="program-wizard" type="submit" name="goto" value="{{ $number }}">{{ $number }}. {{ $label }}</button>
                @else
                    <span>{{ $number }}. {{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>

    <form id="program-wizard" class="sui-card sui-wizard" method="POST" action="{{ $action }}" enctype="multipart/form-data" @if ($program) data-sui-acceptance-url="{{ route('staff-ui.programs.wizard.acceptance-preview', $program) }}" @endif>
        @csrf

        @if ($step === 1)
            <div class="sui-form-grid">
                <x-staff-ui.input name="title" label="العنوان" :value="old('title', $program?->title)" required :error="$errors->first('title')" />
                <x-staff-ui.select name="program_kind" label="النوع" :selected="old('program_kind', $program?->program_kind?->value)" :options="$kindOptions" />
                <x-staff-ui.select name="competency_track" label="مسار الكفاءة" :selected="old('competency_track', $program?->competency_track?->value)" :options="$trackOptions" />
                <x-staff-ui.select name="delivery_mode" label="أسلوب التنفيذ" :selected="old('delivery_mode', $program?->delivery_mode?->value)" :options="$deliveryOptions" />
                <x-staff-ui.input class="sui-span-2" name="venue" label="مكان الانعقاد" :value="old('venue', $program?->venue)" hint="مطلوب للحضوري والهايبرد، ويُفرَّغ عن بُعد." :error="$errors->first('venue')" />
                <x-staff-ui.textarea class="sui-span-2" name="description" label="النبذة" rows="5">{{ old('description', $program?->description) }}</x-staff-ui.textarea>
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
        @endif

        @if ($step === 2)
            <div class="sui-form-grid">
                <x-staff-ui.input type="date" name="start_date" label="تاريخ البداية" :value="old('start_date', $program?->start_date?->toDateString())" :error="$errors->first('start_date')" />
                @unless ($isSession)
                    <x-staff-ui.input type="date" name="end_date" label="تاريخ النهاية" :value="old('end_date', $program?->end_date?->toDateString())" :error="$errors->first('end_date')" />
                @endunless
                <x-staff-ui.input type="date" name="registration_start" label="بداية التسجيل" :value="old('registration_start', $program?->registration_start?->toDateString())" />
                <x-staff-ui.input type="date" name="registration_end" label="نهاية التسجيل" :value="old('registration_end', $program?->registration_end?->toDateString())" :error="$errors->first('registration_end')" />
            </div>
            @unless ($isSession)
                <h2 class="sui-wizard-sub">أيام الأسبوع</h2>
                <div class="sui-wizard-days">
                    @foreach ($weekdayOptions as $value => $label)
                        <x-staff-ui.checkbox
                            name="weekdays[]"
                            :value="$value"
                            :label="$label"
                            :checked="in_array($value, array_map('intval', old('weekdays', $program?->weekdays ?? [])), true)"
                        />
                    @endforeach
                </div>
            @endunless
        @endif

        @if ($step === 3)
            <x-staff-ui.toggle name="acceptance_require_saudi_national" value="1" label="سعوديون فقط" :checked="(bool) old('acceptance_require_saudi_national', $program?->acceptance_conditions['require_saudi_national'] ?? false)" />
            <h2 class="sui-wizard-sub">الجنس</h2>
            <p class="sui-wizard-hint">اترك الاختيار فارغاً لقبول الجميع.</p>
            @foreach ($genderOptions as $value => $label)
                <x-staff-ui.checkbox name="acceptance_genders[]" :value="$value" :label="$label" :checked="in_array($value, $genders, true)" />
            @endforeach
            <div class="sui-form-grid">
                <x-staff-ui.input type="number" name="acceptance_min_age" label="الحد الأدنى للعمر" min="0" max="120" :value="old('acceptance_min_age', $program?->acceptance_conditions['min_age'] ?? '')" />
                <x-staff-ui.input type="number" name="acceptance_max_age" label="الحد الأقصى للعمر" min="0" max="120" :value="old('acceptance_max_age', $program?->acceptance_conditions['max_age'] ?? '')" :error="$errors->first('acceptance_max_age')" />
            </div>
            <x-staff-ui.select name="capacity_mode" label="السعة" :selected="$capacityMode" :options="['unlimited' => 'بلا حد', 'shared' => 'سعة واحدة', 'per_gender' => 'سعة لكل جنس']" />
            <div data-sui-capacity="shared" @if ($capacityMode !== 'shared') hidden @endif>
                <x-staff-ui.input type="number" name="capacity" label="السعة" min="1" :value="old('capacity', $program?->capacity)" :error="$errors->first('capacity')" />
            </div>
            <div data-sui-capacity="male" @if ($capacityMode !== 'per_gender' || ! $showMale) hidden @endif>
                <x-staff-ui.input type="number" name="capacity_male" label="سعة الرجال" min="1" :value="old('capacity_male', $program?->capacity_male)" :error="$errors->first('capacity_male')" />
            </div>
            <div data-sui-capacity="female" @if ($capacityMode !== 'per_gender' || ! $showFemale) hidden @endif>
                <x-staff-ui.input type="number" name="capacity_female" label="سعة النساء" min="1" :value="old('capacity_female', $program?->capacity_female)" :error="$errors->first('capacity_female')" />
            </div>
            <x-staff-ui.toggle name="auto_accept_registrations" value="1" label="قبول تلقائي لمن يستوفي الشروط حتى امتلاء السعة" :checked="(bool) old('auto_accept_registrations', $program?->auto_accept_registrations)" />
            <h2 class="sui-wizard-sub">كما ستظهر للمستفيد</h2>
            <ul data-sui-acceptance-preview>
                @foreach ($acceptancePreview as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        @endif

        @if ($step === 4)
            <x-staff-ui.textarea name="approval_message" label="رسالة القبول" rows="6" hint="اتركها فارغة للرسالة الافتراضية.">{{ old('approval_message', $program?->approval_message) }}</x-staff-ui.textarea>
            <x-staff-ui.toggle name="whatsapp_groups_enabled" value="1" label="مجموعات التواصل" :checked="(bool) old('whatsapp_groups_enabled', $program?->whatsapp_groups_enabled)" />
            @if ($showMale)
                <x-staff-ui.input name="whatsapp_group_male" label="رابط مجموعة الرجال" :value="old('whatsapp_group_male', $program?->whatsapp_group_male)" hint="يبدأ بـ https://" :error="$errors->first('whatsapp_group_male')" />
            @endif
            @if ($showFemale)
                <x-staff-ui.input name="whatsapp_group_female" label="رابط مجموعة النساء" :value="old('whatsapp_group_female', $program?->whatsapp_group_female)" hint="يبدأ بـ https://" :error="$errors->first('whatsapp_group_female')" />
            @endif
            <x-staff-ui.button type="submit" form="program-wizard" variant="secondary" size="sm" formaction="{{ route('staff-ui.programs.wizard.preview-mail', $program) }}">أرسل نسخة تجريبية لي</x-staff-ui.button>
        @endif

        @if ($step === 5)
            <x-staff-ui.toggle name="publish" value="1" label="نشر البرنامج الآن" :checked="$publishIntent" />
            @unless ($canPublish)
                <p class="sui-wizard-hint">النشر يحتاج صلاحية نشر البرامج. يمكنك حفظه كمسودة.</p>
            @endunless
            <x-staff-ui.toggle name="notify_audience" value="1" label="إشعارات المستفيدين" :checked="$notifyIntent" />
            <p class="sui-wizard-hint">يضبط إشعار النشر، وإشعار المحطات، وإشعار المسجّلين عند التحديث معاً.</p>
        @endif

        @if ($step === 6)
            @php
                $presenterSummary = collect(is_array($program->program_presenters) ? $program->program_presenters : [])
                    ->map(function (mixed $row): string {
                        if (! is_array($row)) {
                            return '';
                        }
                        $name = trim((string) ($row['name'] ?? ''));
                        $role = trim((string) ($row['role'] ?? ''));

                        return $name === '' ? '' : ($role !== '' ? $name.' — '.$role : $name);
                    })
                    ->filter()
                    ->implode('، ');
                $topicSummary = $program->session_topics_enabled
                    ? collect(is_array($program->session_topics) ? $program->session_topics : [])
                        ->map(function (mixed $row): string {
                            if (! is_array($row)) {
                                return '';
                            }
                            $title = trim((string) ($row['title'] ?? ''));
                            $who = trim((string) ($row['facilitators'] ?? ''));

                            return $title === '' ? '' : ($who !== '' ? $title.' — '.$who : $title);
                        })
                        ->filter()
                        ->implode('، ')
                    : '';
                $groupSummary = $program->whatsapp_groups_enabled
                    ? collect([
                        filled($program->whatsapp_group_male) ? 'رجال: '.$program->whatsapp_group_male : null,
                        filled($program->whatsapp_group_female) ? 'نساء: '.$program->whatsapp_group_female : null,
                    ])->filter()->implode('، ')
                    : 'غير مفعّلة';
            @endphp
            <input type="hidden" name="publish" value="{{ $publishIntent ? '1' : '0' }}">
            <dl class="sui-wizard-summary">
                <div><dt>العنوان</dt><dd>{{ $program->title }}</dd></div>
                <div><dt>النوع</dt><dd>{{ $program->program_kind?->label() }}</dd></div>
                <div><dt>مسار الكفاءة</dt><dd>{{ $program->competency_track?->shortLabel() }}</dd></div>
                <div><dt>أسلوب التنفيذ</dt><dd>{{ $program->delivery_mode?->label() }}</dd></div>
                <div><dt>المكان</dt><dd>{{ $program->venue ?: '—' }}</dd></div>
                <div><dt>المقدمون</dt><dd>{{ $presenterSummary !== '' ? $presenterSummary : '—' }}</dd></div>
                <div><dt>المحاور</dt><dd>{{ $topicSummary !== '' ? $topicSummary : '—' }}</dd></div>
                <div><dt>البداية</dt><dd>{{ $program->start_date?->toDateString() ?: '—' }}</dd></div>
                <div><dt>النهاية</dt><dd>{{ $program->end_date?->toDateString() ?: '—' }}</dd></div>
                <div><dt>التسجيل</dt><dd>{{ $program->registration_start?->toDateString() ?: '—' }} – {{ $program->registration_end?->toDateString() ?: '—' }}</dd></div>
                <div><dt>رسالة القبول</dt><dd>{{ filled($program->approval_message) ? trim(strip_tags((string) $program->approval_message)) : 'الرسالة الافتراضية' }}</dd></div>
                <div><dt>روابط المجموعات</dt><dd>{{ $groupSummary !== '' ? $groupSummary : '—' }}</dd></div>
                <div><dt>النشر</dt><dd>{{ $publishIntent ? 'منشور' : 'مسودة' }}</dd></div>
                <div><dt>الإشعارات</dt><dd>{{ $notifyIntent ? 'مفعّلة' : 'متوقفة' }}</dd></div>
            </dl>
            <h2 class="sui-wizard-sub">شروط القبول</h2>
            <ul>
                @forelse ($acceptancePreview as $line)
                    <li>{{ $line }}</li>
                @empty
                    <li>بلا شروط إضافية</li>
                @endforelse
            </ul>
            <div class="sui-form-actions">
                @foreach ([1 => 'البيانات', 2 => 'الجدول', 3 => 'القبول', 4 => 'الرسالة', 5 => 'النشر'] as $number => $label)
                    <x-staff-ui.button variant="secondary" size="sm" :href="route('staff-ui.programs.wizard', [$program, $number])">تعديل {{ $label }}</x-staff-ui.button>
                @endforeach
                <x-staff-ui.button variant="secondary" size="sm" :href="route('staff-ui.programs.wizard.preview', $program)" target="_blank">معاينة الصفحة العامة</x-staff-ui.button>
            </div>
        @endif

        <div class="sui-form-actions">
            @if ($program && $step > 1)
                <x-staff-ui.button type="submit" name="goto" value="{{ $step - 1 }}" variant="secondary">السابق</x-staff-ui.button>
            @endif
            @if ($step < 6)
                <x-staff-ui.button type="submit">التالي</x-staff-ui.button>
            @else
                <x-staff-ui.button type="submit">حفظ البرنامج</x-staff-ui.button>
            @endif
        </div>
    </form>

    <template id="sui-presenter-template">
        <div class="sui-wizard-repeat" data-sui-row="presenter">
            <div class="sui-form-grid">
                <label class="sui-field"><span class="sui-field__label">الاسم</span><input class="sui-control" data-field="name"></label>
                <label class="sui-field"><span class="sui-field__label">الصفة</span><input class="sui-control" data-field="role"></label>
            </div>
            <button type="button" class="sui-btn sui-btn--secondary sui-btn--sm" data-sui-remove>حذف</button>
        </div>
    </template>
    <template id="sui-topic-template">
        <div class="sui-wizard-repeat" data-sui-row="topic">
            <div class="sui-form-grid">
                <label class="sui-field"><span class="sui-field__label">المحور</span><input class="sui-control" data-field="title"></label>
                <label class="sui-field"><span class="sui-field__label">المسؤولون</span><input class="sui-control" data-field="facilitators"></label>
            </div>
            <button type="button" class="sui-btn sui-btn--secondary sui-btn--sm" data-sui-remove>حذف</button>
        </div>
    </template>
    <script>
        document.querySelector('[data-sui-cover]')?.addEventListener('change', (event) => {
            const file = event.target.files?.[0];
            const preview = document.querySelector('[data-sui-cover-preview]');
            const name = document.querySelector('[data-sui-cover-name]');
            if (!file || !preview) return;
            preview.hidden = false;
            preview.src = URL.createObjectURL(file);
            if (name) name.textContent = file.name;
        });

        const wizardForm = document.getElementById('program-wizard');

        function reindexRows(kind, prefix) {
            document.querySelectorAll(`[data-sui-row="${kind}"]`).forEach((row, index) => {
                row.querySelectorAll('[data-field], [name]').forEach((input) => {
                    const field = input.dataset.field || (input.name?.match(/\[(\w+)\]$/) || [])[1];
                    if (!field || !input.name && !input.dataset.field) return;
                    if (field) input.name = `${prefix}[${index}][${field}]`;
                });
            });
        }

        document.querySelectorAll('[data-sui-add]').forEach((button) => {
            button.addEventListener('click', () => {
                const kind = button.dataset.suiAdd;
                const template = document.getElementById(kind === 'presenter' ? 'sui-presenter-template' : 'sui-topic-template');
                const host = document.querySelector(`[data-sui-rows="${kind}"]`);
                host?.append(template.content.cloneNode(true));
                reindexRows('presenter', 'program_presenters');
                reindexRows('topic', 'session_topics');
            });
        });

        wizardForm?.addEventListener('click', (event) => {
            const remove = event.target.closest('[data-sui-remove]');
            if (!remove) return;
            remove.closest('[data-sui-row]')?.remove();
            reindexRows('presenter', 'program_presenters');
            reindexRows('topic', 'session_topics');
        });

        const topicsBox = document.querySelector('[data-sui-topics]');
        wizardForm?.querySelector('[name="session_topics_enabled"]')?.addEventListener('change', (event) => {
            if (topicsBox) topicsBox.hidden = !event.target.checked;
        });

        function syncCapacityFields() {
            const mode = wizardForm?.querySelector('[name="capacity_mode"]')?.value || 'unlimited';
            const genders = [...(wizardForm?.querySelectorAll('[name="acceptance_genders[]"]:checked') || [])].map((input) => input.value);
            const showMale = genders.length === 0 || genders.includes('male');
            const showFemale = genders.length === 0 || genders.includes('female');
            const shared = document.querySelector('[data-sui-capacity="shared"]');
            const male = document.querySelector('[data-sui-capacity="male"]');
            const female = document.querySelector('[data-sui-capacity="female"]');
            if (shared) shared.hidden = mode !== 'shared';
            if (male) male.hidden = mode !== 'per_gender' || !showMale;
            if (female) female.hidden = mode !== 'per_gender' || !showFemale;
            [shared, male, female].forEach((box) => {
                box?.querySelectorAll('input').forEach((input) => {
                    input.disabled = box.hidden;
                });
            });
        }

        wizardForm?.querySelector('[name="capacity_mode"]')?.addEventListener('change', syncCapacityFields);
        wizardForm?.querySelectorAll('[name="acceptance_genders[]"]').forEach((input) => {
            input.addEventListener('change', syncCapacityFields);
        });
        syncCapacityFields();

        const previewList = document.querySelector('[data-sui-acceptance-preview]');
        const previewUrl = wizardForm?.dataset.suiAcceptanceUrl;
        let previewTimer = 0;
        async function refreshAcceptancePreview() {
            if (!previewList || !previewUrl || !wizardForm) return;
            const response = await fetch(previewUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(wizardForm),
            });
            if (!response.ok) return;
            const payload = await response.json();
            previewList.replaceChildren(...(payload.lines || []).map((line) => {
                const item = document.createElement('li');
                item.textContent = line;
                return item;
            }));
        }
        function scheduleAcceptancePreview() {
            window.clearTimeout(previewTimer);
            previewTimer = window.setTimeout(refreshAcceptancePreview, 150);
        }
        if (previewList && previewUrl) {
            wizardForm.addEventListener('input', scheduleAcceptancePreview);
            wizardForm.addEventListener('change', scheduleAcceptancePreview);
            refreshAcceptancePreview();
        }
    </script>
</x-staff-ui.layout>
