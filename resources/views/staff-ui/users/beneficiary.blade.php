@php
    use App\Enums\AccountStatus;
    use App\Support\Privacy\SensitiveContactMasker;

    $profile = $beneficiary->profile;
    $email = $canViewContact
        ? $beneficiary->email
        : SensitiveContactMasker::maskEmail($beneficiary->email);
    $phone = filled($beneficiary->phone)
        ? ($canViewContact ? $beneficiary->phone : SensitiveContactMasker::maskPhone($beneficiary->phone))
        : '—';
    $active = (bool) $beneficiary->is_active && $beneficiary->account_status === AccountStatus::Active;
    $skills = $profile?->cvSkillsStructured() ?? [];
    $education = $profile?->cvEducationStructured() ?? [];
    $hasCvFile = $profile?->currentCvDocument?->isActive() ?? false;
    $openField = match (true) {
        $errors->hasAny(['first_name', 'father_name', 'grandfather_name', 'family_name']) => 'name',
        $errors->has('phone') => 'phone',
        $errors->has('gender') => 'gender',
        $errors->has('birth_date') => 'birth_date',
        $errors->has('city') => 'city',
        $errors->has('job_title') => 'job_title',
        $errors->has('bio') => 'bio',
        $errors->has('notify_email') => 'notify_email',
        $errors->has('email') => 'email',
        $errors->hasAny(['identity_type', 'identity_number', 'reason']) => 'identity',
        default => null,
    };
@endphp

