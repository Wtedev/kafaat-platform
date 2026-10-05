@php
    use App\Services\Rbac\RbacCatalog;
    use App\Services\StaffUi\StaffInvitationService;

    $filtersActive = $search !== '' || $role !== '' || $status !== '';
@endphp

<x-staff-ui.layout :name="$staffName" :email="$staffEmail" crumb="المستخدمين" :dashboard-active="false" active-nav="users">
    @if (session('status'))
        <div hidden data-sui-flash data-tone="success" data-title="تم" data-body="{{ session('status') }}"></div>
    @endif
    @foreach ($errors->all() as $error)
        <div hidden data-sui-flash data-tone="danger" data-title="تعذر تنفيذ الإجراء" data-body="{{ $error }}"></div>
    @endforeach

    <header class="sui-page-head sui-staff-head">
        <h1>المستخدمين</h1>
        @if ($canInvite)
            <x-staff-ui.button type="button" variant="primary" size="sm" icon="user-plus" data-sui-open-modal="invite-staff">دعوة موظف</x-staff-ui.button>
        @endif
    </header>

    <x-staff-ui.tabs group="users" :tabs="[
        ['id' => 'beneficiaries', 'label' => 'المستفيدين', 'href' => route('staff-ui.users.index')],
        ['id' => 'staff', 'label' => 'الموظفين', 'active' => true, 'count' => $directory->total(), 'href' => route('staff-ui.users.staff.index')],
    ]" />

    <x-staff-ui.data-table
        server
        data-sui-staff-directory
        :class="$directory->isEmpty() ? 'is-empty sui-staff-directory' : 'sui-staff-directory'"
        :columns="[
            ['label' => 'الموظف', 'key' => 'person'],
            ['label' => 'الدور', 'key' => 'role'],
            ['label' => 'الحالة', 'key' => 'status'],
            ['label' => 'آخر دخول', 'key' => 'last_login'],
            ['label' => 'إجراءات', 'key' => 'actions'],
        ]"
    >
        <x-slot:filters>
            <form class="sui-table__filters sui-staff-filters" method="GET" action="{{ route('staff-ui.users.staff.index') }}" data-sui-staff-filters>
                <label class="sui-search sui-staff-search">
                    <i data-lucide="search" class="sui-icon"></i>
                    <input type="search" name="q" value="{{ $search }}" placeholder="الاسم أو البريد" aria-label="بحث" data-sui-staff-search autocomplete="off">
                </label>
                <x-staff-ui.select
                    name="role"
                    label="الدور"
                    :selected="$role"
                    :options="['' => 'الكل', 'admin' => RbacCatalog::roleArabicLabel('admin'), 'staff' => RbacCatalog::roleArabicLabel('staff')]"
                />
                <x-staff-ui.select
                    name="status"
                    label="الحالة"
                    :selected="$status"
                    :options="['' => 'الكل', 'active' => 'نشط', 'inactive' => 'معطّل', 'invited' => 'مدعو']"
                />
                @if ($filtersActive)
                    <a class="sui-staff-clear" href="{{ route('staff-ui.users.staff.index') }}">مسح الفلاتر</a>
                @endif
                <span class="sui-staff-count">{{ $directory->total() }} موظف</span>
            </form>
        </x-slot:filters>

        <x-slot:emptyState>
            <i data-lucide="search-x" class="sui-icon"></i>
            <p>لا توجد نتائج مطابقة.</p>
            @if ($filtersActive)
                <a class="sui-staff-clear" href="{{ route('staff-ui.users.staff.index') }}">مسح الفلاتر</a>
            @endif
        </x-slot:emptyState>

        @foreach ($directory as $member)
            @php
                $pending = app(StaffInvitationService::class)->isPending($member);
                $isSelf = auth()->id() === $member->id;
                $roleName = $member->isAdmin() ? 'admin' : 'staff';
                $roleLabel = RbacCatalog::roleArabicLabel($roleName);
                $loginAt = $member->last_login_at?->timezone(config('app.timezone'));
                $showRole = $canChangeRole;
                $showActivation = $canActivate && ! $pending && (! $member->isProtectedAdminUser() || $isSelf);
                $showInvite = $canInvite && $pending;
                $showMenu = $showRole || $showActivation || $showInvite;
            @endphp
            <tr>
                <td class="sui-staff-person" data-label="الموظف">
                    <div class="sui-person">
                        <x-staff-ui.avatar :name="$member->name" />
                        <div class="sui-person__text">
                            <div class="sui-person__name">
                                <strong>{{ $member->name }}</strong>
                                @if ($isSelf)
                                    <span class="sui-you">أنت</span>
                                @endif
                            </div>
                            <div class="sui-person__email">{{ $member->email }}</div>
                        </div>
                    </div>
                </td>
                <td class="sui-staff-role" data-label="الدور">
                    <span class="sui-role-badge">{{ $roleLabel }}</span>
                </td>
                <td class="sui-staff-status" data-label="الحالة">
                    @if ($member->is_active)
                        <x-staff-ui.status tone="success">نشط</x-staff-ui.status>
                    @elseif ($pending)
                        <x-staff-ui.status tone="warning">مدعو</x-staff-ui.status>
                    @else
                        <x-staff-ui.status tone="muted">معطّل</x-staff-ui.status>
                    @endif
                </td>
                <td class="sui-staff-login" data-label="آخر دخول">
                    @if ($loginAt)
                        <time datetime="{{ $loginAt->toIso8601String() }}" title="{{ $loginAt->format('Y-m-d H:i') }}">{{ $loginAt->locale('ar')->diffForHumans() }}</time>
                    @else
                        <span class="sui-login-never">لم يسجل دخول بعد</span>
                    @endif
                </td>
                <td class="sui-staff-actions" data-label="إجراءات">
                    @if ($showMenu)
                        <x-staff-ui.dropdown class="sui-staff-menu" align="end">
                            <x-slot:trigger>
                                <button type="button" class="sui-icon-btn" aria-label="إجراءات {{ $member->name }}">
                                    <i data-lucide="ellipsis" class="sui-icon"></i>
                                </button>
                            </x-slot:trigger>
                            @if ($showRole)
                                @if ($isSelf)
                                    <span class="sui-tip" title="لا يمكنك تغيير دورك.">
                                        <button type="button" class="sui-menu-item" disabled>تغيير الدور</button>
                                    </span>
                                @else
                                    <button
                                        type="button"
                                        class="sui-menu-item"
                                        data-sui-open-modal="staff-role"
                                        data-sui-staff-action="role"
                                        data-action="{{ route('staff-ui.users.staff.role', $member) }}"
                                        data-role="{{ $roleName }}"
                                        data-role-label="{{ $roleLabel }}"
                                    >تغيير الدور</button>
                                @endif
                            @endif
                            @if ($showInvite)
                                <form method="POST" action="{{ route('staff-ui.users.staff.invitation', $member) }}" data-sui-staff-form>
                                    @csrf
                                    <input type="hidden" name="action" value="resend">
                                    <button type="submit" class="sui-menu-item">إعادة إرسال الدعوة</button>
                                </form>
                                <form method="POST" action="{{ route('staff-ui.users.staff.invitation', $member) }}" data-sui-staff-form>
                                    @csrf
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="sui-menu-item">إلغاء الدعوة</button>
                                </form>
                            @endif
                            @if ($showActivation)
                                @if ($isSelf)
                                    <span class="sui-tip" title="لا يمكنك تعطيل حسابك.">
                                        <button type="button" class="sui-menu-item sui-menu-item--danger" disabled>تعطيل الحساب</button>
                                    </span>
                                @else
                                    <button
                                        type="button"
                                        @class(['sui-menu-item', 'sui-menu-item--danger' => $member->is_active])
                                        data-sui-open-modal="staff-activation"
                                        data-sui-staff-action="activation"
                                        data-action="{{ route('staff-ui.users.staff.activation', $member) }}"
                                        data-activation="{{ $member->is_active ? 'deactivate' : 'activate' }}"
                                        data-name="{{ $member->name }}"
                                    >{{ $member->is_active ? 'تعطيل الحساب' : 'تفعيل الحساب' }}</button>
                                @endif
                            @endif
                        </x-staff-ui.dropdown>
                    @endif
                </td>
            </tr>
        @endforeach

        <x-slot:footer>
            <div class="sui-pager">
                <span class="sui-pager__label">{{ $directory->firstItem() ?? 0 }}–{{ $directory->lastItem() ?? 0 }} من {{ $directory->total() }}</span>
                @foreach ([
                    ['url' => $directory->url(1), 'enabled' => ! $directory->onFirstPage(), 'icon' => 'chevrons-right', 'label' => 'الصفحة الأولى'],
                    ['url' => $directory->previousPageUrl(), 'enabled' => ! $directory->onFirstPage(), 'icon' => 'chevron-right', 'label' => 'السابق'],
                    ['url' => $directory->nextPageUrl(), 'enabled' => $directory->hasMorePages(), 'icon' => 'chevron-left', 'label' => 'التالي'],
                    ['url' => $directory->url($directory->lastPage()), 'enabled' => $directory->hasMorePages(), 'icon' => 'chevrons-left', 'label' => 'الصفحة الأخيرة'],
                ] as $pager)
                    @if ($pager['enabled'])
                        <a class="sui-icon-btn" href="{{ $pager['url'] }}" aria-label="{{ $pager['label'] }}"><i data-lucide="{{ $pager['icon'] }}" class="sui-icon"></i></a>
                    @else
                        <span class="sui-icon-btn" aria-disabled="true" aria-label="{{ $pager['label'] }}"><i data-lucide="{{ $pager['icon'] }}" class="sui-icon"></i></span>
                    @endif
                @endforeach
            </div>
        </x-slot:footer>
    </x-staff-ui.data-table>

    @if ($canChangeRole)
        <x-staff-ui.modal name="staff-role" title="تغيير الدور" :open="$errors->has('role') && ! $errors->has('name') && ! $errors->has('email')">
            <form method="POST" action="{{ route('staff-ui.users.staff.index') }}" data-sui-role-form data-sui-staff-form>
                @csrf
                <p class="sui-staff-current">الدور الحالي: <strong data-sui-role-current>{{ RbacCatalog::roleArabicLabel(old('role', 'staff')) }}</strong></p>
                <x-staff-ui.select id="staff-role-select" name="role" label="الدور الجديد" :selected="old('role', 'staff')" :options="$roles" data-sui-role-select />
                <p class="sui-staff-role-note" data-sui-role-note>
                    {{ old('role', 'staff') === 'admin' ? 'وصول كامل لإدارة المنصة والموظفين والأدوار.' : 'صلاحيات العمل اليومية الممنوحة لهذا الحساب، دون إدارة الأدوار.' }}
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
            <form method="POST" action="{{ route('staff-ui.users.staff.index') }}" data-sui-activation-form data-sui-staff-form>
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

    @if ($canInvite)
        <x-staff-ui.modal name="invite-staff" title="دعوة موظف" :open="$errors->has('name') || $errors->has('email')">
            <form method="POST" action="{{ route('staff-ui.users.staff.store') }}" data-sui-staff-form>
                @csrf
                <x-staff-ui.input name="name" label="الاسم" :value="old('name')" :error="$errors->first('name')" required />
                <x-staff-ui.input name="email" type="email" label="البريد الإلكتروني" :value="old('email')" :error="$errors->first('email')" required />
                <x-staff-ui.select id="invite-role" name="role" label="الدور" :selected="old('role', 'staff')" :options="$roles" :error="$errors->first('role')" />
                <div class="sui-modal__actions">
                    <x-staff-ui.button type="submit" variant="primary" size="sm">إرسال الدعوة</x-staff-ui.button>
                    <x-staff-ui.button type="button" variant="ghost" size="sm" data-sui-modal-close>إلغاء</x-staff-ui.button>
                </div>
            </form>
        </x-staff-ui.modal>
    @endif
</x-staff-ui.layout>
