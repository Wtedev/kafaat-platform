<?php

namespace App\Exports;

use App\Models\ProgramAttendanceLink;
use App\Models\ProgramAttendanceMark;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProgramAttendanceMarkExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly ProgramAttendanceLink $link) {}

    public function headings(): array
    {
        return ['الاسم', 'الوقت', 'المصدر'];
    }

    public function collection(): Collection
    {
        return $this->link->marks()
            ->with('registration.user')
            ->orderBy('attended_at')
            ->get()
            ->map(fn (ProgramAttendanceMark $mark): array => [
                $mark->registration?->user?->fullName(),
                $mark->riyadhLabel(),
                $mark->source->label(),
            ]);
    }
}
