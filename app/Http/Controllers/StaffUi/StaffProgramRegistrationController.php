<?php

namespace App\Http\Controllers\StaffUi;

use App\Exceptions\ProgramCapacityExceededException;
use App\Exceptions\RegistrationNotEligibleException;
use App\Http\Controllers\Controller;
use App\Jobs\BulkApproveProgramRegistrationsJob;
use App\Jobs\BulkRejectProgramRegistrationsJob;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Exports\ProgramRegistrationExportAuthorization;
use App\Services\Exports\ProgramRegistrationExportService;
use App\Services\ProgramRegistration\BulkProgramRegistrationProcessor;
use App\Services\ProgramRegistrationService;
use App\Services\StaffUi\StaffProgramRegistrations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Actions of the registrations tab. Listing is rendered by {@see StaffProgramPageController}.
 */
class StaffProgramRegistrationController extends Controller
{
    public function __construct(
        private readonly StaffProgramRegistrations $registrations,
        private readonly ProgramRegistrationService $service,
        private readonly BulkProgramRegistrationProcessor $bulk,
    ) {}

    public function approve(Request $request, TrainingProgram $program, ProgramRegistration $registration): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->assertBelongs($program, $registration);
        abort_unless($actor->can('approve', $registration), 403);

        try {
            $this->service->approve($registration, $actor);
        } catch (ProgramCapacityExceededException) {
            return $this->back($request, $program)->withErrors(['registrations' => 'البرنامج بلغ سعته القصوى.']);
        } catch (RegistrationNotEligibleException $e) {
            return $this->back($request, $program)->withErrors(['registrations' => $e->userMessage()]);
        }

