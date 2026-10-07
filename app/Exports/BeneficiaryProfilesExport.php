<?php

namespace App\Exports;

use App\Models\Profile;
use App\Support\Exports\BeneficiaryProfileExportColumns;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

class BeneficiaryProfilesExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings
{
    /**
     * @param  Collection<int, Profile>  $profiles
     * @param  list<string>  $columnKeys
     */
    public function __construct(
        private readonly Collection $profiles,
        private readonly array $columnKeys,
    ) {}

    public function headings(): array
    {
        return BeneficiaryProfileExportColumns::labelsForKeys($this->columnKeys);
    }

    public function collection(): Collection
    {
        return $this->profiles->map(function ($profile): array {
            $row = [];
            foreach ($this->columnKeys as $key) {
                $row[] = BeneficiaryProfileExportColumns::resolve($profile, $key);
            }

            return $row;
        });
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $event->sheet->getDelegate()->setRightToLeft(true);
            },
        ];
    }
}
