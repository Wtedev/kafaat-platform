@php
    use App\Enums\AttendanceLinkMode;

    $tz = config('app.timezone');
@endphp

@if ($errors->hasAny(['name', 'open_minutes', 'mode', 'attendance']))
    <p class="sui-alert sui-alert--danger" role="alert">{{ $errors->first() }}</p>
@endif

<section class="sui-card">
    <div class="sui-card__head sui-card__head--row">
        <div>
            <h2>أيام التحضير</h2>
            <p>كل يوم تحضير له رابط: رابط مسح QR لمسؤول التحضير في الحضوري، أو رابط يُرسل للمشاركين في عن بُعد.</p>
        </div>
        <x-staff-ui.dropdown align="end">
            <x-slot:trigger>
                <x-staff-ui.button type="button" size="sm" icon="plus">إضافة</x-staff-ui.button>
            </x-slot:trigger>
            @foreach ($linkModes as $mode)
                <button type="button" class="sui-menu-item sui-att-mode" data-att-add="{{ $mode->value }}" data-att-add-label="{{ $mode->label() }}">
                    <i data-lucide="{{ $mode === AttendanceLinkMode::Qr ? 'qr-code' : 'link' }}" class="sui-icon"></i>
                    <span>
                        <strong>{{ $mode->label() }}</strong>
                        <small>{{ $mode->description() }}</small>
                    </span>
                </button>
            @endforeach
        </x-staff-ui.dropdown>
    </div>

    @if ($attendanceLinks->isEmpty())
        <p class="sui-program-muted">لا توجد أيام تحضير بعد. اضغط «إضافة» واختر النوع.</p>
    @else
        <div class="sui-table-wrap">
            <table class="sui-table sui-att-table">
                <thead>
                    <tr>
                        <th>اليوم</th>
                        <th>النوع</th>
                        <th>مدة الرابط</th>
                        <th>الحالة</th>
                        <th>الحاضرون</th>
                        <th>الرابط</th>
                        <th aria-label="إجراءات"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($attendanceLinks as $link)
                        @php
                            $open = $link->isOpen();
                            [$stateLabel, $stateTone] = match (true) {
                                $open => ['مفتوح حتى '.$link->closes_at?->timezone($tz)->format('H:i'), 'success'],
                                $link->opens_at === null => ['لم يُفتح بعد', 'warning'],
                                default => ['أُغلق '.en_digits(ar_date($link->closes_at?->timezone($tz), 'd MMM')).' '.$link->closes_at?->timezone($tz)->format('H:i'), 'muted'],
                            };
                        @endphp
                        <tr>
                            <td data-label="اليوم"><strong>{{ $link->name }}</strong></td>
                            <td data-label="النوع">
                                <span @class(['sui-att-mode-badge', 'is-qr' => $link->isQr()])>
                                    <i data-lucide="{{ $link->isQr() ? 'qr-code' : 'link' }}" class="sui-icon sui-icon--sm"></i>
                                    {{ $link->mode?->label() }}
                                </span>
                            </td>
                            <td data-label="مدة الرابط" class="sui-att-count">{{ en_num($link->openMinutes()) }} دقيقة</td>
                            <td data-label="الحالة"><x-staff-ui.status :tone="$stateTone">{{ $stateLabel }}</x-staff-ui.status></td>
                            <td data-label="الحاضرون" class="sui-att-count">{{ en_num($link->marks_count) }}</td>
                            <td data-label="الرابط">
                                <button type="button" class="sui-btn sui-btn--secondary sui-btn--sm" data-copy="{{ $link->shareUrl() }}" title="{{ $link->shareUrl() }}">
                                    <i data-lucide="copy" class="sui-icon sui-icon--sm"></i>
                                    <span>{{ $link->isQr() ? 'نسخ رابط مسح التحضير' : 'نسخ رابط المشاركين' }}</span>
                                </button>
                            </td>
                            <td class="sui-reg-table__actions">
                                <form method="POST" action="{{ route('staff-ui.programs.attendance.action', [$program, $link]) }}" class="sui-inline-form">
                                    @csrf
                                    <input type="hidden" name="action" value="{{ $open ? 'close' : 'open' }}">
                                    <button type="submit" @class(['sui-btn', 'sui-btn--sm', 'sui-btn--primary' => ! $open, 'sui-btn--secondary' => $open])>{{ $open ? 'إغلاق' : 'فتح' }}</button>
                                </form>
                                <button type="button" class="sui-btn sui-btn--ghost sui-btn--sm" aria-label="تعديل {{ $link->name }}"
                                    data-att-edit="{{ route('staff-ui.programs.attendance.update', [$program, $link]) }}"
                                    data-name="{{ $link->name }}"
                                    data-minutes="{{ $link->openMinutes() }}"><i data-lucide="pencil" class="sui-icon sui-icon--sm"></i></button>
                                <form method="POST" action="{{ route('staff-ui.programs.attendance.action', [$program, $link]) }}" class="sui-inline-form" onsubmit="return confirm('حذف «{{ $link->name }}»؟ يبقى الحضور المسجّل فيه محفوظاً.');">
                                    @csrf
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="sui-btn sui-btn--ghost sui-btn--sm" aria-label="حذف {{ $link->name }}"><i data-lucide="trash-2" class="sui-icon sui-icon--sm"></i></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<x-staff-ui.modal name="att-add" title="إضافة يوم تحضير">
    <form method="POST" action="{{ route('staff-ui.programs.attendance.store', $program) }}" class="sui-att-form" id="att-add-form">
        @csrf
        <input type="hidden" name="mode" value="" data-att-mode>
        <p class="sui-att-form__type" data-att-mode-label></p>
        <x-staff-ui.input name="name" label="اسم يوم التحضير" placeholder="مثلاً: اليوم الأول" required />
        <x-staff-ui.input type="number" name="open_minutes" label="مدة الرابط (دقيقة)" value="10" min="1" max="720" required hint="يمكن تعديلها بعد الإنشاء." />
        <x-staff-ui.checkbox name="open_now" value="1" label="افتح التحضير الآن" checked />
    </form>
    <x-slot:actions>
        <button type="button" class="sui-btn sui-btn--secondary sui-btn--md" data-sui-modal-close>إلغاء</button>
        <button type="submit" form="att-add-form" class="sui-btn sui-btn--primary sui-btn--md">إضافة</button>
    </x-slot:actions>
