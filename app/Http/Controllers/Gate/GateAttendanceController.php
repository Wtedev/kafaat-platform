<?php

namespace App\Http\Controllers\Gate;

use App\Enums\ProgramPrepDayType;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureGateAttendanceAccess;
use App\Models\ProgramAttendanceChecker;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\AttendanceLiveSessionService;
use App\Services\ProgramAttendanceCheckerAccessService;
use App\Services\ProgramAttendanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GateAttendanceController extends Controller
{
    public function login(Request $request, TrainingProgram $program): View|RedirectResponse
    {
        $this->assertGateAvailable($program);

        if ($this->alreadyAuthorized($request, $program)) {
            return redirect()->route('gate.portal', ['program' => $program->slug]);
        }

        return view('gate.login', [
            'program' => $program,
        ]);
    }

    /**
     * Token exchange: verify hash → secure session → redirect to clean URL.
     */
    public function access(
        Request $request,
        TrainingProgram $program,
        string $token,
        ProgramAttendanceCheckerAccessService $accessService,
    ): RedirectResponse {
        $this->assertGateAvailable($program);

        $checker = $accessService->findByPlainToken($program, $token);

        if ($checker === null) {
            return redirect()
                ->route('gate.login', ['program' => $program->slug])
                ->withErrors(['token' => 'رابط التحضير غير صالح أو منتهي. اطلب رابطاً جديداً من الإدارة.']);
        }

        $request->session()->regenerate();
        $request->session()->put(EnsureGateAttendanceAccess::SESSION_CHECKER_ID, $checker->id);
        $request->session()->put(EnsureGateAttendanceAccess::SESSION_PROGRAM_ID, $program->id);
        $request->session()->put(
            EnsureGateAttendanceAccess::SESSION_ACCESS_VERSION,
            (int) $checker->access_version,
        );

        $accessService->touchLastUsed($checker);

        return redirect()->route('gate.portal', ['program' => $program->slug]);
    }

    public function portal(
        Request $request,
        TrainingProgram $program,
        ProgramAttendanceService $attendanceService,
        AttendanceLiveSessionService $liveSessionService,
    ): View|Response {
        $this->assertGateAvailable($program);

        $today = Carbon::today(config('app.timezone'))->toDateString();
        $prepDay = $attendanceService->todayPrepDay($program);
        $isInPersonToday = $attendanceService->isTodayInPersonPrepDay($program);
        $isRemoteToday = $attendanceService->isTodayRemotePrepDay($program);
        $isPrepDayToday = $prepDay !== null;
        $defaultTab = $isInPersonToday ? 'qr' : ($isRemoteToday ? 'session' : 'manual');
        $tab = $request->query('tab', $defaultTab);
        if (! in_array($tab, ['qr', 'manual', 'session'], true)) {
            $tab = $defaultTab;
        }
        if ($tab === 'qr' && ! $isInPersonToday) {
            $tab = $isRemoteToday ? 'session' : 'manual';
        }
        if ($tab === 'session' && ! $isRemoteToday) {
            $tab = $isInPersonToday ? 'qr' : 'manual';
        }

        $search = trim((string) $request->query('q', ''));
        $registrations = null;

        if ($tab === 'manual') {
            $registrations = $this->eligibleRegistrationsQuery($program, $search, $today)
                ->paginate(20)
                ->onEachSide(1)
                ->withQueryString();
        }

        $dayTypeLabel = match (true) {
            $prepDay?->delivery_type === ProgramPrepDayType::InPerson => 'حضوري',
            $prepDay?->delivery_type === ProgramPrepDayType::Remote => 'عن بُعد',
            default => null,
        };

        $viewData = [
            'program' => $program,
            'prepDate' => $today,
            'prepDateLabel' => $prepDay?->displayLabel() ?? $today,
            'isInPersonToday' => $isInPersonToday,
            'isRemoteToday' => $isRemoteToday,
            'isPrepDayToday' => $isPrepDayToday,
            'prepDay' => $prepDay,
            'dayTypeLabel' => $dayTypeLabel,
            'operatorName' => (string) $request->attributes->get('gate_operator_name', 'مسؤول التحضير'),
            'operatorType' => (string) $request->attributes->get('gate_operator_type', 'checker'),
            'tab' => $tab,
            'search' => $search,
            'registrations' => $registrations,
            'liveSession' => $tab === 'session' && $isRemoteToday
                ? $liveSessionService->gateStatusPayload($program)
                : null,
            'liveSessionMinutes' => AttendanceLiveSessionService::SESSION_MINUTES,
        ];

        if ($tab === 'manual' && $request->boolean('partial')) {
            return response()
                ->view('gate.partials.manual-list', $viewData)
                ->header('Cache-Control', 'no-store');
        }

        return view('gate.portal', $viewData);
    }

    public function liveSessionStatus(
        Request $request,
        TrainingProgram $program,
        AttendanceLiveSessionService $liveSessionService,
    ): JsonResponse {
        $this->assertGateAvailable($program);

        return response()->json($liveSessionService->gateStatusPayload($program));
    }

    public function startLiveSession(
        Request $request,
        TrainingProgram $program,
        AttendanceLiveSessionService $liveSessionService,
    ): JsonResponse {
        $this->assertGateAvailable($program);

        $opener = $this->resolveLiveSessionOpener($request);

        try {
            $before = $liveSessionService->activeSessionFor(
                $program,
                Carbon::today(config('app.timezone'))->toDateString(),
            );
            $session = $liveSessionService->startProgramRemoteSession($program, $opener);
            $reused = $before !== null && $before->isActive() && $before->id === $session->id;
        } catch (ValidationException $exception) {
            return response()->json([
                'ok' => false,
                'message' => collect($exception->errors())->flatten()->first() ?? 'تعذّر فتح الجلسة.',
                'status' => $liveSessionService->gateStatusPayload($program),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'reused' => $reused,
            'message' => $reused
                ? 'جلسة التحضير مفتوحة بالفعل.'
                : 'تم فتح جلسة التحضير.',
            'status' => $liveSessionService->gateStatusPayload($program),
        ]);
    }

    public function endLiveSession(
        Request $request,
        TrainingProgram $program,
        AttendanceLiveSessionService $liveSessionService,
    ): JsonResponse {
        $this->assertGateAvailable($program);

        $session = $liveSessionService->activeSessionFor(
            $program,
            Carbon::today(config('app.timezone'))->toDateString(),
        );

        if ($session === null) {
            return response()->json([
                'ok' => true,
                'message' => 'لا توجد جلسة مفتوحة حالياً.',
                'status' => $liveSessionService->gateStatusPayload($program),
            ]);
        }

        $liveSessionService->endSession($session);

        return response()->json([
            'ok' => true,
            'message' => 'تم إنهاء جلسة التحضير.',
            'status' => $liveSessionService->gateStatusPayload($program),
        ]);
    }

    /** @deprecated Prefer portal; kept as alias for bookmarks/admin links. */
    public function scan(
        Request $request,
        TrainingProgram $program,
    ): RedirectResponse {
        return redirect()->route('gate.portal', [
            'program' => $program->slug,
            'tab' => 'qr',
        ]);
    }

    public function mark(
        Request $request,
        TrainingProgram $program,
        ProgramAttendanceService $attendanceService,
    ): JsonResponse|RedirectResponse {
        $this->assertGateAvailable($program);

        $data = $request->validate([
            'pass' => ['required', 'string', 'max:500'],
        ], [
            'pass.required' => 'امسح رمز المرور.',
        ]);

        /** @var ProgramAttendanceChecker|null $checker */
        $checker = $request->attributes->get('gate_checker');
        $admin = $request->attributes->get('gate_operator_type') === 'admin'
            ? $request->user()
            : null;

        $result = $attendanceService->markPresentFromPass(
            $program,
            $data['pass'],
            $checker instanceof ProgramAttendanceChecker ? $checker : null,
            $admin instanceof User ? $admin : null,
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => $result['ok'],
                'reason' => $result['reason'],
                'message' => $result['message'],
                'beneficiary_name' => $result['beneficiary_name'],
            ], $result['ok'] ? 200 : 422);
        }

        if ($result['ok']) {
            return back()->with('gate_success', $result['message'])
                ->with('gate_beneficiary', $result['beneficiary_name'])
                ->with('gate_reason', $result['reason']);
        }

        return back()->with('gate_error', $result['message']);
    }

    public function toggleAttendance(
        Request $request,
        TrainingProgram $program,
        ProgramRegistration $registration,
        ProgramAttendanceService $attendanceService,
    ): JsonResponse {
        $this->assertGateAvailable($program);

        if ((int) $registration->training_program_id !== (int) $program->id) {
            abort(404);
        }

        $data = $request->validate([
            'present' => ['required', 'boolean'],
            'date' => ['sometimes', 'nullable', 'date'], // ignored — forged-date protection
        ]);

        /** @var ProgramAttendanceChecker|null $checker */
        $checker = $request->attributes->get('gate_checker');
        $today = Carbon::today(config('app.timezone'))->toDateString();

        if ($request->attributes->get('gate_operator_type') === 'admin') {
            $admin = $request->user();
            if (! $admin instanceof User) {
                abort(403);
            }

            if (! $attendanceService->isValidAttendancePrepDate($program, $today)) {
                return response()->json([
                    'ok' => false,
                    'reason' => 'invalid_day',
                    'message' => 'اليوم ليس من أيام البرنامج، ولا يتوفر تحضير اليوم.',
                    'present' => false,
                    'beneficiary_name' => null,
                ], 422);
            }

            $registration->loadMissing('user');
            $beneficiaryName = $registration->user?->fullName()
                ?: ($registration->user?->name ?? 'مستفيد');

            $attendanceService->setPresentState(
                $registration,
                $today,
                (bool) $data['present'],
                $admin,
            );

            return response()->json([
                'ok' => true,
                'reason' => $data['present'] ? 'marked' : 'cleared',
                'message' => $data['present'] ? 'تم تسجيل الحضور.' : 'تم إلغاء الحضور.',
                'present' => (bool) $data['present'],
                'beneficiary_name' => $beneficiaryName,
            ]);
        }

        if (! $checker instanceof ProgramAttendanceChecker) {
            abort(403);
        }

        $result = $attendanceService->setPresentStateByChecker(
            $program,
            $registration,
            (bool) $data['present'],
            $checker,
            $data['date'] ?? null,
        );

        return response()->json([
            'ok' => $result['ok'],
            'reason' => $result['reason'],
            'message' => $result['message'],
            'present' => $result['present'],
            'beneficiary_name' => $result['beneficiary_name'],
        ], $result['ok'] ? 200 : 422);
    }

    public function logout(Request $request, TrainingProgram $program): RedirectResponse
    {
        $request->session()->forget([
            EnsureGateAttendanceAccess::SESSION_CHECKER_ID,
            EnsureGateAttendanceAccess::SESSION_PROGRAM_ID,
            EnsureGateAttendanceAccess::SESSION_ACCESS_VERSION,
        ]);

        return redirect()->route('gate.login', ['program' => $program->slug])
            ->with('success', 'تم تسجيل الخروج من بوابة التحضير.');
    }

    private function resolveLiveSessionOpener(Request $request): User|ProgramAttendanceChecker
    {
        if ($request->attributes->get('gate_operator_type') === 'admin') {
            $admin = $request->user();
            if (! $admin instanceof User) {
                abort(403);
            }

            return $admin;
        }

        $checker = $request->attributes->get('gate_checker');
        if (! $checker instanceof ProgramAttendanceChecker) {
            abort(403);
        }

        return $checker;
    }

    private function assertGateAvailable(TrainingProgram $program): void
    {
        if (EnsureGateAttendanceAccess::gateAvailable($program)) {
            return;
        }

        abort(404);
    }

    private function alreadyAuthorized(Request $request, TrainingProgram $program): bool
    {
        $user = $request->user();
        if (
            $user !== null
            && $user->allowsOperationalAccess()
            && $user->is_active
            && $user->can('viewOperational', $program)
        ) {
            return true;
        }

        $checkerId = $request->session()->get(EnsureGateAttendanceAccess::SESSION_CHECKER_ID);
        $programId = $request->session()->get(EnsureGateAttendanceAccess::SESSION_PROGRAM_ID);
        $accessVersion = $request->session()->get(EnsureGateAttendanceAccess::SESSION_ACCESS_VERSION);

        if (! $checkerId || (int) $programId !== (int) $program->id) {
            return false;
        }

        $checker = ProgramAttendanceChecker::query()
            ->whereKey($checkerId)
            ->where('training_program_id', $program->id)
            ->where('is_active', true)
            ->whereNotNull('access_token_hash')
            ->first();

        return $checker !== null
            && (int) $accessVersion === (int) $checker->access_version;
    }

    /**
     * @return Builder<ProgramRegistration>
     */
    private function eligibleRegistrationsQuery(TrainingProgram $program, string $search, string $today)
    {
        $query = ProgramRegistration::query()
            ->with([
                'user',
                'attendanceRecords' => fn ($q) => $q->whereDate('training_date', $today),
            ])
            ->where('training_program_id', $program->id)
            ->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->orderBy('id');

        if ($search !== '') {
            $like = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';
            $query->whereHas('user', function ($userQuery) use ($like): void {
                $userQuery->where(function ($inner) use ($like): void {
                    foreach (['name', 'first_name', 'father_name', 'grandfather_name', 'family_name'] as $column) {
                        $inner->orWhereRaw('LOWER(COALESCE('.$column.", '')) LIKE ?", [$like]);
                    }
                });
            });
        }

        return $query;
    }
}
