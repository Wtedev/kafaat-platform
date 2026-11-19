@php
    use App\Services\StaffUi\StaffProgramCertificates as Certs;

    $tz = config('app.timezone');
    $tabUrl = route('staff-ui.programs.tab', [$program, 'certificates']);
    $keep = array_filter(['q' => $certSearch, 'page' => $certRows->currentPage() > 1 ? $certRows->currentPage() : null], fn ($v) => filled($v));
    $time = fn ($at) => $at instanceof \Carbon\CarbonInterface
        ? en_digits(ar_date($at->copy()->timezone($tz), 'd MMM')).' '.$at->copy()->timezone($tz)->format('H:i')
        : null;
    $canSelect = $certCanIssue && $certBackgroundReady;
@endphp

@if ($errors->any())
    <p class="sui-alert sui-alert--danger" role="alert">{{ $errors->first() }}</p>
@endif

<section class="sui-card">
    <div class="sui-card__head sui-card__head--row">
        <div>
            <h2>الشهادات</h2>
            <p>كل متدرب مقبول: وقت تحضيره في كل يوم، واستبياناته، وشهادته. اختاري من تصدر لهم الشهادة.</p>
        </div>
        <x-staff-ui.button variant="secondary" size="sm" icon="pen-tool" :href="$certDesignerUrl">تصميم الشهادة</x-staff-ui.button>
    </div>

    @unless ($certBackgroundReady)
        <p class="sui-alert sui-alert--warning" role="status">ما فيه خلفية للشهادة بعد. صمّميها أولاً عشان يتفعّل الإصدار.</p>
    @endunless

    <form method="GET" action="{{ $tabUrl }}" class="sui-cert-search">
        <x-staff-ui.input name="q" label="بحث" placeholder="اسم المتدرب" :value="$certSearch" />
        <x-staff-ui.button type="submit" size="sm">بحث</x-staff-ui.button>
        @if ($certSearch !== '')
            <x-staff-ui.button variant="ghost" size="sm" :href="$tabUrl">مسح</x-staff-ui.button>
        @endif
    </form>

    @if ($certRows->isEmpty())
        <p class="sui-program-muted">{{ $certSearch !== '' ? 'لا يوجد متدرب بهذا الاسم.' : 'لا يوجد متدربون مقبولون بعد.' }}</p>
    @else
        <form id="cert-issue" method="POST" action="{{ route('staff-ui.programs.certificates.issue', $program) }}" data-cert-form>
            @csrf
            @foreach ($keep as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach

            @if ($canSelect)
                <div class="sui-reg-bulkbar" data-cert-bulkbar hidden>
                    <span data-cert-selected-label></span>
                    <div class="sui-reg-bulkbar__actions">
                        <x-staff-ui.button type="submit" size="sm" icon="award">إصدار للمحدد</x-staff-ui.button>
                    </div>
                </div>
            @endif

            <div class="sui-table-wrap">
                <table class="sui-table sui-cert-table">
                    <thead>
                        <tr>
                            @if ($canSelect)
                                <th class="sui-reg-table__check"><input type="checkbox" class="sui-check" data-cert-check-page aria-label="تحديد الصفحة"></th>
                            @endif
                            <th>المتدرب</th>
                            @foreach ($certDays as $day)
                                <th title="{{ $day->mode->label() }}">{{ $day->name }}</th>
                            @endforeach
                            @foreach (Certs::SURVEY_COLUMNS as $type => $label)
                                <th @class(['is-off' => $certSurveys[$type] === null]) @if ($certSurveys[$type] === null) title="لا يوجد استبيان من هذا النوع" @endif>{{ $label }}</th>
                            @endforeach
                            <th>الشهادة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($certRows as $row)
                            @php
                                $issued = $certIssued->get($row->user_id);
                                $marks = $certCheckIns[$row->id] ?? [];
                                $answers = $certSurveyAnswers[$row->id] ?? [];
                            @endphp
                            <tr>
                                @if ($canSelect)
                                    <td class="sui-reg-table__check">
                                        @unless ($issued)
                                            <input type="checkbox" class="sui-check" name="ids[]" value="{{ $row->id }}" data-cert-check aria-label="تحديد {{ $row->user?->name }}">
                                        @endunless
                                    </td>
                                @endif
                                <td data-label="المتدرب"><a class="sui-reg-name" href="{{ route('staff-ui.users.show', $row->user_id) }}">{{ $row->user?->name ?? '—' }}</a></td>
                                @foreach ($certDays as $day)
                                    <td data-label="{{ $day->name }}" class="sui-cert-cell">
                                        @if (isset($marks[$day->id]))
                                            <span class="sui-cert-done"><i data-lucide="check" class="sui-icon"></i>{{ $time($marks[$day->id]) }}</span>
                                        @else
                                            <button type="submit" formaction="{{ route('staff-ui.programs.attendance.manual', [$program, $day, $row]) }}" class="sui-btn sui-btn--ghost sui-btn--sm sui-cert-manual" data-cert-manual aria-label="تحضير {{ $row->user?->name }} — {{ $day->name }}">تحضير</button>
                                        @endif
                                    </td>
                                @endforeach
                                @foreach (Certs::SURVEY_COLUMNS as $type => $label)
                                    <td data-label="{{ $label }}" class="sui-cert-cell">
                                        @php $answer = $answers[$type] ?? null; @endphp
                                        @if ($answer === true)
                                            <span class="sui-cert-done" title="عُبّئ قبل حفظ وقت التعبئة"><i data-lucide="check" class="sui-icon"></i>تمّ</span>
                                        @elseif ($answer)
                                            <span class="sui-cert-done"><i data-lucide="check" class="sui-icon"></i>{{ $time($answer) }}</span>
                                        @else
                                            <span class="sui-cert-none">—</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td data-label="الشهادة" class="sui-cert-cell">
                                    @if ($issued)
                                        <span class="sui-cert-issued">
                                            <x-staff-ui.status tone="success">مُصدرة</x-staff-ui.status>
                                            <small>{{ $issued->certificate_number }} · {{ $time($issued->issued_at) }}</small>
                                        </span>
                                    @elseif ($canSelect)
                                        <button type="submit" name="ids[]" value="{{ $row->id }}" class="sui-btn sui-btn--secondary sui-btn--sm" data-cert-one>إصدار</button>
                                    @else
                                        <span class="sui-cert-none">لم تصدر</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>

        @if ($certRows->hasPages())
            <div class="sui-pager">
                <span class="sui-pager__label">{{ en_num($certRows->firstItem() ?? 0) }}–{{ en_num($certRows->lastItem() ?? 0) }} من {{ en_num($certRows->total()) }}</span>
                @foreach ([
                    ['url' => $certRows->previousPageUrl(), 'enabled' => ! $certRows->onFirstPage(), 'icon' => 'chevron-right', 'label' => 'السابق'],
                    ['url' => $certRows->nextPageUrl(), 'enabled' => $certRows->hasMorePages(), 'icon' => 'chevron-left', 'label' => 'التالي'],
                ] as $pager)
                    @if ($pager['enabled'])
                        <a class="sui-icon-btn" href="{{ $pager['url'] }}" aria-label="{{ $pager['label'] }}"><i data-lucide="{{ $pager['icon'] }}" class="sui-icon"></i></a>
                    @else
                        <span class="sui-icon-btn" aria-disabled="true" aria-label="{{ $pager['label'] }}"><i data-lucide="{{ $pager['icon'] }}" class="sui-icon"></i></span>
                    @endif
                @endforeach
            </div>
        @endif
    @endif
</section>

@if ($canSelect)
    <script>
        (() => {
            const form = document.querySelector('[data-cert-form]');
            if (!form) return;
            const page = form.querySelector('[data-cert-check-page]');
            const checks = () => [...form.querySelectorAll('[data-cert-check]')];
            const bar = form.querySelector('[data-cert-bulkbar]');
            const label = form.querySelector('[data-cert-selected-label]');
            const sync = () => {
                const n = checks().filter((c) => c.checked).length;
                bar.hidden = n === 0;
                label.textContent = `محدد ${n}`;
                if (page) page.checked = n > 0 && n === checks().length;
            };
            page?.addEventListener('change', () => { checks().forEach((c) => { c.checked = page.checked; }); sync(); });
            form.addEventListener('change', (e) => { if (e.target.matches('[data-cert-check]')) sync(); });
            // A single «إصدار» button issues only its own row, whatever is ticked.
            form.querySelectorAll('[data-cert-one]').forEach((btn) => btn.addEventListener('click', () => {
                checks().forEach((c) => { c.checked = false; });
            }));
            form.addEventListener('submit', (e) => {
                if (e.submitter?.matches('[data-cert-manual]')) return;
                const n = e.submitter?.matches('[data-cert-one]') ? 1 : checks().filter((c) => c.checked).length;
                if (!confirm(`إصدار ${n} شهادة وإرسالها للمتدربين بالإيميل؟`)) e.preventDefault();
            });
        })();
    </script>
@endif
