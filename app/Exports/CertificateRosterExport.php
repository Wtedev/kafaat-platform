<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class CertificateRosterExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings
{
    /**
     * @param  Collection<int, array<int, string|null>>  $rows
     */
    public function __construct(private readonly Collection $rows) {}

    public function headings(): array
    {
        return [
            'الاسم',
            'الحضور',
            'الدرجة',
            'الأحقية والسبب',
            'رقم الشهادة',
            'رابط التحقق',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $rowCount = max(1, $this->rows->count() + 1);
                $sheet->setRightToLeft(true);
                $sheet->freezePane('A2');
                $sheet->getStyle('A1:F1')->getFont()->setBold(true);
                $sheet->getStyle("A1:F{$rowCount}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
