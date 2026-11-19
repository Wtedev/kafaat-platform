<?php

namespace App\Http\Controllers\StaffUi;

use App\Enums\ProgramPrepDayType;
use App\Http\Controllers\Controller;
use App\Models\ProgramAttendanceChecker;
use App\Models\ProgramAttendanceLink;
use App\Models\ProgramPrepDay;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Attendance\ProgramAttendanceLinkService;
use App\Services\ProgramAttendanceCheckerAccessService;
use App\Services\ProgramAttendanceService;
use App\Services\StaffUi\StaffProgramAttendance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Actions of the attendance tab. The tab itself is rendered by {@see StaffProgramPageController}.
 */
class StaffProgramAttendanceController extends Controller
{
    public function __construct(
        private readonly StaffProgramAttendance $attendance,
        private readonly ProgramAttendanceService $records,
        private readonly ProgramAttendanceLinkService $links,
        private readonly ProgramAttendanceCheckerAccessService $checkers,
    ) {}

    public function storeDay(Request $request, TrainingProgram $program): RedirectResponse
    {
        $this->authorizeManage($request, $program);
        $data = $request->validate([
            'prep_date' => ['required', 'date'],
            'delivery_type' => ['required', Rule::enum(ProgramPrepDayType::class)],
        ]);

        $day = $this->attendance->addDay($program, $data['prep_date'], ProgramPrepDayType::from($data['delivery_type']));

        return $this->back($program)->with('status', 'أُضيف يوم '.$day->displayLabel().'.');
    }

    public function generateDays(Request $request, TrainingProgram $program): RedirectResponse
    {
        $this->authorizeManage($request, $program);
        $data = $request->validate(['delivery_type' => ['required', Rule::enum(ProgramPrepDayType::class)]]);

        $created = $this->attendance->generateFromSchedule($program, ProgramPrepDayType::from($data['delivery_type']));

        return $this->back($program)->with('status', $created > 0
            ? 'أُضيف '.en_num($created).' يوماً من جدول البرنامج.'
            : 'كل أيام الجدول مضافة من قبل.');
    }

    public function updateDay(Request $request, TrainingProgram $program, ProgramPrepDay $day): RedirectResponse
    {
        $this->authorizeManage($request, $program);
        $this->assertBelongs($program, $day->training_program_id);
        $data = $request->validate(['delivery_type' => ['required', Rule::enum(ProgramPrepDayType::class)]]);

        $this->attendance->changeType($day, ProgramPrepDayType::from($data['delivery_type']));

        return $this->back($program)->with('status', 'تم تغيير نوع يوم '.$day->displayLabel().'.');
    }

    public function destroyDay(Request $request, TrainingProgram $program, ProgramPrepDay $day): RedirectResponse
    {
        $this->authorizeManage($request, $program);
        $this->assertBelongs($program, $day->training_program_id);
        $label = $day->displayLabel();

        $this->attendance->removeDay($program, $day);

        return $this->back($program)->with('status', 'حُذف يوم '.$label.'.');
    }

    public function toggle(Request $request, TrainingProgram $program, ProgramRegistration $registration): RedirectResponse
    {
        $actor = $this->authorizeOperate($request, $program);
        $this->assertBelongs($program, $registration->training_program_id);
        $data = $request->validate([
            'date' => ['required', 'date'],
            'present' => ['required', 'boolean'],
        ]);

        $this->records->setPresentState($registration, $data['date'], (bool) $data['present'], $actor);

        return $this->roster($program, $data['date'], $request)->with('status', ((bool) $data['present'] ? 'حُضّر ' : 'أُلغي تحضير ').$registration->user?->name.'.');
    }

