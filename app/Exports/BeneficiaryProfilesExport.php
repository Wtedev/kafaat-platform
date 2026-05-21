<?php

namespace App\Exports;

use App\Support\Exports\BeneficiaryProfileExportColumns;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BeneficiaryProfilesExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    /**
     * @param  Collection<int, \App\Models\Profile>  $profiles
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
}
