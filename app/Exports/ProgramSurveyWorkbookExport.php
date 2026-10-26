<?php

namespace App\Exports;

use App\Models\ProgramSurvey;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProgramSurveyWorkbookExport implements WithMultipleSheets
{
    /**
     * @param  list<string>  $columnKeys
     */
    public function __construct(
        private readonly ProgramSurvey $survey,
        private readonly array $columnKeys,
        private readonly bool $includeCompleters,
    ) {}

    public function sheets(): array
    {
        $sheets = [
            new ProgramSurveyExport($this->survey, $this->columnKeys),
        ];

        if ($this->includeCompleters) {
            $sheets[] = new ProgramSurveyCompletersExport($this->survey);
        }

        return $sheets;
    }
}
