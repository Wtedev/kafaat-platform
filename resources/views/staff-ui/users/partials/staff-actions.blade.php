@php
    use App\Services\Rbac\RbacCatalog;
    use App\Services\StaffUi\StaffInvitationService;
    use App\Support\UserAccountRoleForm;

    $pending = $pending ?? app(StaffInvitationService::class)->isPending($member);
    $isSelf = $isSelf ?? auth()->id() === $member->id;
    $roleName = $member->isAdmin() ? 'admin' : 'staff';
    $roleLabel = RbacCatalog::roleArabicLabel($roleName);
    $showRole = $canChangeRole;
    $reinvite = ($canInvite ?? false) && app(StaffInvitationService::class)->requiresReinvite($member);
    $lastActiveAdmin = $member->is_active
        && $member->isAdmin()
        && UserAccountRoleForm::activeAdminCount() <= 1;
    $showActivation = $canActivate && ! $pending && ! $reinvite && (! $member->isProtectedAdminUser() || $isSelf);
    $showInvite = $canInvite && $pending;
    $showAccount = (bool) ($includeAccountActions ?? false);
    $showReset = $showAccount && ($canResetPassword ?? false);
    $showResetDisabled = $showAccount && ! $pending && ! $member->is_active && ($canUpdate ?? false);
    $showMenu = $showRole || $showActivation || $showInvite || $reinvite || ($showAccount && ($canUpdate ?? false));
@endphp

@if ($showMenu)
    <x-staff-ui.dropdown class="sui-staff-menu" align="end">
        <x-slot:trigger>
            <button type="button" class="sui-icon-btn" aria-label="إجراءات {{ $member->name }}">
                <i data-lucide="ellipsis" class="sui-icon"></i>
            </button>
        </x-slot:trigger>
        @if ($showAccount && ($canUpdate ?? false))
            <button type="button" class="sui-menu-item" data-sui-open-modal="staff-edit">تعديل البيانات</button>
        @endif
        @if ($showReset)
            <button type="button" class="sui-menu-item" data-sui-open-modal="staff-password-reset">إرسال رابط إعادة تعيين كلمة المرور</button>
        @elseif ($showResetDisabled)
            <span class="sui-tip" title="لا يمكن إرسال رابط لحساب معطّل.">
                <button type="button" class="sui-menu-item" disabled>إرسال رابط إعادة تعيين كلمة المرور</button>
            </span>
        @endif
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
        @if ($reinvite)
            <form method="POST" action="{{ route('staff-ui.users.staff.invitation', $member) }}" data-sui-staff-form>
                @csrf
                <input type="hidden" name="action" value="reinvite">
                <button type="submit" class="sui-menu-item">إعادة الدعوة</button>
            </form>
        @endif
        @if ($showActivation)
            @if ($isSelf)
                <span class="sui-tip" title="لا يمكنك تعطيل حسابك.">
                    <button type="button" class="sui-menu-item sui-menu-item--danger" disabled>تعطيل الحساب</button>
                </span>
            @elseif ($lastActiveAdmin)
                <span class="sui-tip" title="لا يمكن تعطيل آخر مدير نشط.">
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
