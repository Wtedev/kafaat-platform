<?php

namespace App\Http\Controllers\StaffUi;

use App\Enums\ProgramActivityAction;
use App\Enums\ProgramPrepDayType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Filament\Resources\TrainingProgramResource;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\ProgramActivity;
use App\Models\ProgramAttendanceLink;
use App\Models\ProgramPrepDay;
use App\Models\SurveyCompletion;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\StaffUi\StaffProgramCalendar;
use App\Services\StaffUi\StaffProgramSettings;
use App\Services\StaffUi\StaffProgramStatus;
use App\Services\StaffUi\StaffProgramWizard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The program page in the new staff UI: one URL per tab.
 */
class StaffProgramPageController extends Controller
{
    /** Tab key => label, in working order. */
    public const TABS = [
        'about' => 'عن البرنامج',
        'registrations' => 'التسجيلات',
        'attendance' => 'التحضير',
        'surveys' => 'الاستبيانات',
        'certificates' => 'الشهادات',
        'broadcasts' => 'الرسائل',
        'activity' => 'سجل النشاط',
        'settings' => 'الإعدادات',
    ];

    /** Tabs that are built in the new UI; the rest still live in the current admin panel. */
    private const BUILT_TABS = ['about', 'activity', 'settings'];

    public function __construct(
        private readonly StaffProgramWizard $wizard,
        private readonly StaffProgramSettings $settings,
        private readonly StaffProgramStatus $status,
    ) {}

