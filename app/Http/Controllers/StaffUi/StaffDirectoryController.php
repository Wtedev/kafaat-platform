<?php

namespace App\Http\Controllers\StaffUi;

use App\Enums\AuditLogResult;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Privacy\AccountDeactivationService;
use App\Services\Rbac\RbacCatalog;
use App\Services\StaffUi\StaffDirectoryIndex;
use App\Services\StaffUi\StaffInvitationService;
use App\Support\StaffUi\StaffUiAccess;
use App\Support\UserAccountRoleForm;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffDirectoryController extends Controller
{
    public function index(Request $request, StaffDirectoryIndex $index): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('users.view'), 403);

        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $role = (string) $request->query('role', '');
        $status = (string) $request->query('status', '');

        if (! in_array($role, ['', ...StaffInvitationService::ROLES], true)) {
            $role = '';
        }

        if (! in_array($status, ['', 'active', 'inactive', 'invited'], true)) {
            $status = '';
        }

        return view('staff-ui.users.staff', [
            'staffName' => $actor->name,
            'staffEmail' => $actor->email,
            'directory' => $index->paginate($search, $role, $status, (int) $request->query('page', 1)),
            'search' => $search,
            'role' => $role,
            'status' => $status,
            'canInvite' => $actor->can('users.create') && StaffUiAccess::invitesEnabled(),
            'canAssignAdmin' => $actor->can('updateRole'),
            'canChangeRole' => $actor->can('updateRole'),
            'canActivate' => $actor->can('users.activate'),
            'roles' => $this->roleOptions($actor),
        ]);
    }

    public function store(Request $request, StaffInvitationService $invitations): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless(StaffUiAccess::invitesEnabled(), 403);
        abort_unless($actor->can('users.create'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::in(StaffInvitationService::ROLES)],
        ], [
            'name.required' => 'الاسم مطلوب.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'role.required' => 'الدور مطلوب.',
            'role.in' => 'الدور غير معروف.',
        ]);

        if ($data['role'] === RbacCatalog::ROLE_ADMIN) {
            abort_unless($actor->can('updateRole'), 403);
        }

        $invitations->invite(trim($data['name']), $data['email'], $data['role']);

        return redirect()
            ->route('staff-ui.users.staff.index')
            ->with('status', 'تم إرسال الدعوة.');
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->assertStaffMember($user);
        abort_unless($actor->can('updateRole'), 403);
        abort_if($actor->is($user), 403);

        $data = $request->validate([
            'role' => ['required', Rule::in(StaffInvitationService::ROLES)],
        ], [
            'role.required' => 'الدور مطلوب.',
            'role.in' => 'الدور غير معروف.',
        ]);

        UserAccountRoleForm::syncAssignedRole($actor, $user, $data['role'], $request);

        return redirect()
            ->route('staff-ui.users.staff.index')
            ->with('status', 'تم تغيير الدور.');
    }

    public function activation(Request $request, User $user, AccountDeactivationService $deactivation, AuditLogger $auditLogger, StaffInvitationService $invitations): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->assertStaffMember($user);
        abort_unless($actor->can('users.activate'), 403);
        abort_if($actor->is($user), 403);
        abort_if($invitations->isPending($user), 422);

        $action = (string) $request->input('action');

        if ($action === 'deactivate') {
            try {
                $deactivation->deactivate($user, $actor, request: $request);
            } catch (AuthorizationException) {
                abort(403);
            }
            $message = 'تم تعطيل الحساب.';
        } elseif ($action === 'activate') {
            if (! $user->is_active) {
                $user->update(['is_active' => true]);
                $auditLogger->recordOrFail(
                    $actor,
                    'account.reactivated',
                    AuditLogResult::Success,
                    $user,
                    request: $request,
                );
            }
            $message = 'تم تفعيل الحساب.';
        } else {
            abort(422);
        }

        return redirect()
            ->route('staff-ui.users.staff.index')
            ->with('status', $message);
    }

    public function invitation(Request $request, User $user, StaffInvitationService $invitations): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->assertStaffMember($user);
        abort_unless(StaffUiAccess::invitesEnabled(), 403);
        abort_unless($actor->can('users.create'), 403);

        $action = (string) $request->input('action');

        if ($action === 'resend') {
            $invitations->resend($user);
            $message = 'تم إعادة إرسال الدعوة.';
        } elseif ($action === 'cancel') {
            $invitations->cancel($user);
            $message = 'تم إلغاء الدعوة.';
        } else {
            abort(422);
        }

        return redirect()
            ->route('staff-ui.users.staff.index')
            ->with('status', $message);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->canAccessFilamentAdmin(), 403);

        return $actor;
    }

    private function assertStaffMember(User $user): void
    {
        abort_unless($user->isAdminOrStaff(), 404);
        abort_if($user->isAnonymized(), 404);
    }

    /**
     * @return array<string, string>
     */
    private function roleOptions(User $actor): array
    {
        $options = [
            RbacCatalog::ROLE_STAFF => RbacCatalog::roleArabicLabel(RbacCatalog::ROLE_STAFF),
        ];

        if ($actor->can('updateRole')) {
            $options = [
                RbacCatalog::ROLE_ADMIN => RbacCatalog::roleArabicLabel(RbacCatalog::ROLE_ADMIN),
                ...$options,
            ];
        }

        return $options;
    }
}
