<?php

namespace App\Http\Controllers\StaffUi;

use App\Enums\AccountStatus;
use App\Enums\AuditLogResult;
use App\Enums\ProfileGender;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Identity\IdentityNumberService;
use App\Services\Identity\PersonNameService;
use App\Services\Privacy\AccountDeactivationService;
use App\Services\Rbac\RbacCatalog;
use App\Services\StaffUi\StaffBeneficiaryIndex;
use App\Services\StaffUi\StaffDirectoryIndex;
use App\Support\Auth\EmailNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffBeneficiaryController extends Controller
{
    public function index(Request $request, StaffBeneficiaryIndex $index, StaffDirectoryIndex $staff): View
    {
        $this->ensureStaff($request);
        $this->authorize('viewAny', User::class);

        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $status = (string) $request->query('status', '');
        $completeness = (string) $request->query('profile', '');

        if (! in_array($status, ['', 'active', 'inactive'], true)) {
            $status = '';
        }

        if (! in_array($completeness, ['', 'complete', 'incomplete'], true)) {
            $completeness = '';
        }

        $user = $request->user();

        return view('staff-ui.users.beneficiaries', [
            'staffName' => $user->name,
            'staffEmail' => $user->email,
            'beneficiaries' => $index->paginate($search, $status, $completeness, (int) $request->query('page', 1)),
            'search' => $search,
            'status' => $status,
            'completeness' => $completeness,
            'canViewContact' => $user->can('beneficiaries.view_contact'),
            'canViewStaff' => $user->can('users.view'),
            'staffCount' => $user->can('users.view') ? $staff->count() : null,
        ]);
    }

    public function show(Request $request, User $user): View
    {
        $this->ensureStaff($request);
        $this->authorize('view', $user);
        $this->assertBeneficiary($user);

        $actor = $request->user();
        $user->load([
            'profile.currentCvDocument',
            'programRegistrations.trainingProgram',
            'volunteerRegistrations.opportunity',
            'entityNotes.creator',
        ]);

        return view('staff-ui.users.beneficiary', [
            'staffName' => $actor->name,
            'staffEmail' => $actor->email,
            'beneficiary' => $user,
            'certificates' => Certificate::query()
                ->where('user_id', $user->id)
                ->with('certificateable')
                ->orderByDesc('issued_at')
                ->get(),
            'canViewContact' => $actor->can('viewContact', $user),
            'canUpdate' => $actor->can('update', $user),
            'canUpdateSensitive' => $actor->can('updateSensitive', $user),
            'canDeactivate' => $actor->can('deactivate', $user),
            'canDownloadCv' => $actor->can('downloadCv', $user),
            'canViewMaskedIdentity' => $actor->can('viewMaskedIdentity', $user),
            'canRevealIdentity' => $actor->can('viewFullIdentity', $user),
            'maskedIdentity' => IdentityNumberService::mask($user->identity_number_last4),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureStaff($request);
        $this->authorize('view', $user);
        $this->assertBeneficiary($user);

        $actor = $request->user();
        $canBasic = $actor->can('update', $user);
        $canSensitive = $actor->can('updateSensitive', $user);
        abort_unless($canBasic || $canSensitive, 403);

        $field = (string) $request->input('field', '');

        DB::transaction(function () use ($request, $user, $canBasic, $canSensitive, $field): void {
            if ($field !== '') {
                if ($field === 'email') {
                    abort_unless($canSensitive, 403);
                    $this->saveEmail($request, $user);

                    return;
                }

                abort_unless($canBasic, 403);
                $this->saveBasicField($request, $user, $field);

                return;
            }

            if ($canBasic && $request->boolean('basic')) {
                $this->saveBasicProfile($request, $user);
            }

            if ($canSensitive && $request->exists('email')) {
                $this->saveEmail($request, $user);
            }
        });

        return redirect()
            ->route('staff-ui.users.show', $user)
            ->with('status', 'تم حفظ البيانات.');
    }

    public function activation(Request $request, User $user, AccountDeactivationService $deactivation, AuditLogger $auditLogger): RedirectResponse
    {
        $this->ensureStaff($request);
        $this->authorize('view', $user);
        $this->assertBeneficiary($user);
        $this->authorize('deactivate', $user);
        abort_if($user->isAnonymized(), 403);

        if ($request->input('action') === 'deactivate') {
            $deactivation->deactivate($user, $request->user(), request: $request);
            $message = 'تم تعطيل الحساب.';
        } elseif ($request->input('action') === 'activate') {
            if (! $user->is_active) {
                $user->update(['is_active' => true]);
                $auditLogger->recordOrFail(
                    $request->user(),
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
            ->route('staff-ui.users.show', $user)
            ->with('status', $message);
    }

    public function storeNote(Request $request, User $user): RedirectResponse
    {
        $this->ensureStaff($request);
        $this->authorize('view', $user);
        $this->assertBeneficiary($user);
        $this->authorize('update', $user);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ], [
            'body.required' => 'نص الملاحظة مطلوب.',
            'body.max' => 'الملاحظة طويلة جداً.',
        ]);

        $user->entityNotes()->create([
            'body' => trim($data['body']),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('staff-ui.users.show', $user)
            ->with('status', 'تمت إضافة الملاحظة.');
    }

    private function ensureStaff(Request $request): void
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->canAccessFilamentAdmin(), 403);
    }

    private function assertBeneficiary(User $user): void
    {
        abort_unless($user->isPortalUser(), 404);
        abort_if(
            in_array($user->role_type, ['admin', 'staff'], true)
            || $user->hasAnyRole([RbacCatalog::ROLE_ADMIN, RbacCatalog::ROLE_STAFF]),
            404,
        );
        abort_if(in_array($user->account_status, [
            AccountStatus::Inactive,
            AccountStatus::Anonymized,
            AccountStatus::DeletionProcessing,
        ], true), 404);
    }

    private function saveBasicField(Request $request, User $user, string $field): void
    {
        match ($field) {
            'name' => $this->saveName($request, $user),
            'phone' => $this->savePhone($request, $user),
            'notify_email' => $user->fill([
                'notify_email' => $request->boolean('notify_email'),
            ])->save(),
            'gender', 'birth_date', 'city', 'job_title', 'bio' => $this->saveProfileField($request, $user, $field),
            default => abort(422),
        };
    }

    private function saveName(Request $request, User $user): void
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'father_name' => ['required', 'string', 'max:100'],
            'grandfather_name' => ['required', 'string', 'max:100'],
            'family_name' => ['required', 'string', 'max:100'],
        ], [
            'first_name.required' => 'الاسم مطلوب.',
            'father_name.required' => 'اسم الأب مطلوب.',
            'grandfather_name.required' => 'اسم الجد مطلوب.',
            'family_name.required' => 'اسم العائلة مطلوب.',
        ]);

        try {
            $parts = PersonNameService::normalizedParts($data);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages([
                'first_name' => 'أدخل الاسم الرباعي بأحرف عربية أو إنجليزية.',
            ]);
        }

        $user->fill([
            ...$parts,
            'name' => PersonNameService::buildFullName($parts),
        ])->save();
    }

    private function savePhone(Request $request, User $user): void
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user->fill([
            'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
        ])->save();
    }

    private function saveProfileField(Request $request, User $user, string $field): void
    {
        $rules = [
            'gender' => ['nullable', Rule::enum(ProfileGender::class)],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'city' => ['nullable', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'bio' => ['nullable', 'string', 'max:5000'],
        ];

        $data = $request->validate([
            $field => $rules[$field],
        ]);

        $value = $data[$field] ?? null;
        if (is_string($value)) {
            $value = trim($value);
            $value = $value === '' ? null : $value;
        }

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [$field => $value],
        );
    }

    private function saveBasicProfile(Request $request, User $user): void
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'father_name' => ['required', 'string', 'max:100'],
            'grandfather_name' => ['required', 'string', 'max:100'],
            'family_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', Rule::enum(ProfileGender::class)],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'city' => ['nullable', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'notify_email' => ['nullable', 'boolean'],
        ], [
            'first_name.required' => 'الاسم مطلوب.',
            'father_name.required' => 'اسم الأب مطلوب.',
            'grandfather_name.required' => 'اسم الجد مطلوب.',
            'family_name.required' => 'اسم العائلة مطلوب.',
        ]);

        try {
            $parts = PersonNameService::normalizedParts([
                'first_name' => $data['first_name'],
                'father_name' => $data['father_name'],
                'grandfather_name' => $data['grandfather_name'],
                'family_name' => $data['family_name'],
            ]);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages([
                'first_name' => 'أدخل الاسم الرباعي بأحرف عربية أو إنجليزية.',
            ]);
        }

        $user->fill([
            ...$parts,
            'name' => PersonNameService::buildFullName($parts),
            'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
            'notify_email' => $request->boolean('notify_email'),
        ])->save();

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'gender' => filled($data['gender'] ?? null) ? $data['gender'] : null,
                'birth_date' => $data['birth_date'] ?? null,
                'city' => filled($data['city'] ?? null) ? trim((string) $data['city']) : null,
                'job_title' => filled($data['job_title'] ?? null) ? trim((string) $data['job_title']) : null,
                'bio' => filled($data['bio'] ?? null) ? trim((string) $data['bio']) : null,
            ],
        );
    }

    private function saveEmail(Request $request, User $user): void
    {
        $email = EmailNormalizer::normalize((string) $request->input('email'));

        validator(
            ['email' => $email],
            ['email' => ['required', 'string', 'email', 'max:255']],
            [
                'email.required' => 'البريد الإلكتروني مطلوب.',
                'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            ],
        )->validate();

        $duplicate = User::query()
            ->whereEmailIgnoreCase($email)
            ->whereKeyNot($user->getKey())
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'email' => 'البريد الإلكتروني مستخدم بالفعل.',
            ]);
        }

        $changed = strcasecmp((string) $user->email, $email) !== 0;
        $user->email = $email;
        if ($changed) {
            $user->email_verified_at = null;
        }
        $user->save();
    }
}