    public function bulkPresent(Request $request, TrainingProgram $program): RedirectResponse
    {
        $actor = $this->authorizeOperate($request, $program);
        $data = $request->validate([
            'date' => ['required', 'date'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $count = $this->records->bulkMarkPresent($program, $data['ids'], $data['date'], $actor);

        return $this->roster($program, $data['date'], $request)->with('status', 'حُضّر '.en_num($count).'.');
    }

    public function storeLink(Request $request, TrainingProgram $program): RedirectResponse
    {
        $this->authorizeOperate($request, $program);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'open_minutes' => ['required', 'integer', 'min:1', 'max:180'],
        ]);

        $link = $this->links->create($program, $data['name'], (int) $data['open_minutes']);
        if ($request->boolean('open_now')) {
            $this->links->open($link);
        }

        return $this->back($program, '#links')->with('status', $request->boolean('open_now')
            ? 'أُنشئ الرابط وفُتح التحضير '.en_num($link->openMinutes()).' دقيقة.'
            : 'أُنشئ الرابط. افتحه وقت المحاضرة.');
    }

    public function linkAction(Request $request, TrainingProgram $program, ProgramAttendanceLink $link): RedirectResponse
    {
        $this->authorizeOperate($request, $program);
        $this->assertBelongs($program, $link->training_program_id);

        $message = match ((string) $request->input('action')) {
            'open' => tap('فُتح التحضير '.en_num($link->openMinutes()).' دقيقة.', fn () => $this->links->open($link)),
            'close' => tap('أُغلق التحضير.', fn () => $this->links->close($link)),
            'cancel' => tap('أُلغي الرابط.', fn () => $this->links->cancel($link)),
            default => abort(422),
        };

        return $this->back($program, '#links')->with('status', $message);
    }

    public function storeChecker(Request $request, TrainingProgram $program): RedirectResponse
    {
        $actor = $this->authorizeManage($request, $program);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);

        $issued = $this->checkers->create($program, $data['name'], $actor);

        return $this->back($program, '#checkers')
            ->with('status', 'أُضيف '.$issued['checker']->name.'. انسخي رابط الدخول وأرسليه له.')
            ->with('checker_link', ['name' => $issued['checker']->name, 'url' => $issued['url']]);
    }

    public function checkerAction(Request $request, TrainingProgram $program, ProgramAttendanceChecker $checker): RedirectResponse
    {
        $actor = $this->authorizeManage($request, $program);
        $this->assertBelongs($program, $checker->training_program_id);

        return match ((string) $request->input('action')) {
            'regenerate' => (function () use ($program, $checker, $actor): RedirectResponse {
                $issued = $this->checkers->regenerateLink($checker, $actor);

                return $this->back($program, '#checkers')
                    ->with('status', 'أُنشئ رابط جديد لـ'.$checker->name.'، والرابط القديم لم يعد يعمل.')
                    ->with('checker_link', ['name' => $checker->name, 'url' => $issued['url']]);
            })(),
            'deactivate' => tap($this->back($program, '#checkers')->with('status', 'أُوقف '.$checker->name.'.'), fn () => $this->checkers->setActive($checker, false, $actor)),
            'activate' => tap($this->back($program, '#checkers')->with('status', 'فُعّل '.$checker->name.'.'), fn () => $this->checkers->setActive($checker, true, $actor)),
            default => abort(422),
        };
    }

    private function authorizeManage(Request $request, TrainingProgram $program): User
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('update', $program), 403);

        return $actor;
    }

    private function authorizeOperate(Request $request, TrainingProgram $program): User
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('viewOperational', $program), 403);

        return $actor;
    }

    private function assertBelongs(TrainingProgram $program, mixed $programId): void
    {
        abort_unless((int) $programId === (int) $program->getKey(), 404);
    }

    private function back(TrainingProgram $program, string $anchor = ''): RedirectResponse
    {
        return redirect()->to(route('staff-ui.programs.tab', [$program, 'attendance']).$anchor);
    }

    private function roster(TrainingProgram $program, string $date, Request $request): RedirectResponse
    {
        $query = array_filter(['day' => $date, 'q' => trim((string) $request->input('q', ''))], fn ($v) => $v !== '');

        return redirect()->to(route('staff-ui.programs.tab', [$program, 'attendance']).'?'.http_build_query($query).'#roster');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->canAccessFilamentAdmin(), 403);

        return $actor;
    }
}
