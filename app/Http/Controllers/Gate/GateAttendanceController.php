<?php

namespace App\Http\Controllers\Gate;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureGateAttendanceAccess;
use App\Models\ProgramAttendanceChecker;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramAttendanceCheckerInviteService;
use App\Services\ProgramAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class GateAttendanceController extends Controller
{
    public const SESSION_PREP_DATE = 'gate_prep_date';

    public function login(Request $request, TrainingProgram $program): View|RedirectResponse
    {
        $this->assertInPersonProgram($program);

        if ($this->alreadyAuthorized($request, $program)) {
            return redirect()->route('gate.scan', array_filter([
                'program' => $program->slug,
                'date' => $request->query('date'),
            ]));
        }

        return view('gate.login', [
            'program' => $program,
        ]);
    }

    public function authenticate(
        Request $request,
        TrainingProgram $program,
        ProgramAttendanceCheckerInviteService $inviteService,
    ): RedirectResponse {
        $this->assertInPersonProgram($program);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
        ], [
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'code.required' => 'رمز التحقق مطلوب.',
            'code.size' => 'رمز التحقق مكوّن من 6 أرقام.',
        ]);

        $result = $inviteService->verify($program, $data['email'], $data['code']);

        $errorMessages = [
            'not_found' => 'لا توجد دعوة تحضير لهذا البريد في هذا البرنامج.',
            'inactive' => 'عضوية التحضير معطّلة. راجعي الإدارة.',
            'expired' => 'انتهت صلاحية الرمز. اطلبي إرسال رمز جديد من الإدارة.',
            'too_many_attempts' => 'تجاوزتِ عدد المحاولات. اطلبي رمزاً جديداً من الإدارة.',
            'invalid' => 'رمز التحقق غير صحيح.',
        ];

        if ($result !== 'success') {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['code' => $errorMessages[$result] ?? 'تعذّر التحقق من الرمز.']);
        }

        $checker = $inviteService->findActiveChecker($program, $data['email']);

        if ($checker === null) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'تعذّر إكمال تسجيل الدخول.']);
        }

        $request->session()->put(EnsureGateAttendanceAccess::SESSION_CHECKER_ID, $checker->id);
        $request->session()->put(EnsureGateAttendanceAccess::SESSION_PROGRAM_ID, $program->id);

        return redirect()->route('gate.scan', ['program' => $program->slug]);
    }

    public function scan(
        Request $request,
        TrainingProgram $program,
        ProgramAttendanceService $attendanceService,
    ): View|RedirectResponse {
        $this->assertInPersonProgram($program);

        $options = $attendanceService->attendancePrepDateOptions($program);
        $resolved = $this->resolvePrepDate($request, $program, $attendanceService, $options);

        if ($resolved['needs_choice']) {
            return view('gate.choose-day', [
                'program' => $program,
                'options' => $options,
                'operatorName' => (string) $request->attributes->get('gate_operator_name', 'مشغّلة البوابة'),
                'operatorType' => (string) $request->attributes->get('gate_operator_type', 'checker'),
                'suggested' => $resolved['suggested'],
            ]);
        }

        if ($resolved['date'] === null) {
            return view('gate.choose-day', [
                'program' => $program,
                'options' => $options,
                'operatorName' => (string) $request->attributes->get('gate_operator_name', 'مشغّلة البوابة'),
                'operatorType' => (string) $request->attributes->get('gate_operator_type', 'checker'),
                'suggested' => null,
                'emptyMessage' => 'لا توجد أيام تحضير مفعّلة لهذا البرنامج. أضيفي أياماً من لوحة الإدارة أولاً.',
            ]);
        }

        $request->session()->put(self::SESSION_PREP_DATE, $resolved['date']);

        return view('gate.scan', [
            'program' => $program,
            'prepDate' => $resolved['date'],
            'prepDateLabel' => $options[$resolved['date']] ?? $resolved['date'],
            'operatorName' => (string) $request->attributes->get('gate_operator_name', 'مشغّلة البوابة'),
            'operatorType' => (string) $request->attributes->get('gate_operator_type', 'checker'),
        ]);
    }

    public function selectDay(
        Request $request,
        TrainingProgram $program,
        ProgramAttendanceService $attendanceService,
    ): RedirectResponse {
        $this->assertInPersonProgram($program);

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ], [
            'date.required' => 'اختاري يوم التحضير.',
        ]);

        if (! $attendanceService->isValidAttendancePrepDate($program, $data['date'])) {
            return back()->withErrors(['date' => 'يوم التحضير غير صالح لهذا البرنامج.']);
        }

        $request->session()->put(self::SESSION_PREP_DATE, $data['date']);

        return redirect()->route('gate.scan', [
            'program' => $program->slug,
            'date' => $data['date'],
        ]);
    }

    public function mark(
        Request $request,
        TrainingProgram $program,
        ProgramAttendanceService $attendanceService,
    ): JsonResponse|RedirectResponse {
        $this->assertInPersonProgram($program);

        $data = $request->validate([
            'pass' => ['required', 'string', 'max:500'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'pass.required' => 'أدخلي أو امسحي رمز المرور.',
        ]);

        $prepDate = $data['date']
            ?? $request->session()->get(self::SESSION_PREP_DATE)
            ?? Carbon::today(config('app.timezone'))->toDateString();

        if (! $attendanceService->isValidAttendancePrepDate($program, $prepDate)) {
            $message = 'يوم التحضير غير محدّد أو غير صالح. اختاري يوماً أولاً.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => false,
                    'reason' => 'invalid_day',
                    'message' => $message,
                    'beneficiary_name' => null,
                ], 422);
            }

            return redirect()->route('gate.scan', ['program' => $program->slug])
                ->with('gate_error', $message);
        }

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
            $prepDate,
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

    public function logout(Request $request, TrainingProgram $program): RedirectResponse
    {
        $request->session()->forget([
            EnsureGateAttendanceAccess::SESSION_CHECKER_ID,
            EnsureGateAttendanceAccess::SESSION_PROGRAM_ID,
            self::SESSION_PREP_DATE,
        ]);

        return redirect()->route('gate.login', ['program' => $program->slug])
            ->with('success', 'تم تسجيل الخروج من بوابة التحضير.');
    }

    /**
     * @param  array<string, string>  $options
     * @return array{date: ?string, needs_choice: bool, suggested: ?string}
     */
    private function resolvePrepDate(
        Request $request,
        TrainingProgram $program,
        ProgramAttendanceService $attendanceService,
        array $options,
    ): array {
        if ($options === []) {
            return ['date' => null, 'needs_choice' => false, 'suggested' => null];
        }

        $queryDate = $request->query('date');
        if (is_string($queryDate) && $attendanceService->isValidAttendancePrepDate($program, $queryDate)) {
            return ['date' => $queryDate, 'needs_choice' => false, 'suggested' => $queryDate];
        }

        if ($request->boolean('change')) {
            $request->session()->forget(self::SESSION_PREP_DATE);
            $today = Carbon::today(config('app.timezone'))->toDateString();
            $suggested = array_key_exists($today, $options)
                ? $today
                : $attendanceService->defaultPrepDate($program);

            return ['date' => null, 'needs_choice' => true, 'suggested' => $suggested];
        }

        $sessionDate = $request->session()->get(self::SESSION_PREP_DATE);
        if (is_string($sessionDate) && $attendanceService->isValidAttendancePrepDate($program, $sessionDate)) {
            // Still require explicit choice when multiple days and no query date,
            // unless only one day exists.
            if (count($options) === 1) {
                return ['date' => $sessionDate, 'needs_choice' => false, 'suggested' => $sessionDate];
            }
        }

        if (count($options) === 1) {
            $only = array_key_first($options);

            return ['date' => $only, 'needs_choice' => false, 'suggested' => $only];
        }

        $today = Carbon::today(config('app.timezone'))->toDateString();
        $suggested = array_key_exists($today, $options)
            ? $today
            : $attendanceService->defaultPrepDate($program);

        // Multiple valid prep days → require explicit choice before scanner.
        return ['date' => null, 'needs_choice' => true, 'suggested' => $suggested];
    }

    private function assertInPersonProgram(TrainingProgram $program): void
    {
        if ($program->delivery_mode?->hasPhysicalComponent() !== true) {
            abort(404);
        }
    }

    private function alreadyAuthorized(Request $request, TrainingProgram $program): bool
    {
        $user = $request->user();
        if ($user !== null && $user->can('viewOperational', $program)) {
            return true;
        }

        $checkerId = $request->session()->get(EnsureGateAttendanceAccess::SESSION_CHECKER_ID);
        $programId = $request->session()->get(EnsureGateAttendanceAccess::SESSION_PROGRAM_ID);

        if (! $checkerId || (int) $programId !== (int) $program->id) {
            return false;
        }

        return ProgramAttendanceChecker::query()
            ->whereKey($checkerId)
            ->where('training_program_id', $program->id)
            ->where('is_active', true)
            ->whereNotNull('verified_at')
            ->exists();
    }
}
