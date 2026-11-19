@php
    use App\Enums\OpportunityStatus;
    use App\Support\RichContentSupport;

    $isEdit = $opportunity !== null;
    $descriptionIsRich = $isEdit && filled($opportunity->description) && RichContentSupport::isRichContent($opportunity->description);
    $statusLabels = [
        OpportunityStatus::Published->value => 'منشورة',
        OpportunityStatus::Draft->value => 'مسودة',
        OpportunityStatus::Archived->value => 'مؤرشفة',
    ];
    $tones = [
        OpportunityStatus::Published->value => 'success',
        OpportunityStatus::Draft->value => 'muted',
        OpportunityStatus::Archived->value => 'warning',
    ];
    $status = $opportunity?->status?->value ?? OpportunityStatus::Draft->value;
    $actionButtons = [
        'publish' => ['label' => 'نشر الفرصة', 'icon' => 'send', 'variant' => 'primary', 'confirm' => null],
        'unpublish' => ['label' => 'إرجاع إلى المسودات', 'icon' => 'eye-off', 'variant' => 'secondary', 'confirm' => 'ستختفي الفرصة من الموقع وتصبح مسودة. هل تريد المتابعة؟'],
        'archive' => ['label' => 'أرشفة الفرصة', 'icon' => 'archive', 'variant' => 'secondary', 'confirm' => 'ستنتقل الفرصة إلى الأرشيف وتختفي من الموقع. هل تريد المتابعة؟'],
        'restore' => ['label' => 'استعادة كمسودة', 'icon' => 'archive-restore', 'variant' => 'secondary', 'confirm' => null],
    ];
    $dateValue = static fn ($date): ?string => $date?->toDateString();
@endphp

