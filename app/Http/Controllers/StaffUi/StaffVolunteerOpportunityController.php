<?php

namespace App\Http\Controllers\StaffUi;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Services\StaffUi\StaffVolunteerOpportunities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class StaffVolunteerOpportunityController extends Controller
{
    public function __construct(
        private readonly StaffVolunteerOpportunities $opportunities,
    ) {}

    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('viewAny', VolunteerOpportunity::class), 403);

        $view = (string) $request->query('view', 'all');
        if (! in_array($view, StaffVolunteerOpportunities::VIEWS, true)) {
            $view = 'all';
        }
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        return view('staff-ui.volunteering.index', [
            'staffName' => $actor->name,
            'staffEmail' => $actor->email,
            'opportunities' => $this->opportunities->paginate($actor, $view, $search, (int) $request->query('page', 1)),
            'viewCounts' => $this->opportunities->viewCounts($actor),
            'view' => $view,
            'search' => $search,
            'canCreate' => $actor->can('create', VolunteerOpportunity::class),
        ]);
    }

    public function create(Request $request): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('create', VolunteerOpportunity::class), 403);

        return view('staff-ui.volunteering.form', [
            'staffName' => $actor->name,
            'staffEmail' => $actor->email,
            'opportunity' => null,
            'canPublish' => $actor->hasPermissionTo('volunteering.publish'),
            'actions' => [],
            'canDelete' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('create', VolunteerOpportunity::class), 403);

        $publish = $request->boolean('publish') && $actor->hasPermissionTo('volunteering.publish');
        $opportunity = $this->opportunities->create($actor, $this->input($request), $this->image($request), $publish);

        return redirect()
            ->route('staff-ui.volunteering.edit', $opportunity)
            ->with('status', $publish ? 'أُضيفت الفرصة ونُشرت في الموقع.' : 'أُضيفت الفرصة كمسودة.');
    }

    public function edit(Request $request, VolunteerOpportunity $opportunity): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('update', $opportunity), 403);

        return view('staff-ui.volunteering.form', [
            'staffName' => $actor->name,
            'staffEmail' => $actor->email,
            'opportunity' => $opportunity,
            'canPublish' => $actor->can('publish', $opportunity),
            'actions' => $this->opportunities->availableActions($actor, $opportunity),
            'canDelete' => $actor->can('delete', $opportunity),
        ]);
    }

    public function update(Request $request, VolunteerOpportunity $opportunity): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('update', $opportunity), 403);

        $this->opportunities->update(
            $actor,
            $opportunity,
            $this->input($request),
            $this->image($request),
            $request->boolean('remove_image'),
        );

        return redirect()
            ->route('staff-ui.volunteering.edit', $opportunity)
            ->with('status', 'حُفظت التعديلات.');
    }

    public function status(Request $request, VolunteerOpportunity $opportunity): RedirectResponse
    {
        $actor = $this->actor($request);
        $action = (string) $request->input('action');
        abort_unless(in_array($action, $this->opportunities->availableActions($actor, $opportunity), true), 403);

        $message = $this->opportunities->changeStatus($actor, $opportunity, $action);

        return redirect()
            ->route('staff-ui.volunteering.edit', $opportunity)
            ->with('status', $message);
    }

    public function destroy(Request $request, VolunteerOpportunity $opportunity): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('delete', $opportunity), 403);

        if ($opportunity->certificates()->exists()) {
            return redirect()
                ->route('staff-ui.volunteering.edit', $opportunity)
                ->with('status', 'لا يمكن حذف هذه الفرصة لارتباطها بشهادات سابقة. يمكن أرشفتها بدلاً من ذلك.');
        }

        $title = $opportunity->title;
        $opportunity->delete();

        return redirect()
            ->route('staff-ui.volunteering.index')
            ->with('status', 'حُذفت الفرصة «'.$title.'».');
    }

    /**
     * @return array<string, mixed>
     */
    private function input(Request $request): array
    {
        return [
            ...$request->only(['title', 'description', 'external_url', 'capacity', 'hours_expected', 'start_date', 'end_date']),
            'notify_on_publish' => $request->boolean('notify_on_publish'),
        ];
    }

    private function image(Request $request): ?UploadedFile
    {
        $file = $request->file('image');

        return $file instanceof UploadedFile ? $file : null;
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->canAccessFilamentAdmin(), 403);

        return $actor;
    }
}
