@php
    use App\Enums\ProgramPrepDayType;

    $tabUrl = route('staff-ui.programs.tab', [$program, 'attendance']);
    $tz = config('app.timezone');
    $checkerLink = session('checker_link');
@endphp

@if ($errors->hasAny(['prep_date', 'training_date', 'attendance', 'name', 'open_minutes', 'delivery_type', 'date', 'ids']))
    <p class="sui-alert sui-alert--danger" role="alert">{{ $errors->first() }}</p>
@endif

{{-- Days --}}
<section id="days" class="sui-card">
    <div class="sui-card__head sui-card__head--row">
        <div>
            <h2>أيام البرنامج</h2>
            <p>الأيام الحضورية تُحضَّر بمسح QR المتدرب، والأيام عن بُعد برابط يدخل فيه المتدرب رقم هويته.</p>
        </div>
    </div>

    @if ($canUpdate)
        <div class="sui-att-forms">
            <form method="POST" action="{{ route('staff-ui.programs.attendance.days.store', $program) }}" class="sui-att-inline">
                @csrf
                <x-staff-ui.input type="date" name="prep_date" label="إضافة يوم" required />
                <x-staff-ui.select name="delivery_type" label="النوع" :options="$dayTypes" :selected="ProgramPrepDayType::InPerson->value" />
                <x-staff-ui.button type="submit" size="sm" icon="plus">إضافة</x-staff-ui.button>
            </form>
            <form method="POST" action="{{ route('staff-ui.programs.attendance.days.generate', $program) }}" class="sui-att-inline">
                @csrf
                <x-staff-ui.select name="delivery_type" label="كل أيام الجدول كـ" :options="$dayTypes" :selected="ProgramPrepDayType::InPerson->value" />
                <x-staff-ui.button type="submit" variant="secondary" size="sm" icon="calendar-plus">توليد من جدول البرنامج</x-staff-ui.button>
            </form>
        </div>
    @endif

    @if ($attendanceDays->isEmpty())
        <p class="sui-program-muted">لا توجد أيام بعد. أضيفي يوماً أو ولّديها من جدول البرنامج.</p>
    @else
        <div class="sui-table-wrap">
            <table class="sui-table">
                <thead>
                    <tr>
                        <th>اليوم</th>
                        <th>النوع</th>
                        <th>الحضور</th>
                        <th aria-label="إجراءات"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($attendanceDays as $row)
                        @php
                            $day = $row['day'];
                            $isSelected = $selectedDay && $selectedDay->is($day);
                        @endphp
                        <tr @class(['is-selected' => $isSelected])>
                            <td data-label="اليوم">
                                <strong>{{ $day->displayLabel() }}</strong>
                                @if ($day->dateString() === now($tz)->toDateString())
                                    <x-staff-ui.status tone="info">اليوم</x-staff-ui.status>
                                @endif
                            </td>
                            <td data-label="النوع">
                                @if ($canUpdate)
                                    <form method="POST" action="{{ route('staff-ui.programs.attendance.days.update', [$program, $day]) }}">
                                        @csrf
                                        <select class="sui-control sui-control--sm" name="delivery_type" onchange="this.form.submit()" aria-label="نوع اليوم">
                                            @foreach ($dayTypes as $value => $label)
                                                <option value="{{ $value }}" @selected($day->delivery_type?->value === $value)>{{ $value === ProgramPrepDayType::InPerson->value ? 'حضوري — QR' : 'عن بُعد — رابط' }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @else
                                    {{ $day->delivery_type === ProgramPrepDayType::InPerson ? 'حضوري — QR' : 'عن بُعد — رابط' }}
                                @endif
                            </td>
                            <td data-label="الحضور" class="sui-att-count">{{ en_num($row['present']) }} من {{ en_num($approvedTotal) }}</td>
                            <td class="sui-reg-table__actions">
                                <x-staff-ui.button size="sm" :variant="$isSelected ? 'primary' : 'secondary'" :href="$tabUrl.'?day='.$day->dateString().'#roster'">التحضير</x-staff-ui.button>
                                @if ($canUpdate)
                                    <form method="POST" action="{{ route('staff-ui.programs.attendance.days.destroy', [$program, $day]) }}" class="sui-inline-form" onsubmit="return confirm('حذف يوم {{ $day->displayLabel() }}؟');">
                                        @csrf
                                        <button type="submit" class="sui-btn sui-btn--ghost sui-btn--sm" aria-label="حذف اليوم"><i data-lucide="trash-2" class="sui-icon sui-icon--sm"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

{{-- Roster for one day --}}
@if ($selectedDay)
    <section id="roster" class="sui-card">
        <div class="sui-card__head sui-card__head--row">
            <div>
                <h2>تحضير {{ $selectedDay->displayLabel() }}</h2>
                <p>تحضير يدوي احتياطي: ابحثي بالاسم أو برقم الهوية.</p>
            </div>
            <form method="GET" action="{{ $tabUrl }}#roster" class="sui-att-inline">
                <input type="hidden" name="day" value="{{ $selectedDay->dateString() }}">
                <x-staff-ui.input name="q" label="بحث" placeholder="الاسم أو رقم الهوية" :value="$rosterSearch" />
                <x-staff-ui.button type="submit" variant="secondary" size="sm" icon="search">بحث</x-staff-ui.button>
            </form>
        </div>

        @if ($roster->isEmpty())
            <p class="sui-program-muted">{{ $rosterSearch !== '' ? 'لا يوجد مقبول مطابق.' : 'لا يوجد مقبولون في البرنامج بعد.' }}</p>
        @else
            <form id="att-bulk" method="POST" action="{{ route('staff-ui.programs.attendance.bulk', $program) }}">
                @csrf
                <input type="hidden" name="date" value="{{ $selectedDay->dateString() }}">
                <input type="hidden" name="q" value="{{ $rosterSearch }}">
                <div class="sui-att-bulk">
                    <x-staff-ui.button type="submit" size="sm" icon="check-check">تحضير المحدد</x-staff-ui.button>
                </div>
                <div class="sui-table-wrap">
                    <table class="sui-table">
                        <thead>
                            <tr>
                                <th class="sui-reg-table__check"><input type="checkbox" class="sui-check" aria-label="تحديد الكل" onchange="document.querySelectorAll('[data-att-check]').forEach((c) => { if (!c.disabled) c.checked = this.checked; })"></th>
                                <th>المتدرب</th>
                                <th>الحالة</th>
                                <th aria-label="إجراءات"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($roster as $registration)
                                @php
                                    $record = $presentMap->get($registration->id);
                                    $at = $record?->updated_at?->timezone($tz);
                                @endphp
                                <tr>
                                    <td class="sui-reg-table__check"><input type="checkbox" class="sui-check" name="ids[]" value="{{ $registration->id }}" data-att-check @disabled($record !== null) aria-label="تحديد {{ $registration->user?->name }}"></td>
                                    <td data-label="المتدرب"><strong>{{ $registration->user?->name ?? '—' }}</strong></td>
                                    <td data-label="الحالة">
                                        @if ($record)
                                            <x-staff-ui.status tone="success">حاضر</x-staff-ui.status>
                                            <span class="sui-att-time">{{ $at?->format('H:i') }}</span>
                                        @else
                                            <x-staff-ui.status tone="muted">لم يُحضَّر</x-staff-ui.status>
                                        @endif
                                    </td>
                                    <td class="sui-reg-table__actions">
                                        <button type="submit" form="att-toggle-{{ $registration->id }}" @class(['sui-btn', 'sui-btn--sm', 'sui-btn--secondary' => ! $record, 'sui-btn--ghost' => $record])>{{ $record ? 'إلغاء التحضير' : 'تحضير' }}</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>
            @foreach ($roster as $registration)
                <form id="att-toggle-{{ $registration->id }}" method="POST" action="{{ route('staff-ui.programs.attendance.toggle', [$program, $registration]) }}" hidden>
                    @csrf
                    <input type="hidden" name="date" value="{{ $selectedDay->dateString() }}">
                    <input type="hidden" name="present" value="{{ $presentMap->has($registration->id) ? 0 : 1 }}">
                    <input type="hidden" name="q" value="{{ $rosterSearch }}">
                </form>
            @endforeach
            @if ($roster->hasPages())
                <div class="sui-pager">
                    <span class="sui-pager__label">{{ en_num($roster->firstItem() ?? 0) }}–{{ en_num($roster->lastItem() ?? 0) }} من {{ en_num($roster->total()) }}</span>
                    @foreach ([
                        ['url' => $roster->previousPageUrl(), 'enabled' => ! $roster->onFirstPage(), 'icon' => 'chevron-right', 'label' => 'السابق'],
                        ['url' => $roster->nextPageUrl(), 'enabled' => $roster->hasMorePages(), 'icon' => 'chevron-left', 'label' => 'التالي'],
                    ] as $pager)
                        @if ($pager['enabled'])
                            <a class="sui-icon-btn" href="{{ $pager['url'] }}#roster" aria-label="{{ $pager['label'] }}"><i data-lucide="{{ $pager['icon'] }}" class="sui-icon"></i></a>
                        @else
                            <span class="sui-icon-btn" aria-disabled="true" aria-label="{{ $pager['label'] }}"><i data-lucide="{{ $pager['icon'] }}" class="sui-icon"></i></span>
                        @endif
                    @endforeach
                </div>
            @endif
        @endif
    </section>
@endif

{{-- Remote links --}}
<section id="links" class="sui-card">
    <div class="sui-card__head">
        <h2>روابط التحضير (عن بُعد)</h2>
        <p>افتحي الرابط وقت المحاضرة لمدة محددة وأرسليه في القروب. المتدرب يدخل رقم هويته ويطلع اسمه ويؤكد.</p>
    </div>
    <form method="POST" action="{{ route('staff-ui.programs.attendance.links.store', $program) }}" class="sui-att-inline sui-att-forms">
        @csrf
        <x-staff-ui.input name="name" label="اسم الرابط" placeholder="مثلاً: المحاضرة الأولى" required />
        <x-staff-ui.input type="number" name="open_minutes" label="مدة الفتح (دقيقة)" value="10" min="1" max="180" required />
        <x-staff-ui.checkbox name="open_now" value="1" label="افتحه الآن" checked />
        <x-staff-ui.button type="submit" size="sm" icon="link">إنشاء رابط</x-staff-ui.button>
    </form>

    @if ($attendanceLinks->isEmpty())
        <p class="sui-program-muted">لا توجد روابط بعد.</p>
    @else
        <ul class="sui-att-list">
            @foreach ($attendanceLinks as $link)
                @php
                    $state = match (true) {
                        $link->isCancelled() => ['ملغى', 'muted'],
                        $link->isOpen() => ['مفتوح حتى '.$link->closes_at?->timezone($tz)->format('H:i'), 'success'],
                        $link->opens_at === null => ['لم يُفتح بعد', 'warning'],
                        default => ['أُغلق '.en_digits(ar_date($link->closes_at?->timezone($tz), 'd MMM')).' '.$link->closes_at?->timezone($tz)->format('H:i'), 'muted'],
                    };
                @endphp
                <li class="sui-att-item">
                    <div class="sui-att-item__main">
                        <strong>{{ $link->name }}</strong>
                        <x-staff-ui.status :tone="$state[1]">{{ $state[0] }}</x-staff-ui.status>
                        <span class="sui-att-item__meta">{{ en_num($link->marks_count) }} حاضر · {{ en_num($link->openMinutes()) }} دقيقة</span>
                    </div>
                    @unless ($link->isCancelled())
                        <div class="sui-att-copy">
                            <input class="sui-control sui-control--sm" readonly value="{{ $link->publicUrl() }}" aria-label="رابط التحضير" onclick="this.select()">
                            <button type="button" class="sui-btn sui-btn--secondary sui-btn--sm" data-copy="{{ $link->publicUrl() }}">نسخ</button>
                        </div>
                        <div class="sui-att-item__actions">
                            <form method="POST" action="{{ route('staff-ui.programs.attendance.links.action', [$program, $link]) }}">
                                @csrf
                                @if ($link->isOpen())
                                    <input type="hidden" name="action" value="close">
                                    <x-staff-ui.button type="submit" variant="secondary" size="sm" icon="lock">إغلاق الآن</x-staff-ui.button>
                                @else
                                    <input type="hidden" name="action" value="open">
                                    <x-staff-ui.button type="submit" size="sm" icon="play">فتح {{ en_num($link->openMinutes()) }} دقيقة</x-staff-ui.button>
                                @endif
                            </form>
                            <form method="POST" action="{{ route('staff-ui.programs.attendance.links.action', [$program, $link]) }}" onsubmit="return confirm('إلغاء الرابط نهائياً؟ يبقى الحضور المسجّل.');">
                                @csrf
                                <input type="hidden" name="action" value="cancel">
                                <x-staff-ui.button type="submit" variant="ghost" size="sm">إلغاء الرابط</x-staff-ui.button>
                            </form>
                        </div>
                    @endunless
                </li>
            @endforeach
        </ul>
    @endif
</section>

{{-- QR checkers --}}
<section id="checkers" class="sui-card">
    <div class="sui-card__head">
        <h2>مسؤولو التحضير (الأيام الحضورية)</h2>
        <p>كل مقبول يلقى QR الحضور في حسابه بالمنصة. مسؤول التحضير يفتح رابطه من الجوال ويمسح الـQR عند الباب.</p>
    </div>

    @if ($checkerLink)
        <div class="sui-att-flash" role="status">
            <p>رابط دخول <strong>{{ $checkerLink['name'] }}</strong> (يظهر مرة وحدة، انسخيه الآن):</p>
            <div class="sui-att-copy">
                <input class="sui-control sui-control--sm" readonly value="{{ $checkerLink['url'] }}" aria-label="رابط مسؤول التحضير" onclick="this.select()">
                <button type="button" class="sui-btn sui-btn--primary sui-btn--sm" data-copy="{{ $checkerLink['url'] }}">نسخ</button>
            </div>
        </div>
    @endif

    @if ($canUpdate)
        <form method="POST" action="{{ route('staff-ui.programs.attendance.checkers.store', $program) }}" class="sui-att-inline sui-att-forms">
            @csrf
            <x-staff-ui.input name="name" label="إضافة مسؤول تحضير" placeholder="الاسم" required />
            <x-staff-ui.button type="submit" size="sm" icon="user-plus">إضافة</x-staff-ui.button>
        </form>
    @endif

    @if ($attendanceCheckers->isEmpty())
        <p class="sui-program-muted">لا يوجد مسؤولو تحضير بعد.</p>
    @else
        <ul class="sui-att-list">
            @foreach ($attendanceCheckers as $checker)
                <li class="sui-att-item">
                    <div class="sui-att-item__main">
                        <strong>{{ $checker->name }}</strong>
                        <x-staff-ui.status :tone="$checker->is_active ? 'success' : 'muted'">{{ $checker->is_active ? 'فعّال' : 'موقوف' }}</x-staff-ui.status>
                        <span class="sui-att-item__meta">{{ $checker->last_used_at ? 'آخر استخدام '.en_digits(ar_date($checker->last_used_at->timezone($tz), 'd MMM')).' '.$checker->last_used_at->timezone($tz)->format('H:i') : 'لم يستخدم رابطه بعد' }}</span>
                    </div>
                    @if ($canUpdate)
                        <div class="sui-att-item__actions">
                            <form method="POST" action="{{ route('staff-ui.programs.attendance.checkers.action', [$program, $checker]) }}" onsubmit="return confirm('الرابط القديم سيتوقف. إنشاء رابط جديد؟');">
                                @csrf
                                <input type="hidden" name="action" value="regenerate">
                                <x-staff-ui.button type="submit" variant="secondary" size="sm" icon="refresh-cw">رابط جديد</x-staff-ui.button>
                            </form>
                            <form method="POST" action="{{ route('staff-ui.programs.attendance.checkers.action', [$program, $checker]) }}">
                                @csrf
                                <input type="hidden" name="action" value="{{ $checker->is_active ? 'deactivate' : 'activate' }}">
                                <x-staff-ui.button type="submit" variant="ghost" size="sm">{{ $checker->is_active ? 'إيقاف' : 'تفعيل' }}</x-staff-ui.button>
                            </form>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>

<script>
    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(button.dataset.copy);
                const label = button.textContent;
                button.textContent = 'نُسخ ✓';
                setTimeout(() => { button.textContent = label; }, 1500);
            } catch (e) {
                button.previousElementSibling?.select();
            }
        });
    });
</script>
