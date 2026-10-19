<?php

namespace App\Exports;

use App\Models\ProgramSurvey;
use App\Models\SurveyCompletion;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProgramSurveyCompletersExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private readonly ProgramSurvey $survey) {}

    public function title(): string
    {
        return 'من أكملوا';
    }

    public function headings(): array
    {
        return ['الاسم'];
    }

    public function collection(): Collection
    {
        return SurveyCompletion::query()
            ->where('program_survey_id', $this->survey->id)
            ->with('registration.user')
            ->get()
            ->map(fn (SurveyCompletion $completion): array => [
                $completion->registration?->user?->fullName() ?? '',
            ])
            ->sortBy(fn (array $row): string => $row[0])
            ->values();
    }
}
