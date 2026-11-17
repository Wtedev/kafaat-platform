@extends('public.experience-houses._shell')

@section('lead')
    تقدّم <strong>جمعية كفاءات الأهلية</strong> خبراتها المؤسسية للجمعيات المستفيدة —
    حدّدوا الإدارة التي تحتاجون فيها الدعم وارفعوا طلبكم
@endsection

@section('styles')
    .intro { display: flex; gap: 18px; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; margin-bottom: 8px; }
    .intro h2 { margin: 0; color: var(--teal-600); font-size: clamp(24px, 3.4vw, 34px); line-height: 1.3; }
    .intro p { margin: 6px 0 0; color: var(--muted); font-size: 16px; max-width: 560px; }
    .intro .time { background: var(--teal-50); color: var(--teal-700); border-radius: 999px; padding: 6px 16px; font-size: 14px; font-weight: 700; white-space: nowrap; }

    .alert { border-radius: 14px; padding: 14px 18px; margin: 18px 0 0; background: #fdf0ef; color: var(--danger); border: 1px solid #f3cfcc; font-size: 15px; }
    .alert ul { margin: 6px 0 0; padding-inline-start: 20px; }

    fieldset { border: 0; margin: 0; padding: 0; }
    .section { border-top: 1px solid var(--line); margin-top: 34px; padding-top: 30px; }
    .section-head { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; }
    .step {
        flex: none; width: 42px; height: 42px; border-radius: 12px 12px 12px 4px;
        background: var(--teal-300); color: #fff; font-weight: 700; font-size: 18px;
        display: grid; place-items: center;
    }
    .section-head h3 { margin: 0; color: var(--teal-600); font-size: clamp(21px, 2.6vw, 26px); line-height: 1.3; }
    .section-head small { display: block; color: var(--muted); font-size: 14px; font-weight: 400; }

    .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px 22px; }
    .grid .full { grid-column: 1 / -1; }
    @media (max-width: 680px) { .grid { grid-template-columns: 1fr; } }

    label.f { display: block; font-weight: 700; color: var(--teal-950); font-size: 15px; margin-bottom: 7px; }
    label.f .req { color: var(--teal-500); margin-inline-start: 2px; }
    label.f .opt { color: var(--muted); font-weight: 400; font-size: 13px; }
    .input, select.input, textarea.input {
        width: 100%; font: inherit; font-size: 16px; color: var(--ink);
        background: var(--pale); border: 1.5px solid var(--line); border-radius: 12px;
        padding: 12px 14px; transition: border-color .15s, box-shadow .15s, background .15s;
    }
    textarea.input { min-height: 130px; resize: vertical; }
    .input:focus { outline: none; border-color: var(--teal-300); background: #fff; box-shadow: 0 0 0 4px rgba(63,184,185,.18); }
    .input.ltr { direction: ltr; text-align: right; }
    .has-error .input { border-color: var(--danger); background: #fffafa; }
    .err { color: var(--danger); font-size: 13.5px; margin-top: 6px; }
    .hint { color: var(--muted); font-size: 13.5px; margin-top: 6px; }

    .chips { display: flex; flex-wrap: wrap; gap: 10px; }
    .chip { position: relative; }
    .chip input { position: absolute; opacity: 0; inset: 0; cursor: pointer; margin: 0; }
    .chip span {
        display: inline-flex; align-items: center; gap: 8px;
        border: 1.5px solid var(--line); background: #fff; color: var(--teal-950);
        border-radius: 999px; padding: 9px 18px; font-size: 15px; cursor: pointer;
        transition: all .15s; user-select: none;
    }
    .chip span::before {
        content: ''; width: 16px; height: 16px; border-radius: 5px; border: 1.5px solid #a9c9cb; background: #fff; flex: none;
        transition: all .15s;
    }
    .chip input[type=radio] + span::before { border-radius: 50%; }
    .chip input:hover + span { border-color: var(--teal-300); }
    .chip input:focus-visible + span { box-shadow: 0 0 0 4px rgba(63,184,185,.25); }
    .chip input:checked + span { background: var(--teal-600); border-color: var(--teal-600); color: #fff; }
    .chip input:checked + span::before {
        border-color: #fff; background: #fff
            url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath d='M3.5 8.5l3 3 6-7' fill='none' stroke='%231f8a91' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center/12px no-repeat;
    }
    .chips.big .chip span { padding: 12px 20px; }

    .file {
        display: flex; align-items: center; gap: 14px; border: 1.5px dashed #a9d7d8; background: var(--pale);
        border-radius: 14px; padding: 16px 18px; cursor: pointer; position: relative;
    }
    .file input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .file .ico { width: 40px; height: 40px; border-radius: 10px; background: var(--teal-100); display: grid; place-items: center; color: var(--teal-700); flex: none; }
    .file b { color: var(--teal-950); display: block; font-size: 15px; }
    .file small { color: var(--muted); }

    .consent { display: flex; gap: 12px; align-items: flex-start; background: var(--teal-50); border-radius: 14px; padding: 16px 18px; cursor: pointer; }
    .consent input { width: 20px; height: 20px; margin-top: 4px; accent-color: var(--teal-600); flex: none; }

    .actions { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-top: 30px; }
    .btn {
        font: inherit; font-weight: 700; font-size: 18px; color: #fff; border: 0; cursor: pointer;
        background: linear-gradient(135deg, var(--teal-600), var(--teal-700));
        border-radius: 14px 14px 14px 4px; padding: 15px 44px;
        box-shadow: 0 12px 24px -12px rgba(23,113,121,.7); transition: transform .15s, filter .15s;
    }
    .btn:hover { filter: brightness(1.06); transform: translateY(-1px); }
    .btn[disabled] { opacity: .7; cursor: wait; transform: none; }
    .actions small { color: var(--muted); }
    .hp { position: absolute !important; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
    .hidden { display: none; }
@endsection

@section('content')
<div class="panel">
    <div class="intro">
        <div>
            <h2>نموذج طلب الدعم</h2>
            <p>يُستخدم هذا النموذج للجمعيات المستفيدة من مشروع بيوت الخبرة لطلب دعم متخصص في إحدى الإدارات. سيتواصل معكم فريق الجمعية لدراسة الطلب وتحديد آلية التنفيذ.</p>
        </div>
        <span class="time">⏱ حوالي 5 دقائق</span>
    </div>

    @if ($errors->any())
        <div class="alert" role="alert">
            <b>فضلاً راجعوا الحقول التالية:</b>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('public.experience-houses.store') }}" enctype="multipart/form-data" id="eh-form" novalidate>
        @csrf
        <div class="hp" aria-hidden="true">
            <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        {{-- 1. بيانات الجمعية --}}
        <fieldset class="section">
            <div class="section-head">
                <span class="step">1</span>
                <h3>بيانات الجمعية <small>الجهة المستفيدة من الدعم</small></h3>
            </div>
            <div class="grid">
                <div class="full @error('organization_name') has-error @enderror">
                    <label class="f" for="organization_name">اسم الجمعية <span class="req">*</span></label>
                    <input class="input" id="organization_name" name="organization_name" value="{{ old('organization_name') }}" required maxlength="255" autocomplete="organization">
                    @error('organization_name')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="@error('license_number') has-error @enderror">
                    <label class="f" for="license_number">رقم الترخيص <span class="req">*</span></label>
                    <input class="input ltr" id="license_number" name="license_number" value="{{ old('license_number') }}" required inputmode="numeric" maxlength="50">
                    @error('license_number')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="@error('founded_year') has-error @enderror">
                    <label class="f" for="founded_year">سنة التأسيس <span class="opt">(اختياري)</span></label>
                    <input class="input ltr" id="founded_year" name="founded_year" value="{{ old('founded_year') }}" inputmode="numeric" maxlength="4" placeholder="مثال: 2019">
                    @error('founded_year')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="@error('region') has-error @enderror">
                    <label class="f" for="region">المنطقة <span class="req">*</span></label>
                    <select class="input" id="region" name="region" required>
                        <option value="">اختر المنطقة</option>
                        @foreach ($regions as $region)
                            <option value="{{ $region }}" @selected(old('region') === $region)>{{ $region }}</option>
                        @endforeach
                    </select>
                    @error('region')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="@error('city') has-error @enderror">
                    <label class="f" for="city">المدينة <span class="req">*</span></label>
                    <input class="input" id="city" name="city" value="{{ old('city') }}" required maxlength="100">
                    @error('city')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="full @error('staff_size') has-error @enderror">
                    <label class="f">عدد موظفي الجمعية <span class="opt">(اختياري)</span></label>
                    <div class="chips">
                        @foreach ($staffSizes as $value => $label)
                            <label class="chip">
                                <input type="radio" name="staff_size" value="{{ $value }}" @checked(old('staff_size') === $value)>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('staff_size')<div class="err">{{ $message }}</div>@enderror
                </div>
            </div>
        </fieldset>

        {{-- 2. ممثل الجمعية --}}
        <fieldset class="section">
            <div class="section-head">
                <span class="step">2</span>
                <h3>ممثل الجمعية <small>الشخص الذي سنتواصل معه بخصوص الطلب</small></h3>
            </div>
            <div class="grid">
                <div class="@error('contact_name') has-error @enderror">
                    <label class="f" for="contact_name">الاسم الكامل <span class="req">*</span></label>
                    <input class="input" id="contact_name" name="contact_name" value="{{ old('contact_name') }}" required maxlength="255" autocomplete="name">
                    @error('contact_name')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="@error('contact_role') has-error @enderror">
                    <label class="f" for="contact_role">المسمى الوظيفي <span class="req">*</span></label>
                    <input class="input" id="contact_role" name="contact_role" value="{{ old('contact_role') }}" required maxlength="255" placeholder="مثال: المدير التنفيذي">
                    @error('contact_role')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="@error('contact_phone') has-error @enderror">
                    <label class="f" for="contact_phone">رقم الجوال <span class="req">*</span></label>
                    <input class="input ltr" id="contact_phone" name="contact_phone" value="{{ old('contact_phone') }}" required inputmode="tel" maxlength="16" placeholder="05XXXXXXXX" autocomplete="tel">
                    @error('contact_phone')<div class="err">{{ $message }}</div>@else<div class="hint">بصيغة 05XXXXXXXX</div>@enderror
                </div>
                <div class="@error('contact_email') has-error @enderror">
                    <label class="f" for="contact_email">البريد الإلكتروني <span class="req">*</span></label>
                    <input class="input ltr" type="email" id="contact_email" name="contact_email" value="{{ old('contact_email') }}" required maxlength="255" autocomplete="email">
                    @error('contact_email')<div class="err">{{ $message }}</div>@enderror
                </div>
            </div>
        </fieldset>

        {{-- 3. مجال الدعم --}}
        <fieldset class="section">
            <div class="section-head">
                <span class="step">3</span>
                <h3>مجال الدعم المطلوب <small>يمكنكم اختيار أكثر من إدارة</small></h3>
            </div>
            <div class="grid">
                <div class="full @error('departments') has-error @enderror">
                    <label class="f">الإدارات التي تحتاجون فيها المساعدة <span class="req">*</span></label>
                    <div class="chips big">
                        @foreach ($departments as $value => $label)
                            <label class="chip">
                                <input type="checkbox" name="departments[]" value="{{ $value }}" @checked(in_array($value, (array) old('departments', []), true)) @if($value === 'other') data-other-toggle @endif>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('departments')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="full {{ in_array('other', (array) old('departments', []), true) ? '' : 'hidden' }} @error('other_department') has-error @enderror" id="other-wrap">
                    <label class="f" for="other_department">حدّدوا الإدارة الأخرى <span class="req">*</span></label>
                    <input class="input" id="other_department" name="other_department" value="{{ old('other_department') }}" maxlength="255">
                    @error('other_department')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="full @error('support_types') has-error @enderror">
                    <label class="f">نوع الدعم المطلوب <span class="req">*</span></label>
                    <div class="chips">
                        @foreach ($supportTypes as $value => $label)
                            <label class="chip">
                                <input type="checkbox" name="support_types[]" value="{{ $value }}" @checked(in_array($value, (array) old('support_types', []), true))>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('support_types')<div class="err">{{ $message }}</div>@enderror
                </div>
            </div>
        </fieldset>

        {{-- 4. تفاصيل الاحتياج --}}
        <fieldset class="section">
            <div class="section-head">
                <span class="step">4</span>
                <h3>تفاصيل الاحتياج <small>كلما كان الوصف أوضح، كان الدعم أدق</small></h3>
            </div>
            <div class="grid">
                <div class="full @error('challenge') has-error @enderror">
                    <label class="f" for="challenge">صِفوا الاحتياج أو التحدي الذي تواجهه الجمعية <span class="req">*</span></label>
                    <textarea class="input" id="challenge" name="challenge" required maxlength="3000" placeholder="مثال: لا تملك الجمعية خطة استراتيجية معتمدة، ونحتاج إلى بناء خطة للأعوام الثلاثة القادمة…">{{ old('challenge') }}</textarea>
                    @error('challenge')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="full @error('expected_outcome') has-error @enderror">
                    <label class="f" for="expected_outcome">ما النتائج التي تتوقعونها من الدعم؟ <span class="req">*</span></label>
                    <textarea class="input" id="expected_outcome" name="expected_outcome" required maxlength="2000" style="min-height:100px" placeholder="مثال: خطة استراتيجية معتمدة من مجلس الإدارة مع مؤشرات أداء واضحة">{{ old('expected_outcome') }}</textarea>
                    @error('expected_outcome')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="@error('priority') has-error @enderror">
                    <label class="f">أولوية الطلب <span class="req">*</span></label>
                    <div class="chips">
                        @foreach ($priorities as $value => $label)
                            <label class="chip">
                                <input type="radio" name="priority" value="{{ $value }}" required @checked(old('priority', 'month') === $value)>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('priority')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="@error('delivery_mode') has-error @enderror">
                    <label class="f">أسلوب التنفيذ المفضّل <span class="req">*</span></label>
                    <div class="chips">
                        @foreach ($deliveryModes as $value => $label)
                            <label class="chip">
                                <input type="radio" name="delivery_mode" value="{{ $value }}" required @checked(old('delivery_mode', 'remote') === $value)>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('delivery_mode')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="full @error('attachment') has-error @enderror">
                    <label class="f" for="attachment">مرفقات داعمة <span class="opt">(اختياري)</span></label>
                    <label class="file">
                        <span class="ico" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.4 11.1l-9.2 9.2a6 6 0 01-8.5-8.5l9.2-9.2a4 4 0 015.7 5.7l-9.2 9.2a2 2 0 01-2.8-2.8l8.5-8.5"/></svg>
                        </span>
                        <span>
                            <b id="file-label">أرفقوا ملفاً (خطة حالية، لائحة، تقرير…)</b>
                            <small>PDF أو Word أو Excel أو صورة — حتى 10 ميجابايت</small>
                        </span>
                        <input type="file" id="attachment" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg">
                    </label>
                    @error('attachment')<div class="err">{{ $message }}</div>@enderror
                </div>
            </div>
        </fieldset>

        <div class="section">
            <label class="consent @error('consent') has-error @enderror">
                <input type="checkbox" name="consent" value="1" required @checked(old('consent'))>
                <span>أُقرّ بصحة البيانات المدخلة، وأوافق على تواصل جمعية كفاءات الأهلية معنا بخصوص هذا الطلب.</span>
            </label>
            @error('consent')<div class="err">{{ $message }}</div>@enderror

            @if ($turnstileSiteKey)
                <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}" style="margin-top:18px"></div>
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
            @endif
            @error('turnstile')<div class="err">{{ $message }}</div>@enderror

            <div class="actions">
                <small>الحقول المعلّمة بـ <span style="color:var(--teal-500)">*</span> إلزامية</small>
                <button type="submit" class="btn" id="eh-submit">رفع الطلب</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var other = document.querySelector('[data-other-toggle]');
    var wrap = document.getElementById('other-wrap');
    if (other && wrap) {
        other.addEventListener('change', function () {
            wrap.classList.toggle('hidden', !other.checked);
            if (other.checked) document.getElementById('other_department').focus();
        });
    }
    var file = document.getElementById('attachment');
    var fileLabel = document.getElementById('file-label');
    if (file && fileLabel) {
        file.addEventListener('change', function () {
            if (file.files && file.files[0]) fileLabel.textContent = file.files[0].name;
        });
    }
    var form = document.getElementById('eh-form');
    var btn = document.getElementById('eh-submit');
    if (form && btn) {
        form.addEventListener('submit', function () {
            btn.disabled = true;
            btn.textContent = 'جارٍ الإرسال…';
        });
    }
})();
</script>
@endpush
