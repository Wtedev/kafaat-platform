<?php

namespace App\Exports;

use App\Models\ProgramRegistration;
use App\Models\User;
use App\Support\Exports\ProgramRegistrationExportColumns;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ProgramRegistrationsExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithEvents, WithHeadings, WithStrictNullComparison
{
    /**
     * @param  Collection<int, ProgramRegistration>  $registrations
     * @param  list<string>  $columnKeys
     */
    public function __construct(
        private readonly Collection $registrations,
        private readonly array $columnKeys,
        private readonly ?User $actor = null,
    ) {}

    public function headings(): array
    {
        return ProgramRegistrationExportColumns::labelsForKeys($this->columnKeys, $this->actor);
    }

    public function collection(): Collection
    {
        return $this->registrations->map(function (ProgramRegistration $registration): array {
            $row = [];
            foreach ($this->columnKeys as $key) {
                $row[] = ProgramRegistrationExportColumns::resolve($registration, $key, $this->actor);
            }

            return $row;
        });
    }

    public function columnFormats(): array
    {
        $formats = [];
        $textKeys = ProgramRegistrationExportColumns::textColumnKeys();

        foreach ($this->columnKeys as $index => $key) {
            if (in_array($key, $textKeys, true)) {
                $formats[Coordinate::stringFromColumnIndex($index + 1)] = NumberFormat::FORMAT_TEXT;
            }
        }

        return $formats;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $colCount = max(1, count($this->columnKeys));
                $rowCount = max(1, $this->registrations->count() + 1);
                $lastCol = Coordinate::stringFromColumnIndex($colCount);

                $sheet->setRightToLeft(true);
                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:{$lastCol}1");

                $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
                $sheet->getStyle("A1:{$lastCol}{$rowCount}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $textKeys = ProgramRegistrationExportColumns::textColumnKeys();
                foreach ($this->columnKeys as $index => $key) {
                    if (! in_array($key, $textKeys, true)) {
                        continue;
                    }

                    $col = Coordinate::stringFromColumnIndex($index + 1);
                    for ($row = 2; $row <= $rowCount; $row++) {
                        $cell = $sheet->getCell("{$col}{$row}");
                        $value = $cell->getValue();
                        if ($value === null || $value === '') {
                            continue;
                        }
                        $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
                    }
                }
            },
        ];
    }
}
