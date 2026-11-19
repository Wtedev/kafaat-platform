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
use App\Models\SurveyCompletion;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Exports\ProgramRegistrationExportAuthorization;
use App\Services\StaffUi\StaffProgramAttendance;
use App\Services\StaffUi\StaffProgramCalendar;
use App\Services\StaffUi\StaffProgramRegistrations;
use App\Services\StaffUi\StaffProgramSettings;
use App\Services\StaffUi\StaffProgramStatus;
use App\Services\StaffUi\StaffProgramWizard;
use App\Support\Exports\ProgramRegistrationExportColumns;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    private const BUILT_TABS = ['about', 'registrations', 'attendance', 'activity', 'settings'];

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

        if ($tab === 'registrations' || $tab === 'attendance') {
            abort_unless($actor->can('viewOperational', $program), 403);
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
            'registrations' => $this->registrationsData($request, $actor, $program),
            'attendance' => $this->attendanceData($request, $program),
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
            'calendar' => app(StaffProgramCalendar::class)->build($program),
            'recentActivity' => $program->activities()->with('actor:id,name')->limit(5)->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationsData(Request $request, User $actor, TrainingProgram $program): array
    {
        $service = app(StaffProgramRegistrations::class);
        $filters = $service->filters($request->query());

        return [
            'filters' => $filters,
            'statusCounts' => $service->statusCounts($program),
            'registrationRows' => $service->query($program, $filters)
                ->with(['user:id,name,email,phone,identity_category', 'user.profile:id,user_id,gender,birth_date'])
                ->latest('created_at')
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'canApprove' => $actor->hasPermissionTo('registrations.approve'),
            'canReject' => $actor->hasPermissionTo('registrations.reject'),
            'canExport' => ProgramRegistrationExportAuthorization::canExport($actor, $program),
            'exportColumns' => ProgramRegistrationExportColumns::groupedOptionLabels($actor, $program),
            'exportGroupTitles' => ProgramRegistrationExportColumns::groupTitles(),
            'exportDefaults' => ProgramRegistrationExportAuthorization::defaultColumnKeysFor($actor, $program),
            'previewTarget' => $service->query($program, $filters)->where('status', RegistrationStatus::Pending->value)->value('id'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attendanceData(Request $request, TrainingProgram $program): array
    {
        $service = app(StaffProgramAttendance::class);
        $days = $service->days($program);
        $approved = (int) $program->approved_registrations_count;

        $selected = $days->first(fn (array $row): bool => $row['day']->dateString() === (string) $request->query('day'))
            ?? $days->first(fn (array $row): bool => $row['day']->dateString() === now()->toDateString());
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 60);

        $roster = null;
        $presentMap = collect();
        if ($selected !== null) {
            $roster = $service->roster($program, $search)
                ->with('user:id,name')
                ->join('users', 'users.id', '=', 'program_registrations.user_id')
                ->orderBy('users.name')
                ->select('program_registrations.*')
                ->paginate(50)
                ->withQueryString();
            $presentMap = $service->attendanceOn(
                $selected['day']->dateString(),
                $roster->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all(),
            );
        }

        return [
            'attendanceDays' => $days,
            'approvedTotal' => $approved,
            'selectedDay' => $selected['day'] ?? null,
            'roster' => $roster,
            'presentMap' => $presentMap,
            'rosterSearch' => $search,
            'attendanceLinks' => $program->attendanceLinks()->withCount('marks')->latest('id')->get(),
            'attendanceCheckers' => $program->attendanceCheckers()->orderBy('name')->get(),
            'dayTypes' => ProgramPrepDayType::options(),
        ];
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
