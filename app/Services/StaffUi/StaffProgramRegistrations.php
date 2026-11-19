<?php

namespace App\Services\StaffUi;

use App\Enums\IdentityCategory;
use App\Enums\ProfileGender;
use App\Enums\ProgramActivityAction;
use App\Enums\RegistrationStatus;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Notifications\ProgramRegistrationApproved;
use App\Notifications\ProgramRegistrationRejected;
use App\Services\Audit\AuditLogger;
use App\Services\ProgramActivity\ProgramActivityLog;
use App\Support\ArabicText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The registrations tab: filtered registrant lists, manual close of the
 * registration window, and previews of the acceptance and rejection emails.
 */
final class StaffProgramRegistrations
{
    public const STATUS_OPTIONS = [
        '' => 'الكل',
        'pending' => 'بانتظار القبول',
        'approved' => 'مقبول',
        'rejected' => 'مرفوض',
        'cancelled' => 'ملغي',
    ];

    public const NATIONALITY_OPTIONS = [
        '' => 'كل الجنسيات',
        'saudi' => 'سعودي',
        'non_saudi' => 'غير سعودي',
        'unspecified' => 'غير محدد',
    ];

    public const GENDER_OPTIONS = [
        '' => 'كل الأجناس',
        'male' => 'ذكر',
        'female' => 'أنثى',
        'unspecified' => 'غير محدد',
    ];

    public const AGE_OPTIONS = [
        '' => 'كل الأعمار',
        'under_18' => 'أقل من 18',
        '18_24' => '18–24',
        '25_34' => '25–34',
        '35_plus' => '35 فأكثر',
        'unspecified' => 'غير محدد',
    ];

    public function __construct(
        private readonly ProgramActivityLog $activity,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Clean filter values from a request; anything unknown is dropped.
     *
     * @param  array<string, mixed>  $input
     * @return array{q: string, status: string, nationality: string, gender: string, age: string}
     */
    public function filters(array $input): array
    {
        $pick = static fn (string $key, array $options): string => array_key_exists((string) ($input[$key] ?? ''), $options)
            ? (string) ($input[$key] ?? '')
            : '';

        return [
            'q' => mb_substr(trim((string) ($input['q'] ?? '')), 0, 100),
            'status' => $pick('status', self::STATUS_OPTIONS),
            'nationality' => $pick('nationality', self::NATIONALITY_OPTIONS),
            'gender' => $pick('gender', self::GENDER_OPTIONS),
            'age' => $pick('age', self::AGE_OPTIONS),
        ];
    }

    /**
     * @param  array{q: string, status: string, nationality: string, gender: string, age: string}  $filters
     * @return Builder<ProgramRegistration>
     */
    public function query(TrainingProgram $program, array $filters): Builder
    {
        $query = ProgramRegistration::query()->where('training_program_id', $program->getKey());

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['q'] !== '') {
            $like = '%'.ArabicText::fold($filters['q']).'%';
            $plain = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->whereHas('user', function (Builder $user) use ($like, $plain): void {
                $user->where(function (Builder $match) use ($like, $plain): void {
                    foreach (['name', 'first_name', 'father_name', 'grandfather_name', 'family_name'] as $column) {
                        $match->orWhereRaw(ArabicText::sqlFoldColumn($column).' LIKE ?', [$like]);
                    }
                    $match->orWhere('email', 'like', $plain)->orWhere('phone', 'like', $plain);
                });
            });
        }

        match ($filters['nationality']) {
            'saudi' => $query->whereHas('user', fn (Builder $u) => $u->where('identity_category', IdentityCategory::Saudi->value)),
            'non_saudi' => $query->whereHas('user', fn (Builder $u) => $u->where('identity_category', IdentityCategory::Resident->value)),
            'unspecified' => $query->whereHas('user', fn (Builder $u) => $u->whereNull('identity_category')),
            default => null,
        };

        match ($filters['gender']) {
            'male', 'female' => $query->whereHas('user.profile', fn (Builder $p) => $p->where('gender', $filters['gender'])),
            'unspecified' => $query->where(fn (Builder $q) => $q
                ->whereDoesntHave('user.profile')
                ->orWhereHas('user.profile', fn (Builder $p) => $p->whereNull('gender'))),
            default => null,
        };

        if ($filters['age'] === 'unspecified') {
            $query->where(fn (Builder $q) => $q
                ->whereDoesntHave('user.profile')
                ->orWhereHas('user.profile', fn (Builder $p) => $p->whereNull('birth_date')));
        } elseif ($filters['age'] !== '') {
            [$min, $max] = match ($filters['age']) {
                'under_18' => [null, 17],
                '18_24' => [18, 24],
                '25_34' => [25, 34],
                default => [35, null],
            };
            $today = Carbon::today();
            $query->whereHas('user.profile', function (Builder $p) use ($min, $max, $today): void {
                if ($min !== null) {
                    $p->whereDate('birth_date', '<=', $today->copy()->subYears($min)->toDateString());
                }
                if ($max !== null) {
                    $p->whereDate('birth_date', '>', $today->copy()->subYears($max + 1)->toDateString());
                }
            });
        }

        return $query;
    }

