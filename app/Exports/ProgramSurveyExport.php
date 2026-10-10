<?php

namespace App\Exports;

use App\Models\ProgramSurvey;
use App\Models\ProgramSurveyQuestion;
use App\Models\SurveyResponse;
use App\Services\Surveys\ProgramSurveyService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProgramSurveyExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly ProgramSurvey $survey) {}

    public function headings(): array
    {
        $headings = [];
        if (! $this->survey->is_anonymous) {
            $headings[] = 'رقم التسجيل';
        }

        foreach ($this->questions() as $question) {
            $headings[] = $question->prompt;
        }

        return $headings;
    }

    public function collection(): Collection
    {
        $questions = $this->questions();
        $responses = app(ProgramSurveyService::class)->exportResponses($this->survey);

        return $responses->map(function (SurveyResponse $response) use ($questions): array {
            $row = [];
            if (! $this->survey->is_anonymous) {
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

    /**
     * @return Collection<int, ProgramSurveyQuestion>
     */
    private function questions(): Collection
    {
        return $this->survey->questions()->orderBy('position')->get();
    }
}
