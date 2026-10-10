<?php

namespace App\Livewire\Attendance;

use App\Enums\RegistrationStatus;
use App\Models\ProgramAttendanceLink;
use App\Models\ProgramRegistration;
use App\Services\Attendance\ProgramAttendanceLinkService;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TrainerDesk extends Component
{
    #[Locked]
    public string $token = '';

    public string $tab = 'live';

    public string $search = '';

    public ?string $notice = null;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->link();
    }

    public function openWindow(ProgramAttendanceLinkService $service): void
    {
        $this->notice = null;

        try {
            $service->open($this->link());
        } catch (ValidationException $exception) {
            $this->notice = collect($exception->errors())->flatten()->first();
        }
    }

    public function closeWindow(ProgramAttendanceLinkService $service): void
    {
        $this->notice = null;
        $service->close($this->link());
    }

    public function mark(int $registrationId, ProgramAttendanceLinkService $service): void
    {
        $this->notice = null;
        $link = $this->link();
        $registration = ProgramRegistration::query()
            ->whereKey($registrationId)
            ->where('training_program_id', $link->training_program_id)
            ->first();

        if ($registration === null) {
            $this->notice = ProgramAttendanceLinkService::NOT_APPROVED_MESSAGE;

            return;
        }

        try {
            $service->markManual($link, $registration);
        } catch (ValidationException $exception) {
            $this->notice = collect($exception->errors())->flatten()->first();
        }
    }

    public function render(): View
    {
        $link = $this->link()->load('program');
        $approved = ProgramRegistration::query()
            ->where('training_program_id', $link->training_program_id)
            ->where('status', RegistrationStatus::Approved->value)
            ->with('user')
            ->get()
            ->sortBy(fn (ProgramRegistration $registration): string => $registration->user?->fullName() ?? '');

        $term = trim($this->search);
        if ($term !== '') {
            $approved = $approved->filter(
                fn (ProgramRegistration $registration): bool => str_contains($registration->user?->fullName() ?? '', $term)
            );
        }

        $marks = $link->marks()->with('registration.user')->get()->keyBy('program_registration_id');
        $recentMarks = $marks
            ->filter(fn ($mark): bool => $mark->attended_at->greaterThan(now()->subSeconds(4)))
            ->sortByDesc(fn ($mark) => $mark->attended_at->getTimestamp())
            ->values();

        return view('livewire.attendance.trainer-desk', [
            'link' => $link,
            'programTitle' => $link->program?->title,
            'approved' => $approved,
            'marks' => $marks,
            'recentMarks' => $recentMarks,
            'presentCount' => $link->marks()->count(),
            'approvedCount' => ProgramRegistration::query()
                ->where('training_program_id', $link->training_program_id)
                ->where('status', RegistrationStatus::Approved->value)
                ->count(),
        ]);
    }

    private function link(): ProgramAttendanceLink
    {
        return ProgramAttendanceLink::query()->where('token', $this->token)->firstOrFail();
    }
}
