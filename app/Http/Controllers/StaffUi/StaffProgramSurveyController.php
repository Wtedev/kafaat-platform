<?php

namespace App\Http\Controllers\StaffUi;

use App\Enums\ProgramActivityAction;
use App\Enums\SurveyQuestionType;
use App\Enums\SurveyType;
use App\Exports\ProgramSurveyExport;
use App\Http\Controllers\Controller;
use App\Models\ProgramSurvey;
use App\Models\SurveyTemplate;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramActivity\ProgramActivityLog;
use App\Services\Surveys\ProgramSurveyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The surveys tab: create the three surveys, edit their questions and dates,
 * open or close them now, and export answers.
 */
class StaffProgramSurveyController extends Controller
{
    public function __construct(
        private readonly ProgramSurveyService $surveys,
        private readonly ProgramActivityLog $activity,
    ) {}

    public function provision(Request $request, TrainingProgram $program): RedirectResponse
    {
        $actor = $this->authorizeFor($request, $program, 'update');

        if ($program->surveys()->exists()) {
            return $this->back($program)->withErrors(['surveys' => 'الاستبيانات منشأة من قبل.']);
        }

        $data = $request->validate([
            'source' => ['required', Rule::in(['template', 'blank'])],
            'pre_template_id' => ['required_if:source,template', 'nullable', 'integer', Rule::exists('survey_templates', 'id')],
            'satisfaction_template_id' => ['required_if:source,template', 'nullable', 'integer', Rule::exists('survey_templates', 'id')],
        ], [
            'pre_template_id.required_if' => 'يُرجى اختيار قالب الاستبيان القبلي.',
            'satisfaction_template_id.required_if' => 'يُرجى اختيار قالب استبيان الرضا.',
        ]);

        if ($data['source'] === 'blank') {
            $this->surveys->provisionFromScratch($program);
        } else {
            $this->surveys->provision(
                $program,
                SurveyTemplate::query()->findOrFail($data['pre_template_id']),
                SurveyTemplate::query()->findOrFail($data['satisfaction_template_id']),
            );
        }

        $this->activity->record($program, ProgramActivityAction::SurveysCreated, null, $data['source'] === 'blank' ? 'من الصفر' : 'من قالب', [], $actor);

        return $this->back($program)->with('status', 'تم إنشاء الاستبيانات الثلاثة.');
    }

    public function update(Request $request, TrainingProgram $program, ProgramSurvey $survey): RedirectResponse
    {
        $actor = $this->authorizeFor($request, $program, 'update');
        $this->assertBelongs($program, $survey);

        $data = $request->validate([
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date'],
            'questions' => ['nullable', 'array', 'max:60'],
            'questions.*.type' => ['required', Rule::enum(SurveyQuestionType::class)],
            'questions.*.prompt' => ['required', 'string', 'max:500'],
            'questions.*.options' => ['nullable', 'string', 'max:3000'],
            'questions.*.scale_min_label' => ['nullable', 'string', 'max:80'],
            'questions.*.scale_max_label' => ['nullable', 'string', 'max:80'],
            'questions.*.required' => ['nullable', 'boolean'],
        ], [
            'questions.*.prompt.required' => 'كل سؤال يحتاج نصاً.',
        ]);

        $editableQuestions = $survey->type !== SurveyType::Post && ! $this->surveys->questionsLocked($survey);

        try {
            $this->surveys->updateWindow($survey, $this->localTime($data['opens_at'] ?? null), $this->localTime($data['closes_at'] ?? null));
            if ($editableQuestions && $request->boolean('has_questions')) {
                $this->surveys->replaceQuestions($survey, array_map(fn (array $q): array => [
                    ...$q,
                    'required' => (bool) ($q['required'] ?? false),
                ], array_values($data['questions'] ?? [])));
            }
        } catch (ValidationException $exception) {
            return redirect()->to($this->editUrl($program, $survey))->withErrors($exception->errors())->withInput();
        }

        $this->activity->record($program, ProgramActivityAction::SurveyUpdated, $survey, 'استبيان '.$survey->type->label(), [], $actor);

        return $this->back($program)->with('status', 'تم حفظ الاستبيان '.$survey->type->label().'.');
    }

    public function window(Request $request, TrainingProgram $program, ProgramSurvey $survey): RedirectResponse
    {
        $actor = $this->authorizeFor($request, $program, 'update');
        $this->assertBelongs($program, $survey);
        $action = (string) $request->input('action');
        abort_unless(in_array($action, ['open', 'close'], true), 422);

        if ($action === 'open') {
            $closes = $survey->closes_at !== null && $survey->closes_at->isFuture() ? $survey->closes_at : now()->addDays(7)->endOfDay();
            $survey->update(['opens_at' => now(), 'closes_at' => $closes]);
            $message = 'فُتح الاستبيان '.$survey->type->label().' حتى '.en_digits(ar_date($closes->copy()->timezone(config('app.timezone')), 'd MMMM')).'.';
        } else {
            $survey->update(['closes_at' => now(), 'opens_at' => $survey->opens_at ?? now()]);
            $message = 'أُغلق الاستبيان '.$survey->type->label().'.';
        }

        $this->activity->record($program, ProgramActivityAction::SurveyUpdated, $survey, ($action === 'open' ? 'فتح' : 'إغلاق').' استبيان '.$survey->type->label(), [], $actor);

        return $this->back($program)->with('status', $message);
    }

    public function export(Request $request, TrainingProgram $program, ProgramSurvey $survey): BinaryFileResponse
    {
        $this->authorizeFor($request, $program, 'viewOperational');
        $this->assertBelongs($program, $survey);

        return Excel::download(new ProgramSurveyExport($survey), 'survey-'.$program->id.'-'.$survey->type->value.'.xlsx');
    }

    private function authorizeFor(Request $request, TrainingProgram $program, string $ability): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->canAccessFilamentAdmin(), 403);
        abort_unless($actor->can($ability, $program), 403);

        return $actor;
    }

    private function assertBelongs(TrainingProgram $program, ProgramSurvey $survey): void
    {
        abort_unless((int) $survey->training_program_id === (int) $program->getKey(), 404);
    }

    /** The form sends local (Riyadh) date-times without a zone. */
    private function localTime(?string $value): ?Carbon
    {
        return filled($value) ? Carbon::parse($value, config('app.timezone')) : null;
    }

    private function back(TrainingProgram $program): RedirectResponse
    {
        return redirect()->route('staff-ui.programs.tab', [$program, 'surveys']);
    }

    private function editUrl(TrainingProgram $program, ProgramSurvey $survey): string
    {
        return route('staff-ui.programs.tab', [$program, 'surveys']).'?edit='.$survey->id;
    }
}