    /**
     * Registration counts per status, for the filter chips.
     *
     * @return array<string, int>
     */
    public function statusCounts(TrainingProgram $program): array
    {
        $counts = ProgramRegistration::query()
            ->where('training_program_id', $program->getKey())
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $out = ['' => (int) $counts->sum()];
        foreach (array_keys(self::STATUS_OPTIONS) as $key) {
            if ($key !== '') {
                $out[$key] = (int) ($counts[$key] ?? 0);
            }
        }
        $out['approved'] += (int) ($counts[RegistrationStatus::Completed->value] ?? 0);

        return $out;
    }

    public function closeRegistration(User $actor, TrainingProgram $program): void
    {
        if ($program->isRegistrationManuallyClosed()) {
            return;
        }

        $program->forceFill(['registration_closed_at' => now()])->save();
        $this->auditLogger->record($actor, 'training_program.registration_closed', resource: $program, metadata: ['source' => 'staff-ui']);
        $this->activity->record($program, ProgramActivityAction::RegistrationWindowClosed, actor: $actor);
    }

    public function reopenRegistration(User $actor, TrainingProgram $program): void
    {
        if (! $program->isRegistrationManuallyClosed()) {
            return;
        }

        $program->forceFill(['registration_closed_at' => null])->save();
        $this->auditLogger->record($actor, 'training_program.registration_reopened', resource: $program, metadata: ['source' => 'staff-ui']);
        $this->activity->record($program, ProgramActivityAction::RegistrationWindowReopened, actor: $actor);
    }

    /**
     * The acceptance or rejection email exactly as it would reach this registrant.
     */
    public function emailPreview(ProgramRegistration $registration, string $kind, ?string $reason = null): string
    {
        $registration->loadMissing(['user.profile', 'trainingProgram']);

        if ($kind === 'reject') {
            $copy = $registration->replicate();
            $copy->setRelations($registration->getRelations());
            $copy->rejected_reason = filled($reason) ? $reason : null;

            return (string) (new ProgramRegistrationRejected($copy))->toMail($registration->user)->render();
        }

        return (string) (new ProgramRegistrationApproved($registration))->toMail($registration->user)->render();
    }

    /**
     * Age in whole years from the profile birth date, or null.
     */
    public static function age(?ProgramRegistration $registration): ?int
    {
        $birth = $registration?->user?->profile?->birth_date;

        return $birth instanceof Carbon ? (int) $birth->diffInYears(Carbon::today()) : null;
    }

    public static function genderLabel(?ProgramRegistration $registration): string
    {
        $gender = $registration?->user?->profile?->gender;

        return $gender instanceof ProfileGender ? $gender->label() : 'غير محدد';
    }

    public static function nationalityLabel(?ProgramRegistration $registration): string
    {
        return match ($registration?->user?->identity_category) {
            IdentityCategory::Saudi => 'سعودي',
            IdentityCategory::Resident => 'غير سعودي',
            default => 'غير محدد',
        };
    }
}
