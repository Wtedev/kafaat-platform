<?php

namespace App\Http\Controllers\Gate;

use App\Enums\ProgramDeliveryMode;
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
use Illuminate\View\View;

class GateAttendanceController extends Controller
{
    public function login(Request $request, TrainingProgram $program): View|RedirectResponse
    {
        $this->assertInPersonProgram($program);

        if ($this->alreadyAuthorized($request, $program)) {
            return redirect()->route('gate.scan', ['program' => $program->slug]);
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

    public function scan(Request $request, TrainingProgram $program): View
    {
        $this->assertInPersonProgram($program);

        return view('gate.scan', [
            'program' => $program,
            'operatorName' => (string) $request->attributes->get('gate_operator_name', 'مشغّلة البوابة'),
            'operatorType' => (string) $request->attributes->get('gate_operator_type', 'checker'),
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
        ], [
            'pass.required' => 'أدخلي أو امسحي رمز المرور.',
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

    public function logout(Request $request, TrainingProgram $program): RedirectResponse
    {
        $request->session()->forget([
            EnsureGateAttendanceAccess::SESSION_CHECKER_ID,
            EnsureGateAttendanceAccess::SESSION_PROGRAM_ID,
        ]);

        return redirect()->route('gate.login', ['program' => $program->slug])
            ->with('success', 'تم تسجيل الخروج من بوابة التحضير.');
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
