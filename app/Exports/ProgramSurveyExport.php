<?php

namespace App\Exports;

use App\Models\ProgramSurvey;
use App\Models\ProgramSurveyQuestion;
use App\Models\SurveyResponse;
use App\Services\Surveys\ProgramSurveyService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProgramSurveyExport implements FromCollection, WithHeadings, WithTitle
{
    /**
     * @param  list<string>|null  $columnKeys
     */
    public function __construct(
        private readonly ProgramSurvey $survey,
        private readonly ?array $columnKeys = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function columnOptions(ProgramSurvey $survey): array
    {
        $options = [];
        if (! $survey->is_anonymous) {
            $options['name'] = 'الاسم';
            $options['registration'] = 'رقم التسجيل';
        }

        foreach ($survey->questions()->orderBy('position')->get() as $question) {
            $label = trim($question->prompt);
            if (mb_strlen($label) > 80) {
                $label = mb_substr($label, 0, 77).'...';
            }
            $options['q:'.$question->id] = $label !== '' ? $label : 'سؤال '.$question->position;
        }

        return $options;
    }

    public function title(): string
    {
        return 'الاستجابات';
    }

    public function headings(): array
    {
        $headings = [];
        if ($this->include('name')) {
            $headings[] = 'الاسم';
        }
        if ($this->include('registration')) {
            $headings[] = 'رقم التسجيل';
        }

        foreach ($this->selectedQuestions() as $question) {
            $headings[] = $question->prompt;
        }

        return $headings;
    }

    public function collection(): Collection
    {
        $questions = $this->selectedQuestions();
        $responses = app(ProgramSurveyService::class)->exportResponses($this->survey);
        if ($this->include('name') || $this->include('registration')) {
            $responses->load('registration.user');
        }

        return $responses->map(function (SurveyResponse $response) use ($questions): array {
            $row = [];
            if ($this->include('name')) {
                $row[] = $response->registration?->user?->fullName() ?? '';
            }
            if ($this->include('registration')) {
                $row[] = $response->registration_id;
            }

            $byQuestion = $response->answers->keyBy('program_survey_question_id');
            foreach ($questions as $question) {
                $value = $byQuestion->get($question->id)?->value['answer'] ?? null;
                $row[] = is_array($value) ? implode('، ', $value) : $value;
            }

            return $row;
        });
    }

    private function include(string $key): bool
    {
        if ($this->survey->is_anonymous) {
            return false;
        }

        return $this->columnKeys === null || in_array($key, $this->columnKeys, true);
    }

    /**
     * @return Collection<int, ProgramSurveyQuestion>
     */
    private function selectedQuestions(): Collection
    {
        $questions = $this->survey->questions()->orderBy('position')->get();
        if ($this->columnKeys === null) {
            return $questions;
        }

        $ids = collect($this->columnKeys)
            ->filter(fn (string $key): bool => str_starts_with($key, 'q:'))
            ->map(fn (string $key): int => (int) substr($key, 2));

        return $questions->whereIn('id', $ids->all())->values();
    }
}