</x-staff-ui.modal>

<x-staff-ui.modal name="att-edit" title="تعديل يوم التحضير">
    <form method="POST" action="" class="sui-att-form" id="att-edit-form">
        @csrf
        <x-staff-ui.input id="att-edit-name" name="name" label="اسم يوم التحضير" required />
        <x-staff-ui.input id="att-edit-minutes" type="number" name="open_minutes" label="مدة الرابط (دقيقة)" min="1" max="720" required hint="إذا كان التحضير مفتوحاً، يتغيّر وقت إغلاقه مباشرة." />
    </form>
    <x-slot:actions>
        <button type="button" class="sui-btn sui-btn--secondary sui-btn--md" data-sui-modal-close>إلغاء</button>
        <button type="submit" form="att-edit-form" class="sui-btn sui-btn--primary sui-btn--md">حفظ</button>
    </x-slot:actions>
</x-staff-ui.modal>

<script>
(() => {
    const addModal = document.querySelector('[data-sui-modal="att-add"]');
    document.querySelectorAll('[data-att-add]').forEach((item) => item.addEventListener('click', () => {
        addModal.querySelector('[data-att-mode]').value = item.dataset.attAdd;
        addModal.querySelector('[data-att-mode-label]').textContent = 'النوع: ' + item.dataset.attAddLabel;
        item.closest('[data-sui-dropdown]')?.querySelector('.sui-dropdown__menu')?.setAttribute('hidden', '');
        addModal.hidden = false;
        addModal.querySelector('input[name="name"]').focus();
    }));

    const editModal = document.querySelector('[data-sui-modal="att-edit"]');
    document.querySelectorAll('[data-att-edit]').forEach((button) => button.addEventListener('click', () => {
        const form = editModal.querySelector('form');
        form.action = button.dataset.attEdit;
        form.querySelector('[name="name"]').value = button.dataset.name;
        form.querySelector('[name="open_minutes"]').value = button.dataset.minutes;
        editModal.hidden = false;
    }));

    document.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', async () => {
        const label = button.querySelector('span');
        const original = label.textContent;
        try {
            await navigator.clipboard.writeText(button.dataset.copy);
            label.textContent = 'نُسخ ✓';
        } catch (e) {
            window.prompt('انسخ الرابط:', button.dataset.copy);
        }
        setTimeout(() => { label.textContent = original; }, 1500);
    }));
})();
</script>
