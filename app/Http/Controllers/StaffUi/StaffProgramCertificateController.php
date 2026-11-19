<?php

namespace App\Http\Controllers\StaffUi;

use App\Http\Controllers\Controller;
use App\Jobs\IssueProgramCertificateJob;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\StaffUi\StaffProgramCertificates;
use App\Support\CertificateAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Issue certificates from the certificates tab. Issuing runs in the background
 * (PDF + email), exactly like the current admin panel.
 */
class StaffProgramCertificateController extends Controller
{
    public function __construct(
        private readonly StaffProgramCertificates $certificates,
    ) {}

    public function issue(Request $request, TrainingProgram $program): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->canAccessFilamentAdmin(), 403);
        abort_unless($actor->can('viewOperational', $program) && CertificateAuthorization::canIssue($actor), 403);

        $back = redirect()->to(route('staff-ui.programs.tab', [$program, 'certificates']).$this->query($request));

        if (! $this->certificates->backgroundReady($program)) {
            return $back->withErrors(['certificates' => 'يجب تصميم الشهادة ورفع خلفيتها أولاً.']);
        }

        $requested = array_map('intval', array_filter((array) $request->input('ids', []), 'is_numeric'));
        $issuedUsers = $this->certificates->certificates($program)->keys()->map(fn ($id) => (int) $id)->all();

        $pending = $this->certificates->rows($program)
            ->whereIn('program_registrations.id', $requested)
            ->get()
            ->reject(fn (ProgramRegistration $r): bool => in_array((int) $r->user_id, $issuedUsers, true));

        if ($pending->isEmpty()) {
            return $back->withErrors(['certificates' => 'لا توجد شهادات جديدة بين المحددين.']);
        }

        foreach ($pending as $registration) {
            $job = new IssueProgramCertificateJob((int) $registration->id, (int) $actor->id);
            dispatch(config('filesystems.disks.public.driver') === 'local' ? $job->onQueue('certificates') : $job);
        }

        app(AuditLogger::class)->record(
            actor: $actor,
            action: 'program_certificates.issued',
            resource: $program,
            metadata: ['count' => $pending->count(), 'selected' => true, 'source' => 'staff-ui', 'registration_ids' => $pending->modelKeys()],
        );

        return $back->with('status', 'بدأ إصدار '.en_num($pending->count()).' شهادة، وتصل إلى المتدربين بالبريد الإلكتروني عند اكتمالها.');
    }

    private function query(Request $request): string
    {
        $query = array_filter(['q' => trim((string) $request->input('q', '')), 'page' => $request->input('page')], fn ($v) => filled($v));

        return $query === [] ? '' : '?'.http_build_query($query);
    }
}
