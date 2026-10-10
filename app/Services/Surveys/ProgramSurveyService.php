<?php

namespace App\Services\Surveys;

use App\Enums\RegistrationStatus;
use App\Enums\SurveyQuestionType;
use App\Enums\SurveyType;
use App\Models\ProgramRegistration;
use App\Models\ProgramSurvey;
use App\Models\ProgramSurveyQuestion;
use App\Models\SurveyAccessAttempt;
use App\Models\SurveyAnswer;
use App\Models\SurveyCompletion;
use App\Models\SurveyResponse;
use App\Models\SurveyTemplate;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Identity\IdentityNumberService;
use App\Support\Surveys\SurveyNationalIdHasher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProgramSurveyService
{
    public const NOT_FOUND_MESSAGE = 'تعذّر العثور على تسجيلك في هذا البرنامج، تواصل مع منسق البرنامج.';

    public const ALREADY_MESSAGE = 'سبق أن أكملت هذا الاستبيان، شكرًا لك.';

    public const UNAVAILABLE_MESSAGE = 'الاستبيان غير متاح حاليًا';

    public const THANKS_MESSAGE = 'شكرًا لك، تم استلام إجابتك.';

    public const TURNSTILE_MESSAGE = 'تعذر التحقق من الطلب. حاول مرة أخرى.';

    public const QUESTIONS_LOCKED_MESSAGE = 'لا يمكن تعديل أسئلة القبلي أو البعدي بعد وصول أي رد، حتى تبقى المقارنة بينهما صحيحة.';

    public const POST_FOLLOWS_PRE_MESSAGE = 'أسئلة البعدي نسخة من القبلي ولا تُعدَّل بشكل مستقل.';

    public function provision(TrainingProgram $program, SurveyTemplate $preTemplate, SurveyTemplate $satisfactionTemplate): void
    {
        DB::transaction(function () use ($program, $preTemplate, $satisfactionTemplate): void {
            $pre = $this->createFromTemplate($program, SurveyType::Pre, $preTemplate, anonymous: false);
            $post = $this->makeSurvey($program, SurveyType::Post, null, anonymous: false);
            $this->copyQuestions($pre, $post);
            $this->createFromTemplate($program, SurveyType::Satisfaction, $satisfactionTemplate, anonymous: true);
        });
    }

    public function provisionFromScratch(TrainingProgram $program): void
    {
        DB::transaction(function () use ($program): void {
            $this->makeSurvey($program, SurveyType::Pre, null, anonymous: false);
            $this->makeSurvey($program, SurveyType::Post, null, anonymous: false);
            $this->makeSurvey($program, SurveyType::Satisfaction, null, anonymous: true);
        });
    }

    public function questionsLocked(ProgramSurvey $survey): bool
    {
        if (! in_array($survey->type, [SurveyType::Pre, SurveyType::Post], true)) {
            return false;
        }

        $ids = ProgramSurvey::query()
            ->where('training_program_id', $survey->training_program_id)
            ->whereIn('type', [SurveyType::Pre->value, SurveyType::Post->value])
            ->pluck('id');

        return SurveyResponse::query()->whereIn('program_survey_id', $ids)->exists();
    }

    /**
     * @param  list<array{type: string, prompt: string, options?: string|list<string>|null, required?: bool}>  $questions
     */
    public function replaceQuestions(ProgramSurvey $survey, array $questions): void
    {
        if ($survey->type === SurveyType::Post) {
            throw ValidationException::withMessages([
                'questions' => self::POST_FOLLOWS_PRE_MESSAGE,
            ]);
        }

        if ($this->questionsLocked($survey)) {
            throw ValidationException::withMessages([
                'questions' => self::QUESTIONS_LOCKED_MESSAGE,
            ]);
        }

        DB::transaction(function () use ($survey, $questions): void {
            $this->writeQuestions($survey, $questions);

            if ($survey->type === SurveyType::Pre) {
                $post = $this->sibling($survey, SurveyType::Post);
                if ($post !== null) {
                    $this->copyQuestions($survey->fresh('questions'), $post);
                }
            }
        });
    }

    public function updateWindow(ProgramSurvey $survey, mixed $opensAt, mixed $closesAt): void
    {
        $opens = $this->parseDate($opensAt);
        $closes = $this->parseDate($closesAt);

        if ($opens !== null && $closes !== null && $closes->lt($opens)) {
            throw ValidationException::withMessages([
                'closes_at' => 'تاريخ الإغلاق يجب أن يكون بعد تاريخ الفتح.',
            ]);
        }

        $survey->update([
            'opens_at' => $opens,
            'closes_at' => $closes,
        ]);
    }

    /**
     * @return array{status: 'match'|'completed'|'missing', short_name: ?string, registration_id: ?int}
     */
    public function identify(ProgramSurvey $survey, string $nationalId, ?string $ip): array
    {
        $normalized = IdentityNumberService::normalize($nationalId);
        $attemptHash = SurveyNationalIdHasher::hash($normalized ?? trim($nationalId));
        $lookup = $normalized !== null
            ? IdentityNumberService::generateLookupHash($normalized)
            : hash('sha256', 'survey-miss');

        $registration = ProgramRegistration::query()
            ->where('training_program_id', $survey->training_program_id)
            ->whereIn('status', [RegistrationStatus::Approved->value, RegistrationStatus::Completed->value])
            ->whereHas('user', fn ($query) => $query->where('identity_number_lookup_hash', $lookup))
            ->first();

        SurveyAccessAttempt::query()->create([
            'program_survey_id' => $survey->id,
            'national_id_hash' => $attemptHash,
            'ip' => $ip,
            'matched' => $registration !== null,
        ]);

        if ($registration === null) {
            return ['status' => 'missing', 'short_name' => null, 'registration_id' => null];
        }

        if ($this->alreadyAnswered($survey, $registration)) {
            return ['status' => 'completed', 'short_name' => null, 'registration_id' => null];
        }

        $registration->loadMissing('user');

        return [
            'status' => 'match',
            'short_name' => $this->shortName($registration->user),
            'registration_id' => $registration->id,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $answers
     */
    public function submit(ProgramSurvey $survey, int $registrationId, array $answers): void
    {
        if (! $survey->isOpen()) {
            throw ValidationException::withMessages([
                'survey' => self::UNAVAILABLE_MESSAGE,
            ]);
        }

        $registration = ProgramRegistration::query()
            ->whereKey($registrationId)
            ->where('training_program_id', $survey->training_program_id)
            ->whereIn('status', [RegistrationStatus::Approved->value, RegistrationStatus::Completed->value])
            ->first();

        if ($registration === null) {
            throw ValidationException::withMessages([
                'survey' => self::NOT_FOUND_MESSAGE,
            ]);
        }

        if ($this->alreadyAnswered($survey, $registration)) {
            throw ValidationException::withMessages([
                'survey' => self::ALREADY_MESSAGE,
            ]);
        }

        $packed = $this->validateAnswers($survey, $answers);

        DB::transaction(function () use ($survey, $registration, $packed): void {
            $response = SurveyResponse::query()->create([
                'program_survey_id' => $survey->id,
                'registration_id' => $survey->is_anonymous ? null : $registration->id,
            ]);

            foreach ($packed as $questionId => $value) {
                SurveyAnswer::query()->create([
                    'survey_response_id' => $response->id,
                    'program_survey_question_id' => $questionId,
                    'value' => ['answer' => $value],
                ]);
            }

            if ($survey->is_anonymous) {
                DB::table('survey_responses')->where('id', $response->id)->update([
                    'created_at' => null,
                    'updated_at' => null,
                ]);
                SurveyCompletion::query()->create([
                    'program_survey_id' => $survey->id,
                    'registration_id' => $registration->id,
                ]);
            }
        });
    }

    /**
     * @return array{responses: int, eligible: int, rate: ?float, lines: list<string>, comparison: list<array{prompt: string, pre_average: ?float, post_average: ?float}>}
     */
    public function report(ProgramSurvey $survey): array
    {
        $eligible = $this->eligibleCount($survey);
        $responses = $this->responseCount($survey);

        return [
            'responses' => $responses,
            'eligible' => $eligible,
            'rate' => $eligible === 0 ? null : round(($responses / $eligible) * 100, 1),
            'lines' => $this->summaryLines($survey),
            'comparison' => $survey->type === SurveyType::Post
                ? $this->scaleComparison($survey->program)
                : [],
        ];
    }

    /**
     * @return list<array{prompt: string, pre_average: ?float, post_average: ?float}>
     */
    public function scaleComparison(TrainingProgram $program): array
    {
        $pre = $program->surveys()->where('type', SurveyType::Pre->value)->first();
        $post = $program->surveys()->where('type', SurveyType::Post->value)->first();
        if ($pre === null || $post === null) {
            return [];
        }

        $preQuestions = $pre->questions()->where('type', SurveyQuestionType::Scale->value)->orderBy('position')->get();
        $postQuestions = $post->questions()->where('type', SurveyQuestionType::Scale->value)->orderBy('position')->get();
        $rows = [];
        $count = min($preQuestions->count(), $postQuestions->count());

        for ($index = 0; $index < $count; $index++) {
            $rows[] = [
                'prompt' => $preQuestions[$index]->prompt,
                'pre_average' => $this->average($preQuestions[$index]),
                'post_average' => $this->average($postQuestions[$index]),
            ];
        }

        return $rows;
    }

    /**
     * Satisfaction rows are ordered by answer text, never by response id.
     *
     * @return Collection<int, SurveyResponse>
     */
    public function exportResponses(ProgramSurvey $survey): Collection
    {
        $responses = $survey->responses()->with('answers')->get();

        if (! $survey->is_anonymous) {
            return $responses->sortBy('created_at')->values();
        }

        return $responses->sortBy(function (SurveyResponse $response): string {
            return $response->answers
                ->sortBy('program_survey_question_id')
                ->map(fn (SurveyAnswer $answer): string => json_encode($answer->value['answer'] ?? null, JSON_UNESCAPED_UNICODE))
                ->implode('|');
        })->values();
    }

    public function responseCount(ProgramSurvey $survey): int
    {
        if ($survey->is_anonymous) {
            return $survey->completions()->count();
        }

        return $survey->responses()->count();
    }

    public function eligibleCount(ProgramSurvey $survey): int
    {
        return ProgramRegistration::query()
            ->where('training_program_id', $survey->training_program_id)
            ->whereIn('status', [RegistrationStatus::Approved->value, RegistrationStatus::Completed->value])
            ->count();
    }

    public function shortName(?User $user): string
    {
        $first = trim((string) ($user?->first_name ?? ''));
        $family = trim((string) ($user?->family_name ?? ''));
        $letter = $family === '' ? '' : mb_substr($family, 0, 1);

        if ($first === '') {
            return $letter === '' ? '' : $letter.'.';
        }

        return $letter === '' ? $first : $first.' '.$letter.'.';
    }

    /**
     * @return array{opens_at: ?Carbon, closes_at: ?Carbon}
     */
    public function defaultWindow(TrainingProgram $program, SurveyType $type): array
    {
        if ($program->start_date === null) {
            return ['opens_at' => null, 'closes_at' => null];
        }

        if ($type === SurveyType::Pre) {
            return [
                'opens_at' => $program->start_date->copy()->subDays(7)->startOfDay(),
                'closes_at' => $program->start_date->copy()->endOfDay(),
            ];
        }

        $anchor = $program->end_date ?? $program->start_date;
        $opens = $anchor->copy()->addDay()->startOfDay();

        return [
            'opens_at' => $opens,
            'closes_at' => $opens->copy()->addDays(6)->endOfDay(),
        ];
    }

    private function createFromTemplate(TrainingProgram $program, SurveyType $type, SurveyTemplate $template, bool $anonymous): ProgramSurvey
    {
        $survey = $this->makeSurvey($program, $type, $template->id, $anonymous);
        $template->loadMissing('questions');
        $this->writeQuestions($survey, $template->questions->map(fn ($question): array => [
            'type' => $question->type->value,
            'prompt' => $question->prompt,
            'options' => $question->options,
            'required' => $question->required,
            'scale_min_label' => $question->scale_min_label,
            'scale_max_label' => $question->scale_max_label,
        ])->all());

        return $survey->fresh('questions');
    }

    private function makeSurvey(TrainingProgram $program, SurveyType $type, ?int $templateId, bool $anonymous): ProgramSurvey
    {
        $window = $this->defaultWindow($program, $type);

        return ProgramSurvey::query()->create([
            'training_program_id' => $program->id,
            'type' => $type,
            'opens_at' => $window['opens_at'],
            'closes_at' => $window['closes_at'],
            'is_anonymous' => $anonymous,
            'source_template_id' => $templateId,
        ]);
    }

    private function copyQuestions(ProgramSurvey $from, ProgramSurvey $to): void
    {
        $from->load('questions');
        $this->writeQuestions($to, $from->questions->map(fn (ProgramSurveyQuestion $question): array => [
            'type' => $question->type->value,
            'prompt' => $question->prompt,
            'options' => $question->options,
            'required' => $question->required,
            'scale_min_label' => $question->scale_min_label,
            'scale_max_label' => $question->scale_max_label,
        ])->all());
    }

    /**
     * @param  list<array{type: string, prompt: string, options?: string|list<string>|null, required?: bool, scale_min_label?: ?string, scale_max_label?: ?string}>  $questions
     */
    private function writeQuestions(ProgramSurvey $survey, array $questions): void
    {
        $survey->questions()->delete();
        foreach (array_values($questions) as $position => $question) {
            $type = SurveyQuestionType::from((string) $question['type']);
            $options = $this->optionList($question['options'] ?? null);
            if (in_array($type, [SurveyQuestionType::Single, SurveyQuestionType::Multiple], true) && $options === []) {
                throw ValidationException::withMessages([
                    'questions' => 'أسئلة الاختيار تحتاج خيارًا واحدًا على الأقل.',
                ]);
            }

            $survey->questions()->create([
                'position' => $position + 1,
                'type' => $type,
                'prompt' => trim((string) $question['prompt']),
                'options' => $options === [] ? null : $options,
                'required' => (bool) ($question['required'] ?? true),
                'scale_min_label' => $type === SurveyQuestionType::Scale ? $this->optionalLabel($question['scale_min_label'] ?? null) : null,
                'scale_max_label' => $type === SurveyQuestionType::Scale ? $this->optionalLabel($question['scale_max_label'] ?? null) : null,
            ]);
        }
    }

    private function optionalLabel(mixed $label): ?string
    {
        $label = trim((string) $label);

        return $label === '' ? null : $label;
    }

    /**
     * @return list<string>
     */
    private function optionList(mixed $options): array
    {
        if (is_string($options)) {
            $options = preg_split('/\r\n|\r|\n/', $options) ?: [];
        }

        if (! is_array($options)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $option): string => trim((string) $option),
            $options,
        ), static fn (string $option): bool => $option !== ''));
    }

    private function sibling(ProgramSurvey $survey, SurveyType $type): ?ProgramSurvey
    {
        return ProgramSurvey::query()
            ->where('training_program_id', $survey->training_program_id)
            ->where('type', $type->value)
            ->first();
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    private function alreadyAnswered(ProgramSurvey $survey, ProgramRegistration $registration): bool
    {
        if ($survey->is_anonymous) {
            return $survey->completions()->where('registration_id', $registration->id)->exists();
        }

        return $survey->responses()->where('registration_id', $registration->id)->exists();
    }

    /**
     * @param  array<int|string, mixed>  $answers
     * @return array<int, mixed>
     */
    private function validateAnswers(ProgramSurvey $survey, array $answers): array
    {
        $packed = [];
        $errors = [];

        foreach ($survey->questions()->orderBy('position')->get() as $question) {
            $raw = $answers[$question->id] ?? $answers[(string) $question->id] ?? null;
            $value = $this->normalizeAnswer($question, $raw);
            if ($value === null) {
                if ($question->required) {
                    $errors['answers.'.$question->id] = 'أجب عن السؤال: '.$question->prompt;
                }

                continue;
            }

            $packed[$question->id] = $value;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $packed;
    }

    private function normalizeAnswer(ProgramSurveyQuestion $question, mixed $raw): mixed
    {
        return match ($question->type) {
            SurveyQuestionType::Scale => $this->scaleAnswer($raw),
            SurveyQuestionType::Single => $this->singleAnswer($question, $raw),
            SurveyQuestionType::Multiple => $this->multipleAnswer($question, $raw),
            SurveyQuestionType::Text => $this->textAnswer($raw),
        };
    }

    private function scaleAnswer(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if ($value === false || $value < 1 || $value > 5) {
            return null;
        }

        return $value;
    }

    private function singleAnswer(ProgramSurveyQuestion $question, mixed $raw): ?string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        return in_array($value, $question->options ?? [], true) ? $value : null;
    }

    /**
     * @return list<string>|null
     */
    private function multipleAnswer(ProgramSurveyQuestion $question, mixed $raw): ?array
    {
        $values = is_array($raw) ? $raw : [$raw];
        $values = array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $values,
        ), static fn (string $value): bool => $value !== ''));

        if ($values === []) {
            return null;
        }

        $allowed = $question->options ?? [];
        foreach ($values as $value) {
            if (! in_array($value, $allowed, true)) {
                return null;
            }
        }

        return $values;
    }

    private function textAnswer(mixed $raw): ?string
    {
        $value = trim((string) $raw);

        return $value === '' ? null : mb_substr($value, 0, 5000);
    }

    private function average(ProgramSurveyQuestion $question): ?float
    {
        $values = SurveyAnswer::query()
            ->where('program_survey_question_id', $question->id)
            ->get()
            ->map(fn (SurveyAnswer $answer): mixed => $answer->value['answer'] ?? null)
            ->filter(fn (mixed $value): bool => is_int($value) || (is_string($value) && is_numeric($value)))
            ->map(fn (mixed $value): float => (float) $value);

        if ($values->isEmpty()) {
            return null;
        }

        return round($values->avg(), 2);
    }

    /**
     * @return list<string>
     */
    private function summaryLines(ProgramSurvey $survey): array
    {
        $lines = [];
        foreach ($survey->questions()->orderBy('position')->get() as $question) {
            $values = SurveyAnswer::query()
                ->where('program_survey_question_id', $question->id)
                ->get()
                ->map(fn (SurveyAnswer $answer): mixed => $answer->value['answer'] ?? null);

            if ($question->type === SurveyQuestionType::Scale) {
                $average = $this->average($question);
                $lines[] = $question->prompt.': '.($average === null ? '—' : $average);
            } elseif ($question->type === SurveyQuestionType::Text) {
                $lines[] = $question->prompt.': '.$values->filter()->count().' إجابة';
            } else {
                $counts = [];
                foreach ($values as $value) {
                    foreach ((array) $value as $choice) {
                        $choice = (string) $choice;
                        $counts[$choice] = ($counts[$choice] ?? 0) + 1;
                    }
                }
                $parts = [];
                foreach ($counts as $choice => $count) {
                    $parts[] = $choice.' ('.$count.')';
                }
                $lines[] = $question->prompt.': '.($parts === [] ? '—' : implode('، ', $parts));
            }
        }

        return $lines;
    }
}