    public function show(Request $request, TrainingProgram $program, string $tab = 'about'): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('view', $program), 403);
        abort_unless(array_key_exists($tab, self::TABS), 404);

        if ($tab === 'settings') {
            abort_unless($actor->can('update', $program), 403);
        }

        $program->loadCount([
            'registrations',
            'registrations as pending_registrations_count' => fn ($q) => $q->where('status', RegistrationStatus::Pending->value),
            'registrations as approved_registrations_count' => fn ($q) => $q->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ]),
            'prepDays',
        ]);

        $data = [
            'staffName' => $actor->name,
            'staffEmail' => $actor->email,
            'program' => $program,
            'tab' => $tab,
            'tabs' => $this->tabs($actor, $program, $tab),
            'programStatus' => $this->status,
            'availability' => $this->status->registrationAvailability($program),
            'canUpdate' => $actor->can('update', $program),
            'canResumeWizard' => $program->status === ProgramStatus::Draft
                && $this->wizard->canContinue($actor, $program)
                && $this->wizard->firstInvalidStoredStep($program) !== null,
            'adminUrl' => TrainingProgramResource::getUrl('view', ['record' => $program]),
        ];

        return view('staff-ui.programs.show', $data + match ($tab) {
            'about' => $this->aboutData($program),
            'activity' => $this->activityData($request, $program),
            'settings' => $this->settingsData($actor, $program),
            default => [],
        });
    }

    public function updateSettings(Request $request, TrainingProgram $program, string $section): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('update', $program), 403);
        abort_unless(array_key_exists($section, StaffProgramSettings::SECTIONS), 404);

        $changed = $this->settings->updateSection($actor, $program, $section, $request->all(), $request->file('image'));

        return redirect()
            ->to(route('staff-ui.programs.tab', [$program, 'settings']).'#'.$section)
            ->with('status', $changed === []
                ? 'لا تغييرات في «'.StaffProgramSettings::SECTION_LABELS[$section].'».'
                : 'تم حفظ «'.StaffProgramSettings::SECTION_LABELS[$section].'».');
    }

    public function updateStatus(Request $request, TrainingProgram $program): RedirectResponse
    {
        $actor = $this->actor($request);
        $action = (string) $request->input('action');

        match ($action) {
            'publish' => $this->settings->publish($actor, $program),
            'unpublish' => $this->settings->unpublish($actor, $program),
            'archive' => $this->settings->archive($actor, $program),
            'restore' => $this->settings->restore($actor, $program),
            default => abort(422),
        };

        return redirect()
            ->to(route('staff-ui.programs.tab', [$program, 'settings']).'#status')
            ->with('status', match ($action) {
                'publish' => 'تم نشر البرنامج.',
                'unpublish' => 'أصبح البرنامج مسودة ولم يعد ظاهراً للزوار.',
                'archive' => 'تمت أرشفة البرنامج.',
                default => 'تمت استعادة البرنامج كمسودة.',
            });
    }

    public function previewMail(Request $request, TrainingProgram $program): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('update', $program), 403);

        $this->settings->updateSection($actor, $program, 'message', $request->all());
        $this->wizard->sendPreview($actor, $program, $request->all());

        return redirect()
            ->to(route('staff-ui.programs.tab', [$program, 'settings']).'#message')
            ->with('status', 'تم حفظ الرسالة وإرسال نسخة تجريبية إلى بريدك.');
    }

    public function acceptancePreview(Request $request, TrainingProgram $program): JsonResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('update', $program), 403);

        return response()->json(['lines' => $this->wizard->acceptanceLinesFromInput($request->all())]);
    }

    /**
     * @return list<array{id: string, label: string, href: string, active: bool}>
     */
    private function tabs(User $actor, TrainingProgram $program, string $current): array
    {
        $tabs = [];
        foreach (self::TABS as $key => $label) {
            if ($key === 'settings' && ! $actor->can('update', $program)) {
                continue;
            }

            $tabs[] = [
                'id' => $key,
                'label' => $label,
                'href' => route('staff-ui.programs.tab', [$program, $key]),
                'active' => $key === $current,
                'built' => in_array($key, self::BUILT_TABS, true),
            ];
        }

        return $tabs;
    }

    /**
     * @return array<string, mixed>
     */
    private function aboutData(TrainingProgram $program): array
    {
        return [
            'certificatesCount' => Certificate::query()
                ->where('certificateable_type', $program->getMorphClass())
                ->where('certificateable_id', $program->getKey())
                ->count(),
            'surveyCompletionsCount' => SurveyCompletion::query()
                ->whereHas('survey', fn ($q) => $q->where('training_program_id', $program->getKey()))
                ->count(),
            'weekdayOptions' => StaffProgramWizard::formOptions()['weekdayOptions'],
            'calendar' => app(StaffProgramCalendar::class)->build($program),
            'attendanceDays' => $this->attendanceDays($program),
            'recentActivity' => $program->activities()->with('actor:id,name')->limit(5)->get(),
        ];
    }

    /**
     * Days that have a way to take attendance: an in-person prep day (QR scanned at the door)
     * or a remote prep day / an attendance link (trainees check in from a link). Oldest first.
     *
     * @return Collection<int, array{date: ?Carbon, kind: string, title: string, state: ?string, count: ?int}>
     */
    private function attendanceDays(TrainingProgram $program): Collection
    {
        $prepDays = $program->prepDays()->get()->map(fn (ProgramPrepDay $day): array => [
            'date' => $day->prep_date,
            'kind' => $day->delivery_type === ProgramPrepDayType::Remote ? 'link' : 'qr',
            'title' => $day->delivery_type === ProgramPrepDayType::Remote ? 'يوم عن بُعد' : 'يوم حضوري',
            'state' => null,
            'count' => null,
        ]);

        $links = $program->attendanceLinks()
            ->whereNull('cancelled_at')
            ->withCount('marks')
            ->get()
            ->map(fn (ProgramAttendanceLink $link): array => [
                'date' => ($link->opens_at ?? $link->created_at)?->timezone(config('app.timezone')),
                'kind' => 'link',
                'title' => filled($link->name) ? (string) $link->name : 'رابط تحضير',
                'state' => match (true) {
                    $link->isOpen() => 'مفتوح الآن',
                    $link->opens_at === null => 'لم يُفتح بعد',
                    default => 'أُغلق',
                },
                'count' => (int) $link->marks_count,
            ]);

        return $prepDays->concat($links)
            ->sortBy(fn (array $row): string => $row['date']?->toDateString() ?? '9999-12-31')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function activityData(Request $request, TrainingProgram $program): array
    {
        $groups = ProgramActivityAction::groupOptions();
        $group = (string) $request->query('group', '');
        if (! array_key_exists($group, $groups)) {
            $group = '';
        }

        $query = ProgramActivity::query()
            ->where('training_program_id', $program->getKey())
            ->with('actor:id,name')
            ->latest('created_at')
            ->latest('id');

        if ($group !== '') {
            $query->whereIn('action', ProgramActivityAction::valuesInGroup($group));
        }

        return [
            'activities' => $query->paginate(30)->withQueryString(),
            'activityGroups' => $groups,
            'activityGroup' => $group,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsData(User $actor, TrainingProgram $program): array
    {
        return [
            ...StaffProgramWizard::formOptions(),
            'acceptancePreview' => $this->wizard->acceptancePreview($program),
            'sectionLabels' => StaffProgramSettings::SECTION_LABELS,
            'canPublish' => $actor->can('publish', $program),
            'canArchive' => $actor->can('archive', $program),
            'notifyOn' => $program->notify_on_publish && $program->notify_milestones && $program->notify_registrants_on_update,
        ];
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->canAccessFilamentAdmin(), 403);

        return $actor;
    }
}
