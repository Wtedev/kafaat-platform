@php
    use App\Enums\RegistrationStatus;
    use App\Services\StaffUi\StaffProgramRegistrations as Regs;

    $tabUrl = route('staff-ui.programs.tab', [$program, 'registrations']);
    $filterQuery = array_filter($filters, fn ($v) => $v !== '');
    $statusTone = [
        'pending' => 'warning',
        'approved' => 'success',
        'completed' => 'success',
        'rejected' => 'danger',
        'cancelled' => 'muted',
    ];
    $tz = config('app.timezone');
    $closed = $program->isRegistrationManuallyClosed();
    $total = $registrationRows->total();
    $previewUrl = route('staff-ui.programs.registrations.preview', $program);
@endphp

<section class="sui-card sui-reg-window">
    <div class="sui-reg-window__text">
        <span class="sui-reg-window__label">حالة التسجيل</span>
        <x-staff-ui.status :tone="$availability->tone()">{{ $availability->label() }}</x-staff-ui.status>
        @if ($closed)
            <span class="sui-reg-window__hint">أُغلق يدوياً {{ en_digits(ar_date($program->registration_closed_at->timezone($tz), 'd MMM y')) }}، ولا تُقبل طلبات جديدة.</span>
        @endif
    </div>
    <div class="sui-reg-window__actions">
        @if ($canExport)
            <x-staff-ui.button type="button" variant="secondary" size="sm" icon="download" data-sui-open-modal="reg-export" data-export-scope="all">تصدير</x-staff-ui.button>
        @endif
        @if ($canUpdate)
            <form method="POST" action="{{ route('staff-ui.programs.registrations.window', $program) }}" @unless ($closed) onsubmit="return confirm('لن يستطيع أحد التسجيل حتى تعيدي فتحه. إغلاق التسجيل الآن؟');" @endunless>
                @csrf
                <input type="hidden" name="action" value="{{ $closed ? 'reopen' : 'close' }}">
                <x-staff-ui.button type="submit" :variant="$closed ? 'primary' : 'secondary'" size="sm" :icon="$closed ? 'lock-open' : 'lock'">{{ $closed ? 'إعادة فتح التسجيل' : 'إغلاق التسجيل' }}</x-staff-ui.button>
            </form>
        @endif
    </div>
</section>

@if ($errors->has('registrations'))
    <p class="sui-alert sui-alert--danger" role="alert">{{ $errors->first('registrations') }}</p>
@endif