        return $this->back($request, $program)->with('status', 'تم قبول '.$registration->user?->name.' وإرسال إيميل القبول.');
    }

    public function reject(Request $request, TrainingProgram $program, ProgramRegistration $registration): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->assertBelongs($program, $registration);
        abort_unless($actor->can('reject', $registration), 403);
        $reason = $this->reason($request);

        $this->service->reject($registration, $reason);

        return $this->back($request, $program)->with('status', 'تم رفض '.$registration->user?->name.' وإرسال إيميل الاعتذار.');
    }

    public function bulk(Request $request, TrainingProgram $program): RedirectResponse|BinaryFileResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('viewOperational', $program), 403);

        $action = (string) $request->input('action');
        abort_unless(in_array($action, ['approve', 'reject', 'export'], true), 422);

        $filters = $this->registrations->filters($request->all());
        $ids = $this->selectedIds($request, $program, $filters);
        if ($ids === []) {
            return $this->back($request, $program)->withErrors(['registrations' => 'اختر تسجيلاً واحداً على الأقل.']);
        }

        if ($action === 'export') {
            return $this->export($actor, $program, $ids, $request);
        }

        abort_unless($actor->hasPermissionTo($action === 'approve' ? 'registrations.approve' : 'registrations.reject'), 403);
        $reason = $this->reason($request);

        if (count($ids) > BulkProgramRegistrationProcessor::QUEUE_THRESHOLD) {
            $action === 'approve'
                ? BulkApproveProgramRegistrationsJob::dispatch($actor->id, $program->id, $ids, $filters)
                : BulkRejectProgramRegistrationsJob::dispatch($actor->id, $program->id, $ids, $reason, $filters);

            return $this->back($request, $program)->with('status', 'بدأت معالجة '.en_num(count($ids)).' تسجيلاً في الخلفية، وسيصلك تنبيه عند الانتهاء.');
        }

        $records = ProgramRegistration::query()->whereIn('id', $ids)->with('user.profile')->get();

        if ($action === 'approve') {
            $summary = $this->bulk->approve($records, $actor, $program, $filters);
            $message = 'قُبل '.en_num($summary['approved']).' وأُرسل لهم إيميل القبول';
            $message .= $summary['skipped'] > 0 ? '، وتُخطّي '.en_num($summary['skipped']).' (ليس بانتظار القبول أو لا يستوفي الشروط)' : '';
            $message .= $summary['capacity_blocked'] > 0 ? '، وتوقف '.en_num($summary['capacity_blocked']).' بسبب السعة' : '';
        } else {
            $summary = $this->bulk->reject($records, $actor, $program, $reason, $filters);
            $message = 'رُفض '.en_num($summary['rejected']).' وأُرسل لهم إيميل الاعتذار';
            $message .= $summary['skipped'] > 0 ? '، وتُخطّي '.en_num($summary['skipped']) : '';
        }

        return $this->back($request, $program)->with('status', $message.'.');
    }

    public function preview(Request $request, TrainingProgram $program): Response
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('viewOperational', $program), 403);

        $kind = $request->query('kind') === 'reject' ? 'reject' : 'approve';
        $registration = ProgramRegistration::query()
            ->where('training_program_id', $program->getKey())
            ->when($request->filled('registration'), fn (Builder $q) => $q->whereKey((int) $request->query('registration')))
            ->with('user.profile', 'trainingProgram')
            ->first();

        if ($registration === null) {
            return response('<p style="font-family:sans-serif;padding:24px;text-align:center">لا يوجد مسجّل للمعاينة.</p>');
        }

        $html = $this->registrations->emailPreview($registration, $kind, $this->reason($request));

        return response($html);
    }

    public function window(Request $request, TrainingProgram $program): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('update', $program), 403);

        if ($request->input('action') === 'reopen') {
            $this->registrations->reopenRegistration($actor, $program);

            return $this->back($request, $program)->with('status', 'أُعيد فتح التسجيل حسب تواريخه.');
        }

        $this->registrations->closeRegistration($actor, $program);

        return $this->back($request, $program)->with('status', 'أُغلق التسجيل، ولن تُقبل طلبات جديدة حتى تعيدي فتحه.');
    }

    public function exportAll(Request $request, TrainingProgram $program): RedirectResponse|BinaryFileResponse
    {
        $actor = $this->actor($request);
        $filters = $this->registrations->filters($request->all());
        $ids = $this->registrations->query($program, $filters)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($ids === []) {
            return $this->back($request, $program)->withErrors(['registrations' => 'لا توجد صفوف للتصدير.']);
        }

        return $this->export($actor, $program, $ids, $request);
    }

    /**
     * @param  list<int>  $ids
     */
    private function export(User $actor, TrainingProgram $program, array $ids, Request $request): RedirectResponse|BinaryFileResponse
    {
        abort_unless(ProgramRegistrationExportAuthorization::canExport($actor, $program), 403);

        $keys = array_values(array_filter((array) $request->input('columns', []), 'is_string'));
        if ($keys === []) {
            $keys = ProgramRegistrationExportAuthorization::defaultColumnKeysFor($actor, $program);
        }

        $this->bulk->auditExport($actor, $program, count($ids), $this->registrations->filters($request->all()));

        $download = app(ProgramRegistrationExportService::class)->download(
            $actor,
            $program,
            $keys,
            ProgramRegistrationExportService::SCOPE_SELECTED,
            null,
            $ids,
        );

        return $download ?? $this->back($request, $program)->withErrors(['registrations' => 'لا توجد صفوف للتصدير.']);
    }

    /**
     * @param  array{q: string, status: string, nationality: string, gender: string, age: string}  $filters
     * @return list<int>
     */
    private function selectedIds(Request $request, TrainingProgram $program, array $filters): array
    {
        if ($request->boolean('all_matching')) {
            return $this->registrations->query($program, $filters)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $requested = array_map('intval', array_filter((array) $request->input('ids', []), 'is_numeric'));

        return ProgramRegistration::query()
            ->where('training_program_id', $program->getKey())
            ->whereIn('id', $requested)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function reason(Request $request): ?string
    {
        $reason = trim((string) $request->input('rejected_reason', ''));

        return $reason === '' ? null : mb_substr($reason, 0, 2000);
    }

    private function assertBelongs(TrainingProgram $program, ProgramRegistration $registration): void
    {
        abort_unless((int) $registration->training_program_id === (int) $program->getKey(), 404);
    }

    private function back(Request $request, TrainingProgram $program): RedirectResponse
    {
        $query = array_filter(
            $this->registrations->filters($request->all()) + ['page' => $request->input('page')],
            fn ($value) => filled($value),
        );

        return redirect()->to(route('staff-ui.programs.tab', [$program, 'registrations']).($query === [] ? '' : '?'.http_build_query($query)));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->canAccessFilamentAdmin(), 403);

        return $actor;
    }
}
