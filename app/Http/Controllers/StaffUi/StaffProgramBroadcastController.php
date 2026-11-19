<?php

namespace App\Http\Controllers\StaffUi;

use App\Exceptions\ProgramBroadcastException;
use App\Http\Controllers\Controller;
use App\Mail\ProgramBroadcastMail;
use App\Models\ProgramBroadcast;
use App\Models\ProgramBroadcastRecipient;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramBroadcasts\ProgramBroadcastService;
use App\Services\StaffUi\StaffProgramBroadcasts;
use App\Support\RichContentSupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * The messages tab: write an email to a group of registrants, preview it, save
 * it as a draft or send it. Sending runs in the background in small batches.
 */
class StaffProgramBroadcastController extends Controller
{
    public function __construct(
        private readonly ProgramBroadcastService $broadcasts,
        private readonly StaffProgramBroadcasts $segments,
    ) {}

    public function store(Request $request, TrainingProgram $program): RedirectResponse
    {
        $actor = $this->actorFor($request, $program);
        $data = $this->validated($request, $program);

        try {
            $broadcast = $this->broadcasts->createDraft($program, $actor, $data);
            if ($request->input('intent') === 'send') {
                return $this->send($actor, $program, $broadcast);
            }
        } catch (ProgramBroadcastException $exception) {
            return $this->back($program)->withErrors(['broadcast' => $exception->getMessage()])->withInput();
        }

        return $this->back($program)->with('status', 'حُفظت الرسالة كمسودة.');
    }

    public function update(Request $request, TrainingProgram $program, ProgramBroadcast $broadcast): RedirectResponse
    {
        $actor = $this->actorFor($request, $program);
        $this->assertBelongs($program, $broadcast);
        $data = $this->validated($request, $program);

        try {
            $broadcast = $this->broadcasts->updateDraft($broadcast, $actor, $data);
            if ($request->input('intent') === 'send') {
                return $this->send($actor, $program, $broadcast);
            }
        } catch (ProgramBroadcastException $exception) {
            return redirect()->to($this->tabUrl($program).'?edit='.$broadcast->id)->withErrors(['broadcast' => $exception->getMessage()])->withInput();
        }

        return $this->back($program)->with('status', 'حُفظت المسودة.');
    }

    public function action(Request $request, TrainingProgram $program, ProgramBroadcast $broadcast): RedirectResponse
    {
        $actor = $this->actorFor($request, $program);
        $this->assertBelongs($program, $broadcast);

        try {
            return match ((string) $request->input('action')) {
                'send' => $this->send($actor, $program, $broadcast),
                'retry' => tap($this->back($program)->with('status', 'أُعيدت محاولة الإرسال إلى من لم تصلهم الرسالة.'), fn () => $this->broadcasts->retryFailed($broadcast, $actor)),
                'copy' => redirect()->to($this->tabUrl($program).'?edit='.$this->broadcasts->copyToNewDraft($broadcast, $actor)->id)->with('status', 'نُسخت الرسالة كمسودة جديدة.'),
                'delete' => tap($this->back($program)->with('status', 'حُذفت المسودة.'), fn () => $this->broadcasts->deleteDraft($broadcast, $actor)),
                default => abort(422),
            };
        } catch (ProgramBroadcastException $exception) {
            return $this->back($program)->withErrors(['broadcast' => $exception->getMessage()]);
        }
    }

    /** Renders the email exactly as a trainee would get it. */
    public function preview(Request $request, TrainingProgram $program): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->canAccessFilamentAdmin() && $actor->can('viewOperational', $program), 403);

        $subject = mb_substr(trim((string) $request->input('subject', '')), 0, 255);
        $broadcast = new ProgramBroadcast([
            'subject' => $subject !== '' ? $subject : 'بدون عنوان',
            'content' => RichContentSupport::normalizeForStorage((string) $request->input('content', '')) ?? '',
        ]);
        $recipient = new ProgramBroadcastRecipient(['name' => 'اسم المتدرب']);

        $html = (new ProgramBroadcastMail($broadcast, $recipient, (string) $program->title))->render();

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function send(User $actor, TrainingProgram $program, ProgramBroadcast $broadcast): RedirectResponse
    {
        $queued = $this->broadcasts->sendNow($broadcast, $actor);

        return $this->back($program)->with('status', 'بدأ إرسال الرسالة إلى '.en_num((int) $queued->recipients_count).' شخص. ويمكن متابعة حالة الوصول من سجل الرسائل.');
    }

    /**
     * @return array{subject: string, content: string, audience_mode: string, audience_statuses: ?list<string>, audience_filter: ?array<string, mixed>}
     */
    private function validated(Request $request, TrainingProgram $program): array
    {
        $segments = $this->segments->segments($program);
        $data = $request->validate([
            'segment' => ['required', Rule::in(array_keys($segments))],
            'subject' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:20000'],
        ], [
            'subject.required' => 'عنوان الرسالة مطلوب.',
            'content.required' => 'نص الرسالة مطلوب.',
            'segment.in' => 'يُرجى اختيار المستلمين.',
        ]);

        return [
            'subject' => $data['subject'],
            'content' => $data['content'],
            ...$segments[$data['segment']]['data'],
        ];
    }

    private function actorFor(Request $request, TrainingProgram $program): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->canAccessFilamentAdmin(), 403);
        abort_unless($actor->can('create', [ProgramBroadcast::class, $program]), 403);

        return $actor;
    }

    private function assertBelongs(TrainingProgram $program, ProgramBroadcast $broadcast): void
    {
        abort_unless((int) $broadcast->training_program_id === (int) $program->getKey(), 404);
    }

    private function tabUrl(TrainingProgram $program): string
    {
        return route('staff-ui.programs.tab', [$program, 'broadcasts']);
    }

    private function back(TrainingProgram $program): RedirectResponse
    {
        return redirect()->to($this->tabUrl($program));
    }
}