<section class="sui-card">
    <nav class="sui-reg-chips" aria-label="حالة الطلب">
        @foreach (Regs::STATUS_OPTIONS as $value => $label)
            <a href="{{ $tabUrl.'?'.http_build_query(array_filter(['status' => $value] + $filterQuery, fn ($v) => $v !== '')) }}"
               @class(['sui-reg-chip', 'is-active' => $filters['status'] === $value])>
                {{ $label }} <span class="sui-reg-chip__count">{{ en_num($statusCounts[$value] ?? 0) }}</span>
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ $tabUrl }}" class="sui-reg-filters">
        <input type="hidden" name="status" value="{{ $filters['status'] }}">
        <x-staff-ui.input name="q" label="بحث" placeholder="الاسم أو البريد أو الجوال" :value="$filters['q']" />
        <x-staff-ui.select name="nationality" label="الجنسية" :selected="$filters['nationality']" :options="Regs::NATIONALITY_OPTIONS" />
        <x-staff-ui.select name="gender" label="الجنس" :selected="$filters['gender']" :options="Regs::GENDER_OPTIONS" />
        <x-staff-ui.select name="age" label="العمر" :selected="$filters['age']" :options="Regs::AGE_OPTIONS" />
        <div class="sui-reg-filters__actions">
            <x-staff-ui.button type="submit" size="sm">تطبيق</x-staff-ui.button>
            @if (array_diff_key($filterQuery, ['status' => 1]) !== [])
                <x-staff-ui.button variant="ghost" size="sm" :href="$tabUrl.($filters['status'] !== '' ? '?status='.$filters['status'] : '')">مسح</x-staff-ui.button>
            @endif
        </div>
    </form>

    <form id="reg-bulk" method="POST" action="{{ route('staff-ui.programs.registrations.bulk', $program) }}" data-reg-bulk>
        @csrf
        @foreach ($filters as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <input type="hidden" name="action" value="" data-reg-action>
        <input type="hidden" name="all_matching" value="0" data-reg-all-matching>
        <input type="hidden" name="rejected_reason" value="" data-reg-bulk-reason>
        <div data-reg-export-columns></div>

        <div class="sui-reg-bulkbar" data-reg-bulkbar hidden>
            <span data-reg-selected-label></span>
            @if ($total > $registrationRows->count())
                <button type="button" class="sui-reg-link" data-reg-select-all-matching>تحديد كل النتائج ({{ en_num($total) }})</button>
            @endif
            <div class="sui-reg-bulkbar__actions">
                @if ($canApprove)
                    <x-staff-ui.button type="button" size="sm" icon="check" data-reg-bulk-open="approve">قبول المحدد</x-staff-ui.button>
                @endif
                @if ($canReject)
                    <x-staff-ui.button type="button" variant="secondary" size="sm" icon="x" data-reg-bulk-open="reject">رفض المحدد</x-staff-ui.button>
                @endif
                @if ($canExport)
                    <x-staff-ui.button type="button" variant="secondary" size="sm" icon="download" data-sui-open-modal="reg-export" data-export-scope="selected">تصدير المحدد</x-staff-ui.button>
                @endif
            </div>
        </div>

        @if ($registrationRows->isEmpty())
            <p class="sui-program-muted">لا توجد تسجيلات مطابقة.</p>
        @else
            <div class="sui-table-wrap">
                <table class="sui-table sui-reg-table">
                    <thead>
                        <tr>
                            <th class="sui-reg-table__check"><input type="checkbox" class="sui-check" data-reg-check-page aria-label="تحديد الصفحة"></th>
                            <th>المستفيد</th>
                            <th>الجنسية</th>
                            <th>الجنس</th>
                            <th>العمر</th>
                            <th>وقت التسجيل</th>
                            <th>الحالة</th>
                            <th aria-label="إجراءات"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($registrationRows as $row)
                            @php
                                $status = $row->status instanceof RegistrationStatus ? $row->status : RegistrationStatus::from((string) $row->status);
                                $at = $row->created_at?->timezone($tz);
                                $age = Regs::age($row);
                            @endphp
                            <tr>
                                <td class="sui-reg-table__check"><input type="checkbox" class="sui-check" name="ids[]" value="{{ $row->id }}" data-reg-check aria-label="تحديد {{ $row->user?->name }}"></td>
                                <td data-label="المستفيد">
                                    <a class="sui-reg-name" href="{{ route('staff-ui.users.show', $row->user_id) }}">{{ $row->user?->name ?? '—' }}</a>
                                    @if (is_array($row->registration_answers) && $row->registration_answers !== [])
                                        <span class="sui-reg-tag" title="أجاب على أسئلة التسجيل">إجابات</span>
                                    @endif
                                </td>
                                <td data-label="الجنسية">{{ Regs::nationalityLabel($row) }}</td>
                                <td data-label="الجنس">{{ Regs::genderLabel($row) }}</td>
                                <td data-label="العمر">{{ $age !== null ? en_num($age) : '—' }}</td>
                                <td data-label="وقت التسجيل" class="sui-reg-table__time">{{ $at ? en_digits(ar_date($at, 'd MMM y')).' — '.$at->format('H:i') : '—' }}</td>
                                <td data-label="الحالة"><x-staff-ui.status :tone="$statusTone[$status->value] ?? 'muted'">{{ $status === RegistrationStatus::Completed ? 'مقبول' : $status->label() }}</x-staff-ui.status></td>
                                <td class="sui-reg-table__actions">
                                    @if ($status === RegistrationStatus::Pending)
                                        @if ($canApprove)
                                            <button type="submit" form="reg-approve-{{ $row->id }}" class="sui-btn sui-btn--secondary sui-btn--sm">قبول</button>
                                        @endif
                                        @if ($canReject)
                                            <button type="button" class="sui-btn sui-btn--ghost sui-btn--sm"
                                                data-reg-reject-one="{{ route('staff-ui.programs.registrations.reject', [$program, $row]) }}"
                                                data-reg-id="{{ $row->id }}"
                                                data-reg-name="{{ $row->user?->name }}">رفض</button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </form>

    @foreach ($registrationRows as $row)
        @if ($canApprove && $row->status === RegistrationStatus::Pending)
            <form id="reg-approve-{{ $row->id }}" method="POST" action="{{ route('staff-ui.programs.registrations.approve', [$program, $row]) }}" hidden>
                @csrf
                @foreach ($filterQuery as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
            </form>
        @endif
    @endforeach

    @if ($registrationRows->hasPages())
        <div class="sui-pager">
            <span class="sui-pager__label">{{ en_num($registrationRows->firstItem() ?? 0) }}–{{ en_num($registrationRows->lastItem() ?? 0) }} من {{ en_num($total) }}</span>
            @foreach ([
                ['url' => $registrationRows->previousPageUrl(), 'enabled' => ! $registrationRows->onFirstPage(), 'icon' => 'chevron-right', 'label' => 'السابق'],
                ['url' => $registrationRows->nextPageUrl(), 'enabled' => $registrationRows->hasMorePages(), 'icon' => 'chevron-left', 'label' => 'التالي'],
            ] as $pager)
                @if ($pager['enabled'])
                    <a class="sui-icon-btn" href="{{ $pager['url'] }}" aria-label="{{ $pager['label'] }}"><i data-lucide="{{ $pager['icon'] }}" class="sui-icon"></i></a>
                @else
                    <span class="sui-icon-btn" aria-disabled="true" aria-label="{{ $pager['label'] }}"><i data-lucide="{{ $pager['icon'] }}" class="sui-icon"></i></span>
                @endif
            @endforeach
        </div>
    @endif
</section>

{{-- Bulk approve / reject: shows the email that will go out before confirming. --}}
<div class="sui-modal" data-sui-modal="reg-bulk-confirm" hidden>
    <div class="sui-modal__backdrop" data-sui-modal-close></div>
    <div class="sui-modal__panel sui-reg-modal" role="dialog" aria-modal="true" aria-labelledby="reg-bulk-title">
        <h3 class="sui-modal__title" id="reg-bulk-title" data-reg-modal-title></h3>
        <p class="sui-modal__text" data-reg-modal-summary></p>
        <div data-reg-reason-box hidden>
            <x-staff-ui.textarea name="modal_reason" label="سبب الرفض (اختياري، يظهر في الإيميل)" rows="3" data-reg-reason-input></x-staff-ui.textarea>
        </div>
        <p class="sui-reg-preview__label">الإيميل اللي بيوصل <span data-reg-preview-who></span>:</p>
        <iframe class="sui-reg-preview" title="معاينة الإيميل" data-reg-preview sandbox></iframe>
        <div class="sui-modal__actions">
            <button type="button" class="sui-btn sui-btn--secondary sui-btn--md" data-sui-modal-close>إلغاء</button>
            <button type="button" class="sui-btn sui-btn--primary sui-btn--md" data-reg-modal-confirm>تأكيد وإرسال</button>
        </div>
    </div>
</div>

<form method="POST" action="" data-reg-reject-form hidden>
    @csrf
    @foreach ($filterQuery as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
    <input type="hidden" name="rejected_reason" value="" data-reg-reject-reason>
</form>

@if ($canExport)
    <x-staff-ui.modal name="reg-export" title="تصدير المسجلين (Excel)">
        <p data-reg-export-scope-label>كل النتائج المطابقة للتصفية الحالية ({{ en_num($total) }}).</p>
        <div class="sui-reg-export">
            @foreach ($exportColumns as $group => $columns)
                <fieldset>
                    <legend>{{ $exportGroupTitles[$group] ?? $group }}</legend>
                    @foreach ($columns as $key => $label)
                        <x-staff-ui.checkbox :label="$label" value="{{ $key }}" :checked="in_array($key, $exportDefaults, true)" data-reg-export-col />
                    @endforeach
                </fieldset>
            @endforeach
        </div>
        <x-slot:actions>
            <button type="button" class="sui-btn sui-btn--secondary sui-btn--md" data-sui-modal-close>إلغاء</button>
            <button type="button" class="sui-btn sui-btn--primary sui-btn--md" data-reg-export-submit>تنزيل الملف</button>
        </x-slot:actions>
    </x-staff-ui.modal>
    <form method="GET" action="{{ route('staff-ui.programs.registrations.export', $program) }}" data-reg-export-all hidden>
        @foreach ($filterQuery as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
    </form>
@endif

<script>
(() => {
    const bulk = document.querySelector('[data-reg-bulk]');
    if (!bulk) return;
    const checks = () => [...bulk.querySelectorAll('[data-reg-check]')];
    const bar = bulk.querySelector('[data-reg-bulkbar]');
    const label = bulk.querySelector('[data-reg-selected-label]');
    const allMatching = bulk.querySelector('[data-reg-all-matching]');
    const pageBox = bulk.querySelector('[data-reg-check-page]');
    const total = {{ (int) $total }};
    const previewBase = @json($previewUrl);
    let exportScope = 'all';

    const selectedIds = () => checks().filter((c) => c.checked).map((c) => c.value);
    const count = () => allMatching.value === '1' ? total : selectedIds().length;
    const refresh = () => {
        const n = count();
        bar.hidden = n === 0;
        label.textContent = allMatching.value === '1' ? `كل النتائج محددة (${n})` : `${n} محدد`;
    };

    pageBox?.addEventListener('change', () => {
        checks().forEach((c) => { c.checked = pageBox.checked; });
        allMatching.value = '0';
        refresh();
    });
    bulk.addEventListener('change', (e) => {
        if (e.target.matches('[data-reg-check]')) { allMatching.value = '0'; refresh(); }
    });
    bulk.querySelector('[data-reg-select-all-matching]')?.addEventListener('click', () => {
        checks().forEach((c) => { c.checked = true; });
        if (pageBox) pageBox.checked = true;
        allMatching.value = '1';
        refresh();
    });

    // Bulk approve / reject with email preview.
    const modal = document.querySelector('[data-sui-modal="reg-bulk-confirm"]');
    const frame = modal.querySelector('[data-reg-preview]');
    const reasonBox = modal.querySelector('[data-reg-reason-box]');
    const reasonInput = modal.querySelector('[data-reg-reason-input]');
    let mode = null;
    let single = null;

    const loadPreview = (kind, id) => {
        const params = new URLSearchParams({ kind });
        if (id) params.set('registration', id);
        if (kind === 'reject' && reasonInput.value.trim()) params.set('rejected_reason', reasonInput.value.trim());
        frame.src = `${previewBase}?${params}`;
    };

    const open = (kind, opts = {}) => {
        mode = kind;
        single = opts.single || null;
        const n = single ? 1 : count();
        modal.querySelector('[data-reg-modal-title]').textContent = kind === 'approve' ? 'قبول التسجيلات' : (single ? `رفض ${opts.name}` : 'رفض التسجيلات');
        modal.querySelector('[data-reg-modal-summary]').textContent = single
            ? 'سيُرسل له إيميل الاعتذار مباشرة بعد التأكيد.'
            : (kind === 'approve'
                ? `سيُقبل الطلبات المعلّقة ضمن ${n} محدداً، ويُرسل لكل مقبول إيميل القبول. يُحترم حد السعة وشروط القبول.`
                : `سيُرفض الطلبات المعلّقة ضمن ${n} محدداً، ويُرسل لكل واحد إيميل الاعتذار.`);
        modal.querySelector('[data-reg-preview-who]').textContent = single ? `إلى ${opts.name}` : 'لكل واحد (مثال من أول طلب)';
        reasonBox.hidden = kind !== 'reject';
        reasonInput.value = '';
        const firstId = single ? single.id : (selectedIds()[0] || @json($previewTarget));
        loadPreview(kind, firstId);
        modal._previewId = firstId;
        modal.hidden = false;
    };

    let reasonTimer = 0;
    reasonInput?.addEventListener('input', () => {
        clearTimeout(reasonTimer);
        reasonTimer = setTimeout(() => loadPreview('reject', modal._previewId), 400);
    });

    bulk.querySelectorAll('[data-reg-bulk-open]').forEach((btn) => btn.addEventListener('click', () => open(btn.dataset.regBulkOpen)));
    document.querySelectorAll('[data-reg-reject-one]').forEach((btn) => btn.addEventListener('click', () => {
        open('reject', { single: { id: btn.dataset.regId, url: btn.dataset.regRejectOne }, name: btn.dataset.regName });
    }));

    modal.querySelector('[data-reg-modal-confirm]').addEventListener('click', () => {
        if (single) {
            const form = document.querySelector('[data-reg-reject-form]');
            form.action = single.url;
            form.querySelector('[data-reg-reject-reason]').value = reasonInput.value;
            form.submit();
            return;
        }
        bulk.querySelector('[data-reg-action]').value = mode;
        bulk.querySelector('[data-reg-bulk-reason]').value = mode === 'reject' ? reasonInput.value : '';
        bulk.submit();
    });

    // Export: all matching results, or the selection.
    document.querySelectorAll('[data-export-scope]').forEach((btn) => btn.addEventListener('click', () => {
        exportScope = btn.dataset.exportScope;
        const scopeLabel = document.querySelector('[data-reg-export-scope-label]');
        if (scopeLabel) scopeLabel.textContent = exportScope === 'selected' ? `الصفوف المحددة (${count()}).` : `كل النتائج المطابقة للتصفية الحالية (${total}).`;
    }));
    document.querySelector('[data-reg-export-submit]')?.addEventListener('click', () => {
        const cols = [...document.querySelectorAll('[data-reg-export-col]:checked')].map((c) => c.value);
        const target = exportScope === 'selected' ? bulk : document.querySelector('[data-reg-export-all]');
        const holder = exportScope === 'selected' ? bulk.querySelector('[data-reg-export-columns]') : target;
        target.querySelectorAll('input[name="columns[]"]').forEach((i) => i.remove());
        cols.forEach((value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'columns[]';
            input.value = value;
            holder.append(input);
        });
        if (exportScope === 'selected') bulk.querySelector('[data-reg-action]').value = 'export';
        document.querySelector('[data-sui-modal="reg-export"]').hidden = true;
        target.submit();
        if (exportScope === 'selected') bulk.querySelector('[data-reg-action]').value = '';
    });

    refresh();
})();
</script>
