<?php

namespace App\Http\Controllers\StaffUi;

use App\Enums\AttendanceLinkMode;
use App\Http\Controllers\Controller;
use App\Models\ProgramAttendanceLink;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Attendance\ProgramAttendanceLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Attendance days of a program. Each day is a link: a QR scan link for the prep
 * officer (in person) or an identity link for trainees (remote), open for a set
 * number of minutes.
 */
class StaffProgramAttendanceController extends Controller
{
    public function __construct(
        private readonly ProgramAttendanceLinkService $links,
    ) {}

    public function store(Request $request, TrainingProgram $program): RedirectResponse
    {
        $this->authorizeOperate($request, $program);
        $data = $request->validate([
            'mode' => ['required', Rule::enum(AttendanceLinkMode::class)],
            'name' => ['required', 'string', 'max:120'],
            'open_minutes' => ['required', 'integer', 'min:1', 'max:720'],
        ]);

        $link = $this->links->create($program, $data['name'], (int) $data['open_minutes'], AttendanceLinkMode::from($data['mode']));
        if ($request->boolean('open_now')) {
            $this->links->open($link);
        }

        return $this->back($program)->with('status', 'أُضيف «'.$link->name.'»'.($request->boolean('open_now')
            ? ' وفُتح التحضير '.en_num($link->openMinutes()).' دقيقة.'
            : '. افتحه وقت التحضير.'));
    }

    public function update(Request $request, TrainingProgram $program, ProgramAttendanceLink $link): RedirectResponse
    {
        $this->authorizeOperate($request, $program);
        $this->assertBelongs($program, $link->training_program_id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'open_minutes' => ['required', 'integer', 'min:1', 'max:720'],
        ]);

        $link->update(['name' => trim($data['name'])]);
        $this->links->updateMinutes($link, (int) $data['open_minutes']);

        return $this->back($program)->with('status', 'حُفظ «'.$link->name.'».');
    }

    public function action(Request $request, TrainingProgram $program, ProgramAttendanceLink $link): RedirectResponse
    {
        $this->authorizeOperate($request, $program);
        $this->assertBelongs($program, $link->training_program_id);

        $message = match ((string) $request->input('action')) {
            'open' => tap('فُتح التحضير '.en_num($link->openMinutes()).' دقيقة.', fn () => $this->links->open($link)),
            'close' => tap('أُغلق التحضير.', fn () => $this->links->close($link)),
            'cancel' => tap('حُذف «'.$link->name.'». يبقى الحضور المسجّل فيه محفوظاً.', fn () => $this->links->cancel($link)),
            default => abort(422),
        };

        return $this->back($program)->with('status', $message);
    }

    /**
     * Manual check-in for one trainee on one day (from the certificates table).
     */
    public function manual(Request $request, TrainingProgram $program, ProgramAttendanceLink $link, ProgramRegistration $registration): RedirectResponse
    {
        $this->authorizeOperate($request, $program);
        $this->assertBelongs($program, $link->training_program_id);
        $this->assertBelongs($program, $registration->training_program_id);

        try {
            $this->links->markManual($link, $registration);
        } catch (ValidationException $exception) {
            return redirect()->to($this->certificatesUrl($program, $request))->withErrors($exception->errors());
        }

        return redirect()->to($this->certificatesUrl($program, $request))
            ->with('status', 'حُضّر '.$registration->user?->name.' في «'.$link->name.'».');
    }

    private function authorizeOperate(Request $request, TrainingProgram $program): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->canAccessFilamentAdmin(), 403);
        abort_unless($actor->can('viewOperational', $program), 403);

        return $actor;
    }

    private function assertBelongs(TrainingProgram $program, mixed $programId): void
    {
        abort_unless((int) $programId === (int) $program->getKey(), 404);
    }

    private function back(TrainingProgram $program): RedirectResponse
    {
        return redirect()->to(route('staff-ui.programs.tab', [$program, 'attendance']));
    }

    private function certificatesUrl(TrainingProgram $program, Request $request): string
    {
        $query = array_filter(['q' => trim((string) $request->input('q', '')), 'page' => $request->input('page')], fn ($v) => filled($v));

        return route('staff-ui.programs.tab', [$program, 'certificates']).($query === [] ? '' : '?'.http_build_query($query));
    }
}
