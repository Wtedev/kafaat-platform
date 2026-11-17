@extends('experience-houses.manage._layout')
@php
    use App\Models\ExperienceHouseRequest as R;
    $flowIndex = array_search($r->status, R::FLOW, true);
    $next = $r->nextStatus();
    $nextLabels = ['in_review' => 'بدء دراسة الطلب', 'scheduled' => 'تم تحديد موعد', 'in_progress' => 'بدء التنفيذ', 'completed' => 'إنهاء الطلب'];
@endphp
@section('title', $r->organization_name)

@section('styles')
    .back{display:inline-flex;align-items:center;gap:6px;color:var(--teal-700);font-weight:700;margin-bottom:12px}
    .head{padding:22px 24px;display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap}
    .head .org-ic{width:58px;height:58px;border-radius:17px 17px 17px 5px;background:var(--teal-300);color:#fff;display:grid;place-items:center;flex:none}
    .head h1{margin:0;font-size:clamp(22px,3.6vw,28px);color:var(--teal-950);line-height:1.35}
    .head .meta{display:flex;gap:14px;flex-wrap:wrap;color:var(--muted);font-size:14px;margin-top:4px}
    .head .meta span{display:inline-flex;align-items:center;gap:5px}
    .grid{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:16px;margin-top:16px;align-items:start}
    @media(max-width:860px){.grid{grid-template-columns:1fr}}
    .sec{padding:20px 22px}
    .sec+.sec{border-top:1px solid var(--line)}
    .sec h2{margin:0 0 12px;font-size:15px;color:var(--muted);display:flex;align-items:center;gap:8px;font-weight:700}
    .need{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}
    .need div{display:flex;align-items:center;gap:10px;background:var(--pale);border:1px solid var(--line);border-radius:14px;padding:12px;font-weight:700;color:var(--teal-950);font-size:14.5px}
    .need .ic{width:36px;height:36px;border-radius:11px 11px 11px 3px;background:#fff;color:var(--teal-600);display:grid;place-items:center;flex:none;border:1px solid var(--line)}
    .tags{display:flex;gap:8px;flex-wrap:wrap}
    .tag{background:var(--teal-50);color:var(--teal-700);border-radius:10px;padding:5px 12px;font-size:14px}
    .text{margin:0;white-space:pre-line;font-size:16px}
    .facts{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .fact{background:var(--pale);border-radius:12px;padding:10px 12px}
    .fact small{display:block;color:var(--muted);font-size:12.5px}
    .fact b{color:var(--teal-950);font-size:15px}

    /* شريط المراحل */
    .steps{display:flex;align-items:flex-start;padding:22px 18px 6px}
    .step{flex:1;text-align:center;position:relative;font-size:12.5px;color:var(--muted)}
    .step .dot{width:38px;height:38px;margin:0 auto 6px;border-radius:12px 12px 12px 4px;display:grid;place-items:center;background:#fff;border:2px solid var(--line);color:#a9bfc2;position:relative;z-index:1}
    .step::before{content:'';position:absolute;top:19px;left:50%;width:100%;height:3px;background:var(--line);z-index:0}
    .step:first-child::before{display:none}
    .step.done .dot{background:var(--teal-300);border-color:var(--teal-300);color:#fff}
    .step.done::before,.step.now::before{background:var(--teal-300)}
    .step.now .dot{background:var(--teal-700);border-color:var(--teal-700);color:#fff;box-shadow:0 0 0 5px rgba(63,184,185,.25)}
    .step.now{color:var(--teal-900);font-weight:700}
    .actions{padding:16px 18px 20px;display:grid;gap:10px}
    .actions .btn{width:100%;padding:15px}
    .linkish{background:none;border:0;color:var(--muted);font-size:14px;text-decoration:underline;padding:4px}
    .closed{margin:18px;border-radius:14px;padding:14px;text-align:center;background:#f4f4f5;color:#52525b}
    .closed.ok{background:#e5f6ea;color:#2f8a4d}

    .contact{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
    .contact a{display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px 6px;border-radius:16px;background:var(--pale);border:1px solid var(--line);color:var(--teal-700);font-weight:700;font-size:14px;transition:all .15s}
    .contact a:hover{border-color:var(--teal-300);background:#fff}
    .contact a.wa{background:#e9f8ef;border-color:#cdeedb;color:#1f8a4c}
    .person{display:flex;align-items:center;gap:12px;margin-bottom:14px}
    .person .avatar{width:44px;height:44px}
    .person b{display:block;color:var(--teal-950)}
    .person small{color:var(--muted)}
    textarea.input{min-height:110px;resize:vertical}
    .who{font-size:13px;color:var(--muted);margin-top:8px;display:flex;gap:6px;align-items:center}
@endsection

@section('body')
<main class="wrap">
    <a class="back" href="{{ url()->previous() !== url()->current() && str_contains(url()->previous(), '/experience-houses/manage') ? url()->previous() : route('experience-houses.manage.index') }}">@include('experience-houses.manage._icon', ['name' => 'arrow-right', 'size' => 18]) كل الطلبات</a>

    <div class="card head">
        <span class="org-ic">@include('experience-houses.manage._icon', ['name' => 'building', 'size' => 28])</span>
        <div style="flex:1;min-width:220px">
            <h1>{{ $r->organization_name }}</h1>
            <div class="meta">
                <span>@include('experience-houses.manage._icon', ['name' => 'pin', 'size' => 15]) {{ $r->region }} — {{ $r->city }}</span>
                <span>@include('experience-houses.manage._icon', ['name' => 'clock', 'size' => 15]) {{ $r->created_at->locale('ar')->diffForHumans() }}</span>
                <span dir="ltr">{{ $r->reference_no }}</span>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @if ($r->priority === 'urgent')<span class="pill" style="background:#fdecea;color:var(--danger)">@include('experience-houses.manage._icon', ['name' => 'flame', 'size' => 14]) عاجل</span>@endif
            <span class="pill st-{{ $r->status }}">@include('experience-houses.manage._icon', ['name' => R::STATUS_ICONS[$r->status], 'size' => 15]) {{ R::STATUSES[$r->status] }}</span>
        </div>
    </div>

    <div class="grid">
        {{-- العمود الرئيسي: ماذا يحتاجون --}}
        <div class="card">
            <div class="sec">
                <h2>@include('experience-houses.manage._icon', ['name' => 'target', 'size' => 18]) يحتاجون دعماً في</h2>
                <div class="need">
                    @foreach ($r->departments ?? [] as $d)
                        <div><span class="ic">@include('experience-houses.manage._icon', ['name' => R::DEPARTMENT_ICONS[$d] ?? 'dots', 'size' => 19])</span>{{ $d === 'other' && $r->other_department ? $r->other_department : (R::DEPARTMENTS[$d] ?? $d) }}</div>
                    @endforeach
                </div>
                <div class="tags" style="margin-top:12px">
                    @foreach (R::labelsFor($r->support_types, R::SUPPORT_TYPES) as $t)<span class="tag">{{ $t }}</span>@endforeach
                    <span class="tag">{{ R::DELIVERY_MODES[$r->delivery_mode] ?? '' }}</span>
                    <span class="tag">{{ R::PRIORITIES[$r->priority] ?? '' }}</span>
                </div>
            </div>
            <div class="sec">
                <h2>@include('experience-houses.manage._icon', ['name' => 'note', 'size' => 18]) وصف الاحتياج</h2>
                <p class="text">{{ $r->challenge }}</p>
            </div>
            <div class="sec">
                <h2>@include('experience-houses.manage._icon', ['name' => 'check', 'size' => 18]) النتيجة التي يتوقعونها</h2>
                <p class="text">{{ $r->expected_outcome }}</p>
            </div>
            @if ($r->attachment_path)
                <div class="sec">
                    <a class="btn ghost" href="{{ route('experience-houses.manage.attachment', $r) }}">@include('experience-houses.manage._icon', ['name' => 'paperclip', 'size' => 18]) تحميل المرفق</a>
                </div>
            @endif
            <div class="sec">
                <h2>@include('experience-houses.manage._icon', ['name' => 'building', 'size' => 18]) عن الجمعية</h2>
                <div class="facts">
                    <div class="fact"><small>رقم الترخيص</small><b dir="ltr">{{ $r->license_number }}</b></div>
                    <div class="fact"><small>سنة التأسيس</small><b>{{ $r->founded_year ?: '—' }}</b></div>
                    <div class="fact"><small>عدد الموظفين</small><b>{{ R::STAFF_SIZES[$r->staff_size] ?? '—' }}</b></div>
                    <div class="fact"><small>المدينة</small><b>{{ $r->city }}</b></div>
                </div>
            </div>
        </div>

        {{-- العمود الجانبي: الإجراءات --}}
        <div style="display:grid;gap:16px">
            <div class="card">
                <div class="steps">
                    @foreach (R::FLOW as $i => $st)
                        @php $cls = $flowIndex === false ? '' : ($i < $flowIndex ? 'done' : ($i === $flowIndex ? 'now' : '')); @endphp
                        <div class="step {{ $cls }}">
                            <div class="dot">@include('experience-houses.manage._icon', ['name' => R::STATUS_ICONS[$st], 'size' => 18])</div>
                            {{ R::STATUSES[$st] }}
                        </div>
                    @endforeach
                </div>
                @if ($r->status === 'rejected')
                    <div class="closed">تم الاعتذار عن هذا الطلب.
                        <form method="POST" action="{{ route('experience-houses.manage.status', $r) }}" style="margin-top:6px">@csrf<input type="hidden" name="status" value="in_review"><button class="linkish">إعادة فتح الطلب</button></form>
                    </div>
                @elseif ($r->status === 'completed')
                    <div class="closed ok">@include('experience-houses.manage._icon', ['name' => 'check', 'size' => 18]) اكتمل هذا الطلب</div>
                @else
                    <div class="actions">
                        <form method="POST" action="{{ route('experience-houses.manage.status', $r) }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ $next }}">
                            <button class="btn">@include('experience-houses.manage._icon', ['name' => R::STATUS_ICONS[$next], 'size' => 19]) {{ $nextLabels[$next] ?? R::STATUSES[$next] }}</button>
                        </form>
                        <form method="POST" action="{{ route('experience-houses.manage.status', $r) }}" style="text-align:center" onsubmit="return confirm('متأكد من الاعتذار عن هذا الطلب؟')">
                            @csrf
                            <input type="hidden" name="status" value="rejected">
                            <button class="linkish">الاعتذار عن الطلب</button>
                        </form>
                    </div>
                @endif
                @if ($r->handledBy)
                    <div class="who" style="padding:0 18px 16px">@include('experience-houses.manage._icon', ['name' => 'users', 'size' => 15]) آخر تحديث بواسطة {{ $r->handledBy->name }} · {{ $r->status_changed_at?->locale('ar')->diffForHumans() }}</div>
                @endif
            </div>

            <div class="card sec">
                <div class="person">
                    <span class="avatar">{{ mb_substr($r->contact_name, 0, 1) }}</span>
                    <div><b>{{ $r->contact_name }}</b><small>{{ $r->contact_role }}</small></div>
                </div>
                <div class="contact">
                    <a class="wa" href="{{ $r->whatsappUrl() }}" target="_blank" rel="noopener">@include('experience-houses.manage._icon', ['name' => 'whatsapp', 'size' => 24]) واتساب</a>
                    <a href="tel:{{ $r->contact_phone }}">@include('experience-houses.manage._icon', ['name' => 'phone', 'size' => 24]) اتصال</a>
                    <a href="mailto:{{ $r->contact_email }}">@include('experience-houses.manage._icon', ['name' => 'mail', 'size' => 24]) إيميل</a>
                </div>
            </div>

            <form class="card sec" method="POST" action="{{ route('experience-houses.manage.note', $r) }}">
                @csrf
                <h2>@include('experience-houses.manage._icon', ['name' => 'note', 'size' => 18]) ملاحظات الفريق</h2>
                <textarea class="input" name="staff_notes" placeholder="اكتب ما تم مع الجمعية… (لا تظهر لها)">{{ old('staff_notes', $r->staff_notes) }}</textarea>
                <button class="btn light" style="margin-top:10px;width:100%">حفظ الملاحظة</button>
            </form>
        </div>
    </div>
</main>
@endsection