<x-staff-ui.layout :name="$staffName" :email="$staffEmail" crumb="الفرص التطوعية" :dashboard-active="false" active-nav="volunteering">
    <header class="sui-page-head sui-staff-head">
        <div class="sui-vol-head">
            <h1>{{ $isEdit ? $opportunity->title : 'إضافة فرصة تطوعية' }}</h1>
            @if ($isEdit)
                <x-staff-ui.status :tone="$tones[$status] ?? 'muted'">{{ $statusLabels[$status] ?? $status }}</x-staff-ui.status>
            @endif
        </div>
        <div class="sui-vol-head__actions">
            @if ($isEdit && $status === OpportunityStatus::Published->value)
                <x-staff-ui.button variant="secondary" size="sm" :href="route('public.volunteering.show', $opportunity)" target="_blank" icon="external-link">عرض في الموقع</x-staff-ui.button>
            @endif
            <x-staff-ui.button variant="secondary" size="sm" :href="route('staff-ui.volunteering.index')" icon="arrow-right">القائمة</x-staff-ui.button>
        </div>
    </header>

    @if (session('status'))
        <p class="sui-alert sui-program-alert" role="status">{{ session('status') }}</p>
    @endif

    <div class="sui-vol-stack">
    <section class="sui-card sui-settings-section">
        <div class="sui-card__head">
            <h2>بيانات الفرصة</h2>
            <p>التسجيل في الفرصة يتم عبر المنصة الوطنية للعمل التطوعي؛ وزر «سجّل في الفرصة» في الموقع يفتح الرابط المُدخل هنا.</p>
        </div>
        <form class="sui-wizard" data-sui-vol-form method="POST" action="{{ $isEdit ? route('staff-ui.volunteering.update', $opportunity) : route('staff-ui.volunteering.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="sui-form-grid">
                <x-staff-ui.input class="sui-span-2" name="title" label="العنوان" :value="old('title', $opportunity?->title)" required maxlength="255" :error="$errors->first('title')" />
                <x-staff-ui.input
                    class="sui-span-2"
                    type="url"
                    name="external_url"
                    label="رابط الفرصة في المنصة الوطنية للعمل التطوعي"
                    placeholder="https://"
                    dir="ltr"
                    :value="old('external_url', $opportunity?->external_url)"
                    hint="يُفتح هذا الرابط في نافذة جديدة عند ضغط المستفيد على «سجّل في الفرصة». وإذا تُرك فارغاً تظهر عبارة «سيُتاح رابط التسجيل قريباً»."
                    :error="$errors->first('external_url')"
                />
                <div class="sui-span-2">
                <x-staff-ui.textarea
                    name="description"
                    label="الوصف"
                    rows="6"
                    :hint="$descriptionIsRich ? 'الوصف الحالي منسّق، ويبقى كما هو ما لم يُعدَّل النص هنا؛ وعند تعديله يُحفظ نصاً عادياً.' : null"
                    :error="$errors->first('description')"
                >{{ old('description', RichContentSupport::toEditableText($opportunity?->description)) }}</x-staff-ui.textarea>
                </div>
                <x-staff-ui.input type="number" name="capacity" label="السعة (عدد المتطوعين)" min="1" step="1" inputmode="numeric" :value="old('capacity', $opportunity?->capacity)" hint="اختياري. يظهر في صفحة الفرصة للعلم فقط." :error="$errors->first('capacity')" />
                <x-staff-ui.input type="number" name="hours_expected" label="الساعات المتوقعة" min="0" step="0.5" inputmode="decimal" :value="old('hours_expected', $opportunity?->hours_expected !== null ? rtrim(rtrim((string) $opportunity->hours_expected, '0'), '.') : null)" hint="اختياري. للعلم فقط، ولا تُحتسب الساعات في المنصة." :error="$errors->first('hours_expected')" />
                <x-staff-ui.date name="start_date" label="تاريخ البداية" :value="old('start_date', $dateValue($opportunity?->start_date))" :error="$errors->first('start_date')" />
                <x-staff-ui.date name="end_date" label="تاريخ النهاية" :value="old('end_date', $dateValue($opportunity?->end_date))" :error="$errors->first('end_date')" />
                <div class="sui-span-2">
                    <x-staff-ui.field label="صورة الفرصة" hint="JPEG أو PNG أو WebP، بحد أقصى 5 ميجابايت." :error="$errors->first('image')">
                        <div class="sui-vol-image-actions">
                            <label class="sui-btn sui-btn--secondary sui-btn--sm">
                                اختيار صورة
                                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-sui-cover hidden>
                            </label>
                            <span data-sui-cover-name></span>
                            @if ($isEdit && filled($opportunity->image))
                                <x-staff-ui.checkbox name="remove_image" value="1" label="إزالة الصورة الحالية" />
                            @endif
                        </div>
                    </x-staff-ui.field>
                    <img class="sui-wizard-cover" alt="معاينة الصورة" data-sui-cover-preview @if ($isEdit && filled($opportunity->image)) src="{{ $opportunity->imagePublicUrl() }}" @else hidden @endif>
                </div>
            </div>

            <x-staff-ui.toggle name="notify_on_publish" value="1" label="إشعار المستفيدين عند النشر" :checked="(bool) old('notify_on_publish', $opportunity?->notify_on_publish ?? true)" />
            <p class="sui-wizard-hint">يصل إشعار في صندوق المستفيدين عند نشر الفرصة.</p>

            @unless ($isEdit)
                @if ($canPublish)
                    <x-staff-ui.toggle name="publish" value="1" label="نشر الفرصة الآن" :checked="(bool) old('publish', false)" />
                @else
                    <p class="sui-wizard-hint">تُحفظ الفرصة كمسودة؛ فالنشر يتطلب صلاحية نشر الفرص التطوعية.</p>
                @endif
            @endunless

            <div class="sui-form-actions">
                <x-staff-ui.button type="submit" icon="save">{{ $isEdit ? 'حفظ التعديلات' : 'إضافة الفرصة' }}</x-staff-ui.button>
            </div>
        </form>
    </section>

    @if ($isEdit)
        <section class="sui-card sui-settings-section">
            <div class="sui-card__head">
                <h2>النشر والأرشفة</h2>
                <p>
                    الحالة الحالية: <strong>{{ $statusLabels[$status] ?? $status }}</strong>
                    @if ($opportunity->published_at && $status === OpportunityStatus::Published->value)
                        — نُشرت في {{ en_digits(ar_date($opportunity->published_at->copy()->timezone(config('app.timezone')), 'd MMM y')) }}
                    @endif
                </p>
            </div>
            <div class="sui-settings-status">
                @forelse ($actions as $action)
                    @php $button = $actionButtons[$action]; @endphp
                    <form method="POST" action="{{ route('staff-ui.volunteering.status', $opportunity) }}" @if ($button['confirm']) onsubmit="return confirm(@js($button['confirm']));" @endif>
                        @csrf
                        <input type="hidden" name="action" value="{{ $action }}">
                        <x-staff-ui.button type="submit" :variant="$button['variant']" :icon="$button['icon']">{{ $button['label'] }}</x-staff-ui.button>
                    </form>
                @empty
                    <p class="sui-wizard-hint">تغيير حالة النشر يتطلب صلاحية النشر أو الأرشفة.</p>
                @endforelse
            </div>
        </section>

        @if ($canDelete)
            <section class="sui-card sui-settings-section sui-settings-danger">
                <div class="sui-card__head">
                    <h2>حذف الفرصة</h2>
                    <p>يُحذف إعلان الفرصة من الموقع ومن هذه القائمة نهائياً. وللإخفاء مع الاحتفاظ بالبيانات تُستخدم الأرشفة.</p>
                </div>
                <form method="POST" action="{{ route('staff-ui.volunteering.delete', $opportunity) }}" onsubmit="return confirm(@js('حذف «'.$opportunity->title.'» نهائياً؟ لا يمكن التراجع عن هذا الإجراء.'));">
                    @csrf
                    <x-staff-ui.button type="submit" variant="danger" icon="trash-2">حذف الفرصة</x-staff-ui.button>
                </form>
            </section>
        @endif
    @endif
    </div>

    <script>
        document.querySelectorAll('[data-sui-vol-form]').forEach((form) => {
            form.querySelector('[data-sui-cover]')?.addEventListener('change', (event) => {
                const file = event.target.files?.[0];
                const preview = form.querySelector('[data-sui-cover-preview]');
                const name = form.querySelector('[data-sui-cover-name]');
                if (!file || !preview) return;
                preview.hidden = false;
                preview.src = URL.createObjectURL(file);
                if (name) name.textContent = file.name;
            });
        });
    </script>
</x-staff-ui.layout>
