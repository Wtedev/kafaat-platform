<?php

namespace App\Services\StaffUi;

use App\Enums\ProgramStatus;
use App\Enums\StaffUi\StaffRegistrationAvailability;
use App\Models\TrainingProgram;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for staff-UI program / registration status labels.
 *
 * Registration availability rules (first match wins):
 * 1. learning_path_id set → Path («التسجيل عبر المسار»).
 * 2. Manually closed (reserved for function 5) → ManualClosed.
 *    Hook: {@see self::isManuallyClosed()} — always false until a real column exists.
 * 3. registration_start is a future date → NotStarted («لم يبدأ»).
 *    Null registration_start means the window has already started (open from publish /
 *    whenever the program is otherwise eligible) — same as TrainingProgram::isRegistrationOpen().
 * 4. registration_end is a past date → Closed («مغلق»).
 *    Null registration_end means the window has no end date from this field — same as
 *    isRegistrationOpen(). Note: public registration does NOT close on end_date alone;
 *    end_date is not used here (program end is separate from the registration window).
 * 5. capacity is set AND approved_registrations_count >= capacity → Full («مكتمل العدد»).
 *    Count uses approved only (not pending). Public ProgramRegistrationService still accepts
 *    new pending applications when full; capacity is enforced on approval. Staff list shows
 *    Full as an operational signal — do not change public behavior from this service.
 * 6. Otherwise → Open («مفتوح»).
 *
 * Program status badge = ProgramStatus (draft / published / archived), independent of the
 * registration window.
 *
 * Publication line:
 * - Draft → «مسودة»
 * - Published with published_at in the future → «مجدول للنشر في {date}»
 * - Published (live) with published_at → «نُشر منذ {relative}»
 * - Published with null published_at → «نُشر منذ …» falls back to created_at relative
 * - Archived → badge handles status; line uses published_at relative when present, else «مسودة»
 */
final class StaffProgramStatus
{
    public function registrationAvailability(TrainingProgram $program, ?Carbon $today = null): StaffRegistrationAvailability
    {
        $today = ($today ?? Carbon::today())->startOfDay();

        if ($program->learning_path_id !== null) {
            return StaffRegistrationAvailability::Path;
        }

        if ($this->isManuallyClosed($program)) {
            return StaffRegistrationAvailability::ManualClosed;
        }

        if ($program->registration_start !== null && $program->registration_start->copy()->startOfDay()->gt($today)) {
            return StaffRegistrationAvailability::NotStarted;
        }

        if ($program->registration_end !== null && $program->registration_end->copy()->startOfDay()->lt($today)) {
            return StaffRegistrationAvailability::Closed;
        }

        if ($this->isCapacityFull($program)) {
            return StaffRegistrationAvailability::Full;
        }

        return StaffRegistrationAvailability::Open;
    }

    public function programStatus(TrainingProgram $program): ProgramStatus
    {
        return $program->status instanceof ProgramStatus
            ? $program->status
            : ProgramStatus::Draft;
    }

    public function programStatusLabel(TrainingProgram $program): string
    {
        return $this->programStatus($program)->label();
    }

    public function programStatusTone(TrainingProgram $program): string
    {
        return match ($this->programStatus($program)) {
            ProgramStatus::Published => 'success',
            ProgramStatus::Draft => 'warning',
            ProgramStatus::Archived => 'muted',
        };
    }

    /**
     * Publication / schedule line under the card title.
     */
    public function publicationLabel(TrainingProgram $program, ?Carbon $now = null): string
    {
        $now = $now ?? Carbon::now();
        $status = $this->programStatus($program);

        if ($status === ProgramStatus::Draft) {
            return 'مسودة';
        }

        if ($status === ProgramStatus::Published
            && $program->published_at !== null
            && $program->published_at->gt($now)) {
            return 'مجدول للنشر في '.ar_date($program->published_at, 'd MMM y');
        }

        if ($status === ProgramStatus::Archived && $program->published_at === null) {
            return 'مسودة';
        }

        $anchor = $program->published_at ?? $program->created_at;
        if ($anchor === null) {
            return 'مسودة';
        }

        return 'نُشر '.ar_diff_for_humans($anchor);
    }

    /**
     * Reserved for function 5 (manual registration close).
     * Always false until a dedicated column/flag is approved and wired here.
     * Do not invent a column in this service.
     */
    public function isManuallyClosed(TrainingProgram $program): bool
    {
        return false;
    }

    /**
     * Approved count vs capacity. Prefer withCount alias when present to avoid N+1.
     */
    public function isCapacityFull(TrainingProgram $program): bool
    {
        if ($program->capacity === null) {
            return false;
        }

        $approved = $program->approved_registrations_count
            ?? $program->approvedRegistrationsCount();

        return (int) $approved >= (int) $program->capacity;
    }

    public function approvedCount(TrainingProgram $program): int
    {
        return (int) ($program->approved_registrations_count
            ?? $program->approvedRegistrationsCount());
    }

    public function pendingCount(TrainingProgram $program): int
    {
        return (int) ($program->pending_registrations_count
            ?? $program->registrations()->where('status', 'pending')->count());
    }
}