<x-staff-ui.layout :name="$staffName" :email="$staffEmail" crumb="المستخدمين" :dashboard-active="false" active-nav="users">
    <header class="sui-page-head">
        <a class="sui-back" href="{{ route('staff-ui.users.index') }}">المستفيدين</a>
        <div class="sui-page-head__name">
            <h1>{{ $beneficiary->fullName() }}</h1>
            @if ($canDownloadCv)
                <x-staff-ui.button variant="ghost" size="sm" icon="download" icon-only aria-label="تحميل السيرة الذاتية" :href="route('admin.beneficiaries.cv-pdf', $beneficiary)" />
            @endif
        </div>
    </header>

    @if (session('status'))
        <p class="sui-alert" role="status">{{ session('status') }}</p>
    @endif

    <div class="sui-stack">
        <section class="sui-card">
            <div class="sui-card__head">
                <h2>البيانات الأساسية والتواصل</h2>
            </div>
            <dl class="sui-facts">
                <dt>الاسم</dt>
                <dd class="sui-fact">
                    <span class="sui-fact__value">{{ $beneficiary->fullName() }}</span>
                    @if ($canUpdate)
                        <x-staff-ui.button type="button" variant="ghost" size="sm" icon="pencil" icon-only aria-label="تعديل الاسم" data-sui-open-modal="edit-name" />
                    @endif
                </dd>
                <dt>البريد</dt>
                <dd class="sui-fact">
                    <span class="sui-fact__value">{{ $email ?: '—' }}</span>
                    @if ($canUpdateSensitive)
                        <x-staff-ui.button type="button" variant="ghost" size="sm" icon="pencil" icon-only aria-label="تعديل البريد" data-sui-open-modal="edit-email" />
                    @endif
                </dd>
                <dt>الجوال</dt>
                <dd class="sui-fact">
                    <span class="sui-fact__value">{{ $phone }}</span>
                    @if ($canUpdate)
                        <x-staff-ui.button type="button" variant="ghost" size="sm" icon="pencil" icon-only aria-label="تعديل الجوال" data-sui-open-modal="edit-phone" />
                    @endif
                </dd>
                <dt>الجنس</dt>
                <dd class="sui-fact">
                    <span class="sui-fact__value">{{ $profile?->gender?->label() ?? '—' }}</span>
                    @if ($canUpdate)
                        <x-staff-ui.button type="button" variant="ghost" size="sm" icon="pencil" icon-only aria-label="تعديل الجنس" data-sui-open-modal="edit-gender" />
                    @endif
                </dd>
                <dt>تاريخ الميلاد</dt>
                <dd class="sui-fact">
                    <span class="sui-fact__value">{{ $profile?->birth_date?->toDateString() ?? '—' }}</span>
                    @if ($canUpdate)
                        <x-staff-ui.button type="button" variant="ghost" size="sm" icon="pencil" icon-only aria-label="تعديل تاريخ الميلاد" data-sui-open-modal="edit-birth-date" />
                    @endif
                </dd>
                <dt>المدينة</dt>
                <dd class="sui-fact">
                    <span class="sui-fact__value">{{ $profile?->city ?: '—' }}</span>
                    @if ($canUpdate)
                        <x-staff-ui.button type="button" variant="ghost" size="sm" icon="pencil" icon-only aria-label="تعديل المدينة" data-sui-open-modal="edit-city" />
                    @endif
                </dd>
                <dt>المسمى الوظيفي</dt>
                <dd class="sui-fact">
                    <span class="sui-fact__value">{{ $profile?->job_title ?: '—' }}</span>
                    @if ($canUpdate)
                        <x-staff-ui.button type="button" variant="ghost" size="sm" icon="pencil" icon-only aria-label="تعديل المسمى الوظيفي" data-sui-open-modal="edit-job-title" />
                    @endif
                </dd>
                <dt>الحالة</dt>
                <dd>
                    @if ($active)
                        <x-staff-ui.status tone="success">نشط</x-staff-ui.status>
                    @else
                        <x-staff-ui.status tone="danger">معطّل</x-staff-ui.status>
                    @endif
                </dd>
                @if ($canViewMaskedIdentity || $canUpdateIdentity)
                    <dt>نوع الهوية</dt>
                    <dd class="sui-fact">
                        <span class="sui-fact__value">{{ $canViewMaskedIdentity ? ($beneficiary->identity_type?->label() ?? '—') : '—' }}</span>
                        @if ($canUpdateIdentity)
                            <x-staff-ui.button type="button" variant="ghost" size="sm" icon="pencil" icon-only aria-label="تعديل الهوية" data-sui-open-modal="edit-identity" />
                        @endif
                    </dd>
                @endif
                @if ($canViewMaskedIdentity)
                    <dt>رقم الهوية</dt>
                    <dd class="sui-fact">
                        <span class="sui-fact__value" data-identity-masked>{{ $maskedIdentity ?: '—' }}</span>
                        @if ($canRevealIdentity)
                            <x-staff-ui.button type="button" variant="ghost" size="sm" icon="eye" icon-only aria-label="إظهار رقم الهوية" data-sui-open-modal="reveal-identity" />
                        @endif
                    </dd>
                @endif
                <dt>النبذة</dt>
                <dd class="sui-fact">
                    <span class="sui-fact__value">{{ $profile?->bio ?: '—' }}</span>
                    @if ($canUpdate)
                        <x-staff-ui.button type="button" variant="ghost" size="sm" icon="pencil" icon-only aria-label="تعديل النبذة" data-sui-open-modal="edit-bio" />
                    @endif
                </dd>
                @if ($canUpdate)
                    <dt>إشعارات البريد</dt>
                    <dd class="sui-fact">
                        <span class="sui-fact__value">{{ $beneficiary->notify_email ? 'مفعّلة' : 'متوقفة' }}</span>
                        <x-staff-ui.button type="button" variant="ghost" size="sm" icon="pencil" icon-only aria-label="تعديل إشعارات البريد" data-sui-open-modal="edit-notify-email" />
                    </dd>
                @endif
            </dl>
        </section>

        <section class="sui-card">
            <div class="sui-card__head">
                <h2>الملف المهني</h2>
            </div>
            <div class="sui-card__body">
                <h3 class="sui-subhead">المهارات</h3>
                @if ($skills === [] && blank($profile?->iconic_skill))
                    <p>لا توجد مهارات مسجلة.</p>
                @else
                    <ul class="sui-plain-list">
                        @if (filled($profile?->iconic_skill))
                            <li><span>{{ $profile->iconic_skill }}</span><span>مهارة مميزة</span></li>
                        @endif
                        @foreach ($skills as $skill)
                            <li>
                                <span>{{ $skill['skill_name'] }}@if ($skill['category']) — {{ $skill['category'] }}@endif</span>
                                <span>{{ $skill['level'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <h3 class="sui-subhead">السيرة الذاتية</h3>
                <p>{{ $hasCvFile ? 'يوجد ملف سيرة مرفوع.' : 'لا يوجد ملف سيرة مرفوع.' }}</p>

                <h3 class="sui-subhead">التعليم</h3>
                @if ($education === [])
                    <p>لا يوجد تعليم مسجل.</p>
                @else
                    <ul class="sui-plain-list">
                        @foreach ($education as $item)
                            <li>
                                <span>{{ $item['institution'] }}@if ($item['degree_or_program']) — {{ $item['degree_or_program'] }}@endif @if ($item['field']) ({{ $item['field'] }})@endif</span>
                                <span>{{ $item['start_year'] }}@if ($item['end_year']) – {{ $item['end_year'] }}@endif</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        <section class="sui-card">
            <div class="sui-card__head">
                <h2>التسجيلات</h2>
            </div>
            <div class="sui-card__body">
                <h3 class="sui-subhead">البرامج</h3>
                @if ($beneficiary->programRegistrations->isEmpty())
                    <p>لا توجد تسجيلات في البرامج.</p>
                @else
                    <ul class="sui-plain-list">
                        @foreach ($beneficiary->programRegistrations as $registration)
                            <li>
                                <span>{{ $registration->trainingProgram?->title ?? 'برنامج' }}</span>
                                <x-staff-ui.status tone="info">{{ $registration->status?->label() }}</x-staff-ui.status>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <h3 class="sui-subhead">التطوع</h3>
                @if ($beneficiary->volunteerRegistrations->isEmpty())
                    <p>لا توجد تسجيلات تطوعية.</p>
                @else
                    <ul class="sui-plain-list">
                        @foreach ($beneficiary->volunteerRegistrations as $registration)
                            <li>
                                <span>{{ $registration->opportunity?->title ?? 'فرصة تطوع' }}</span>
                                <x-staff-ui.status tone="info">{{ $registration->status?->label() }}</x-staff-ui.status>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        <section class="sui-card">
            <div class="sui-card__head">
                <h2>الشهادات</h2>
            </div>
            <div class="sui-card__body">
                @if ($certificates->isEmpty())
                    <p>لا توجد شهادات.</p>
                @else
                    <ul class="sui-plain-list">
                        @foreach ($certificates as $certificate)
                            <li>
                                <span>{{ $certificate->certificateable?->title ?? 'شهادة' }} — {{ $certificate->certificate_number }}</span>
                                <span>{{ $certificate->issued_at?->toDateString() ?? '—' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        <section class="sui-card">
            <div class="sui-card__head">
                <h2>ملاحظات داخلية</h2>
            </div>
            <div class="sui-card__body">
                @forelse ($beneficiary->entityNotes->sortByDesc('created_at') as $note)
                    <article class="sui-note">
                        <p>{{ $note->body }}</p>
                        <small>{{ $note->creator?->name ?? '—' }} · {{ $note->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</small>
                    </article>
                @empty
                    <p>لا توجد ملاحظات.</p>
                @endforelse

                @if ($canUpdate)
                    <form class="sui-note-form" method="POST" action="{{ route('staff-ui.users.notes.store', $beneficiary) }}">
                        @csrf
                        <label class="sui-field__label" for="note-body">ملاحظة جديدة</label>
                        <div class="sui-note-compose">
                            <textarea class="sui-control" id="note-body" name="body" rows="4" placeholder="اكتب ملاحظة داخلية لفريق العمل…">{{ old('body') }}</textarea>
                            <x-staff-ui.button type="submit" variant="primary" size="sm" icon="plus" icon-only class="sui-note-compose__add" aria-label="إضافة ملاحظة" />
                        </div>
                        @error('body')
                            <p class="sui-field__error">{{ $message }}</p>
                        @enderror
                    </form>
                @endif
            </div>
        </section>

        @if ($canDeactivate && ! $beneficiary->isAnonymized())
            <div class="sui-account-action">
                @if ($beneficiary->is_active)
                    <x-staff-ui.button type="button" variant="danger" data-sui-open-modal="deactivate-beneficiary">تعطيل الحساب</x-staff-ui.button>
                @else
                    <form method="POST" action="{{ route('staff-ui.users.activation', $beneficiary) }}">
                        @csrf
                        <input type="hidden" name="action" value="activate">
                        <x-staff-ui.button type="submit" variant="primary">تفعيل الحساب</x-staff-ui.button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    @if ($canUpdate)
        <x-staff-ui.modal name="edit-name" title="تعديل الاسم" :open="$openField === 'name'">
            <form method="POST" action="{{ route('staff-ui.users.update', $beneficiary) }}">
                @csrf
                <input type="hidden" name="field" value="name">
                <div class="sui-form-grid">
                    <x-staff-ui.input name="first_name" label="الاسم" :value="old('first_name', $beneficiary->first_name)" :error="$errors->first('first_name')" />
                    <x-staff-ui.input name="father_name" label="اسم الأب" :value="old('father_name', $beneficiary->father_name)" :error="$errors->first('father_name')" />
                    <x-staff-ui.input name="grandfather_name" label="اسم الجد" :value="old('grandfather_name', $beneficiary->grandfather_name)" :error="$errors->first('grandfather_name')" />
                    <x-staff-ui.input name="family_name" label="اسم العائلة" :value="old('family_name', $beneficiary->family_name)" :error="$errors->first('family_name')" />
                </div>
                <div class="sui-modal__actions">
                    <x-staff-ui.button type="submit" size="sm">حفظ</x-staff-ui.button>
                    <x-staff-ui.button type="button" variant="ghost" size="sm" data-sui-modal-close>إلغاء</x-staff-ui.button>
                </div>
            </form>
        </x-staff-ui.modal>
        <x-staff-ui.modal name="edit-phone" title="تعديل الجوال" :open="$openField === 'phone'">
            <form method="POST" action="{{ route('staff-ui.users.update', $beneficiary) }}">
                @csrf
                <input type="hidden" name="field" value="phone">
                <x-staff-ui.input name="phone" label="الجوال" :value="old('phone', $beneficiary->phone)" :error="$errors->first('phone')" />
                <div class="sui-modal__actions">
                    <x-staff-ui.button type="submit" size="sm">حفظ</x-staff-ui.button>
                    <x-staff-ui.button type="button" variant="ghost" size="sm" data-sui-modal-close>إلغاء</x-staff-ui.button>
                </div>
            </form>
        </x-staff-ui.modal>
        <x-staff-ui.modal name="edit-gender" title="تعديل الجنس" :open="$openField === 'gender'">
            <form method="POST" action="{{ route('staff-ui.users.update', $beneficiary) }}">
                @csrf
                <input type="hidden" name="field" value="gender">
                <x-staff-ui.select name="gender" label="الجنس" :selected="old('gender', $profile?->gender?->value)" :options="['' => '—'] + \App\Enums\ProfileGender::options()" :error="$errors->first('gender')" />
                <div class="sui-modal__actions">
                    <x-staff-ui.button type="submit" size="sm">حفظ</x-staff-ui.button>
                    <x-staff-ui.button type="button" variant="ghost" size="sm" data-sui-modal-close>إلغاء</x-staff-ui.button>
                </div>
            </form>
        </x-staff-ui.modal>
        <x-staff-ui.modal name="edit-birth-date" title="تعديل تاريخ الميلاد" :open="$openField === 'birth_date'">
            <form method="POST" action="{{ route('staff-ui.users.update', $beneficiary) }}">
                @csrf
                <input type="hidden" name="field" value="birth_date">
                <x-staff-ui.date name="birth_date" label="تاريخ الميلاد" :value="old('birth_date', $profile?->birth_date?->toDateString())" :error="$errors->first('birth_date')" />
                <div class="sui-modal__actions">
                    <x-staff-ui.button type="submit" size="sm">حفظ</x-staff-ui.button>
                    <x-staff-ui.button type="button" variant="ghost" size="sm" data-sui-modal-close>إلغاء</x-staff-ui.button>
                </div>
            </form>
        </x-staff-ui.modal>
        <x-staff-ui.modal name="edit-city" title="تعديل المدينة" :open="$openField === 'city'">
            <form method="POST" action="{{ route('staff-ui.users.update', $beneficiary) }}">
                @csrf
                <input type="hidden" name="field" value="city">
                <x-staff-ui.input name="city" label="المدينة" :value="old('city', $profile?->city)" :error="$errors->first('city')" />
                <div class="sui-modal__actions">
                    <x-staff-ui.button type="submit" size="sm">حفظ</x-staff-ui.button>
                    <x-staff-ui.button type="button" variant="ghost" size="sm" data-sui-modal-close>إلغاء</x-staff-ui.button>
                </div>
            </form>
        </x-staff-ui.modal>
        <x-staff-ui.modal name="edit-job-title" title="تعديل المسمى الوظيفي" :open="$openField === 'job_title'">
            <form method="POST" action="{{ route('staff-ui.users.update', $beneficiary) }}">
                @csrf
                <input type="hidden" name="field" value="job_title">
                <x-staff-ui.input name="job_title" label="المسمى الوظيفي" :value="old('job_title', $profile?->job_title)" :error="$errors->first('job_title')" />
                <div class="sui-modal__actions">
                    <x-staff-ui.button type="submit" size="sm">حفظ</x-staff-ui.button>
                    <x-staff-ui.button type="button" variant="ghost" size="sm" data-sui-modal-close>إلغاء</x-staff-ui.button>
                </div>
            </form>
        </x-staff-ui.modal>
        <x-staff-ui.modal name="edit-bio" title="تعديل النبذة" :open="$openField === 'bio'">
            <form method="POST" action="{{ route('staff-ui.users.update', $beneficiary) }}">
                @csrf
                <input type="hidden" name="field" value="bio">
                <x-staff-ui.textarea name="bio" label="النبذة" rows="3">{{ old('bio', $profile?->bio) }}</x-staff-ui.textarea>
                @error('bio')
                    <p class="sui-field__error">{{ $message }}</p>
                @enderror
                <div class="sui-modal__actions">
                    <x-staff-ui.button type="submit" size="sm">حفظ</x-staff-ui.button>
                    <x-staff-ui.button type="button" variant="ghost" size="sm" data-sui-modal-close>إلغاء</x-staff-ui.button>
                </div>
            </form>
        </x-staff-ui.modal>
        <x-staff-ui.modal name="edit-notify-email" title="تعديل إشعارات البريد" :open="$openField === 'notify_email'">
            <form method="POST" action="{{ route('staff-ui.users.update', $beneficiary) }}">
                @csrf
                <input type="hidden" name="field" value="notify_email">
                <x-staff-ui.checkbox name="notify_email" label="إشعارات البريد" value="1" :checked="old('notify_email', $beneficiary->notify_email)" />
                <div class="sui-modal__actions">
                    <x-staff-ui.button type="submit" size="sm">حفظ</x-staff-ui.button>
                    <x-staff-ui.button type="button" variant="ghost" size="sm" data-sui-modal-close>إلغاء</x-staff-ui.button>
                </div>
            </form>
        </x-staff-ui.modal>
    @endif

    @if ($canUpdateSensitive)
        <x-staff-ui.modal name="edit-email" title="تعديل البريد" :open="$openField === 'email'">
            <form method="POST" action="{{ route('staff-ui.users.update', $beneficiary) }}">
                @csrf
                <input type="hidden" name="field" value="email">
                <x-staff-ui.input name="email" type="email" label="البريد الإلكتروني" :value="old('email', $beneficiary->email)" :error="$errors->first('email')" />
                <div class="sui-modal__actions">
                    <x-staff-ui.button type="submit" size="sm">حفظ</x-staff-ui.button>
                    <x-staff-ui.button type="button" variant="ghost" size="sm" data-sui-modal-close>إلغاء</x-staff-ui.button>
                </div>
            </form>
        </x-staff-ui.modal>
    @endif

    @if ($canDeactivate && $beneficiary->is_active && ! $beneficiary->isAnonymized())
        <x-staff-ui.modal name="deactivate-beneficiary" title="تعطيل الحساب">
            <p>لن يتمكن هذا المستفيد من تسجيل الدخول. يبقى سجله محفوظاً.</p>
            <form method="POST" action="{{ route('staff-ui.users.activation', $beneficiary) }}">
                @csrf
                <input type="hidden" name="action" value="deactivate">
                <div class="sui-modal__actions">
                    <x-staff-ui.button type="submit" variant="danger" size="sm">تعطيل</x-staff-ui.button>
                    <x-staff-ui.button type="button" variant="ghost" size="sm" data-sui-modal-close>إلغاء</x-staff-ui.button>
                </div>
            </form>
        </x-staff-ui.modal>
    @endif

    @if ($canUpdateIdentity)
        <x-staff-ui.modal name="edit-identity" title="تعديل الهوية" :open="$openField === 'identity'">
            <form method="POST" action="{{ route('staff-ui.users.identity.update', $beneficiary) }}">
                @csrf
                <x-staff-ui.select name="identity_type" label="نوع الهوية" :selected="old('identity_type', $beneficiary->identity_type?->value)" :options="['national_id' => 'هوية وطنية', 'iqama' => 'إقامة']" :error="$errors->first('identity_type')" />
                <x-staff-ui.input name="identity_number" label="رقم الهوية" :value="old('identity_number')" :error="$errors->first('identity_number')" />
                <x-staff-ui.textarea name="reason" label="سبب التعديل" rows="3">{{ old('reason') }}</x-staff-ui.textarea>
                @error('reason')
                    <p class="sui-field__error">{{ $message }}</p>
                @enderror
                <div class="sui-modal__actions">
                    <x-staff-ui.button type="submit" size="sm">حفظ</x-staff-ui.button>
                    <x-staff-ui.button type="button" variant="ghost" size="sm" data-sui-modal-close>إلغاء</x-staff-ui.button>
                </div>
            </form>
        </x-staff-ui.modal>
    @endif

    @if ($canRevealIdentity)
        <x-staff-ui.modal name="reveal-identity" title="إظهار رقم الهوية">
            <form id="reveal-identity-form" method="POST" action="{{ route('admin.beneficiaries.identity.reveal', $beneficiary) }}">
                @csrf
                <x-staff-ui.input name="password" type="password" label="كلمة مرورك" autocomplete="current-password" />
                <x-staff-ui.textarea name="reason" label="سبب الإظهار" rows="3" placeholder="اذكر سبب الاطلاع" />
                <p class="sui-field__error" data-identity-error hidden></p>
                <p data-identity-result hidden></p>
                <div class="sui-modal__actions">
                    <x-staff-ui.button type="submit" size="sm">إظهار</x-staff-ui.button>
                    <x-staff-ui.button type="button" variant="ghost" size="sm" data-sui-modal-close>إغلاق</x-staff-ui.button>
                </div>
            </form>
        </x-staff-ui.modal>
        <script>
            document.getElementById('reveal-identity-form')?.addEventListener('submit', async (event) => {
                event.preventDefault();
                const form = event.currentTarget;
                const error = form.querySelector('[data-identity-error]');
                const result = form.querySelector('[data-identity-result]');
                error.hidden = true;
                result.hidden = true;
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const message = payload.message
                        || payload.errors?.password?.[0]
                        || payload.errors?.reason?.[0]
                        || 'تعذر إظهار رقم الهوية.';
                    error.textContent = message;
                    error.hidden = false;
                    return;
                }
                const masked = document.querySelector('[data-identity-masked]');
                if (masked) masked.textContent = payload.identity_number;
                result.textContent = 'يختفي الرقم تلقائياً عند انتهاء المهلة.';
                result.hidden = false;
            });
        </script>
    @endif
</x-staff-ui.layout>
