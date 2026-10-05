@php
    use App\Services\Rbac\RbacCatalog;

    $roleName = $member->isAdmin() ? 'admin' : 'staff';
    $roleLabel = RbacCatalog::roleArabicLabel($roleName);
    $loginAt = $member->last_login_at?->timezone(config('app.timezone'));
    $createdAt = $member->created_at?->timezone(config('app.timezone'));
    $openEdit = $errors->hasAny(['first_name', 'father_name', 'grandfather_name', 'family_name', 'email']);
@endphp

<x-staff-ui.layout :name="$staffName" :email="$staffEmail" crumb="المستخدمين" :dashboard-active="false" active-nav="users">
    @if (session('status'))
        <div hidden data-sui-flash data-tone="success" data-title="تم" data-body="{{ session('status') }}"></div>
    @endif
    @foreach ($errors->all() as $error)
        <div hidden data-sui-flash data-tone="danger" data-title="تعذر تنفيذ الإجراء" data-body="{{ $error }}"></div>
    @endforeach

    <header class="sui-page-head sui-staff-head">
        <div>
            <a class="sui-back" href="{{ route('staff-ui.users.staff.index') }}">الموظفين</a>
            <div class="sui-page-head__name">
                <h1>{{ $member->name }}</h1>
                @if ($isSelf)
                    <span class="sui-you">أنت</span>
                @endif
            </div>
        </div>
        @include('staff-ui.users.partials.staff-actions', ['includeAccountActions' => true])
    </header>

    <div class="sui-stack">
        <section class="sui-card">
            <div class="sui-card__head">
                <h2>بيانات الموظف</h2>
            </div>
            <dl class="sui-facts">
                <dt>الاسم</dt>
                <dd>{{ $member->name }}</dd>
                <dt>البريد</dt>
                <dd>{{ $member->email }}</dd>
                @if (filled($member->phone))
                    <dt>الجوال</dt>
                    <dd>{{ $member->phone }}</dd>
                @endif
                <dt>الدور</dt>
                <dd><span class="sui-role-badge">{{ $roleLabel }}</span></dd>
                <dt>الحالة</dt>
                <dd>
                    @if ($member->is_active)
                        <x-staff-ui.status tone="success">نشط</x-staff-ui.status>
                    @elseif ($pending)
                        <x-staff-ui.status tone="warning">مدعو</x-staff-ui.status>
                    @else
                        <x-staff-ui.status tone="muted">معطّل</x-staff-ui.status>
                    @endif
                </dd>
                <dt>تاريخ إنشاء الحساب</dt>
                <dd>{{ $createdAt?->format('Y-m-d') }}</dd>
                <dt>آخر دخول</dt>
                <dd>
                    @if ($loginAt)
                        <time datetime="{{ $loginAt->toIso8601String() }}" title="{{ $loginAt->format('Y-m-d H:i') }}">{{ $loginAt->locale('ar')->diffForHumans() }}</time>
                    @else
                        <span class="sui-login-never">لم يسجل دخول بعد</span>
                    @endif
                </dd>
                @if ($pending)
                    <dt>حالة الدعوة</dt>
                    <dd>بانتظار تعيين كلمة المرور</dd>
                @endif
            </dl>
        </section>

        <section class="sui-card">
            <div class="sui-card__head">
                <h2>الصلاحيات الفعالة</h2>
            </div>
            <div class="sui-card__body">
                @forelse ($permissionGroups as $group)
                    <section class="sui-perm-group">
                        <header class="sui-perm-group__head">
                            <h3>{{ $group['label'] }}</h3>
                            @if ($group['sees'])
                                <span class="sui-role-badge">يشوف</span>
                            @endif
                            @if ($group['edits'])
                                <span class="sui-role-badge">يعدل</span>
                            @endif
                        </header>
                        <ul class="sui-perm-list">
                            @foreach ($group['items'] as $item)
                                <li>
                                    <span>{{ $item['label'] }}</span>
                                    @if ($item['level'])
                                        <span class="sui-role-badge">{{ $item['level'] }}</span>
                                    @endif
                                    @if ($item['from_role'])
                                        <span class="sui-perm-source">من الدور</span>
                                    @endif
                                    @if ($item['direct'])
                                        <span class="sui-perm-source">مباشرة</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @empty
                    <p class="sui-login-never">لا توجد صلاحيات فعالة.</p>
                @endforelse
                <p class="sui-perm-note">تعديل الصلاحيات من قسم الصلاحيات</p>
            </div>
        </section>
    </div>

    @if ($canUpdate)
        <x-staff-ui.modal name="staff-edit" title="تعديل البيانات" :open="$openEdit">
            <form method="POST" action="{{ route('staff-ui.users.staff.update', $member) }}">
                @csrf
                <x-staff-ui.input name="first_name" label="الاسم" :value="old('first_name', $member->first_name)" :error="$errors->first('first_name')" required />
                <x-staff-ui.input name="father_name" label="اسم الأب" :value="old('father_name', $member->father_name)" :error="$errors->first('father_name')" required />
                <x-staff-ui.input name="grandfather_name" label="اسم الجد" :value="old('grandfather_name', $member->grandfather_name)" :error="$errors->first('grandfather_name')" required />
                <x-staff-ui.input name="family_name" label="اسم العائلة" :value="old('family_name', $member->family_name)" :error="$errors->first('family_name')" required />
                @if ($isSelf)
                    <p class="sui-perm-note">لا يمكنك تغيير بريدك من هنا. <a href="{{ route('staff-ui.profile') }}">غيّره من ملفك الشخصي</a>.</p>
                @else
                    <x-staff-ui.input name="email" type="email" label="البريد الإلكتروني" :value="old('email', $member->email)" :error="$errors->first('email')" required />
                @endif
                <div class="sui-modal__actions">
                    <x-staff-ui.button type="submit" variant="primary" size="sm">حفظ</x-staff-ui.button>
                    <x-staff-ui.button type="button" variant="ghost" size="sm" data-sui-modal-close>إلغاء</x-staff-ui.button>
                </div>
            </form>
        </x-staff-ui.modal>
    @endif

    @if ($canResetPassword)
        <x-staff-ui.modal name="staff-password-reset" title="إعادة تعيين كلمة المرور" :open="$errors->has('password_reset')">
            <form method="POST" action="{{ route('staff-ui.users.staff.password-reset', $member) }}">
                @csrf
                <p>سيصل {{ $member->name }} رابط لتعيين كلمة مرور جديدة على بريده الإلكتروني.</p>
                <div class="sui-modal__actions">
                    <x-staff-ui.button type="submit" variant="primary" size="sm">إرسال الرابط</x-staff-ui.button>
                    <x-staff-ui.button type="button" variant="ghost" size="sm" data-sui-modal-close>إلغاء</x-staff-ui.button>
                </div>
            </form>
        </x-staff-ui.modal>
    @endif

    @if ($canChangeRole)
        <x-staff-ui.modal name="staff-role" title="تغيير الدور" :open="$errors->has('role')">
            <form method="POST" action="{{ route('staff-ui.users.staff.role', $member) }}" data-sui-role-form data-sui-staff-form>
                @csrf
                <p class="sui-staff-current">الدور الحالي: <strong data-sui-role-current>{{ $roleLabel }}</strong></p>
                <x-staff-ui.select id="staff-role-select" name="role" label="الدور الجديد" :selected="old('role', $roleName)" :options="$roles" data-sui-role-select />
                <p class="sui-staff-role-note" data-sui-role-note>
                    {{ $roleName === 'admin' ? 'وصول كامل لإدارة المنصة والموظفين والأدوار.' : 'صلاحيات العمل اليومية الممنوحة لهذا الحساب، دون إدارة الأدوار.' }}
                </p>
                <div class="sui-modal__actions">
                    <x-staff-ui.button type="submit" variant="primary" size="sm">تأكيد</x-staff-ui.button>
                    <x-staff-ui.button type="button" variant="ghost" size="sm" data-sui-modal-close>إلغاء</x-staff-ui.button>
                </div>
            </form>
        </x-staff-ui.modal>
    @endif

    @if ($canActivate)
        <x-staff-ui.modal name="staff-activation" title="تعطيل الحساب">
            <form method="POST" action="{{ route('staff-ui.users.staff.activation', $member) }}" data-sui-activation-form data-sui-staff-form>
                @csrf
                <input type="hidden" name="action" value="deactivate">
                <p data-sui-activation-text>سيتم تسجيل خروج هذا الموظف فورًا، ولن يتمكن من الدخول حتى يُفعّل الحساب.</p>
                <div class="sui-modal__actions">
                    <button type="submit" class="sui-btn sui-btn--danger sui-btn--sm" data-sui-activation-confirm>تعطيل الحساب</button>
                    <x-staff-ui.button type="button" variant="ghost" size="sm" data-sui-modal-close>إلغاء</x-staff-ui.button>
                </div>
            </form>
        </x-staff-ui.modal>
    @endif
</x-staff-ui.layout>
