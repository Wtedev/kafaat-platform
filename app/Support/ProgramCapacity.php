<?php

namespace App\Support;

use App\Enums\ProfileGender;
use App\Enums\RegistrationStatus;
use App\Exceptions\ProgramCapacityExceededException;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ProgramCapacity
{
    public static function usesPerGender(TrainingProgram $program): bool
    {
        return $program->capacity_male !== null || $program->capacity_female !== null;
    }

    public static function approvedCount(TrainingProgram $program, ?ProfileGender $gender = null): int
    {
        $query = $program->registrations()->where('status', RegistrationStatus::Approved->value);

        if ($gender instanceof ProfileGender) {
            $query->whereHas('user.profile', function (Builder $profile) use ($gender): void {
                $profile->where('gender', $gender->value);
            });
        }

        return $query->count();
    }

    public static function isFull(TrainingProgram $program, ?ProfileGender $gender): bool
    {
        if (self::usesPerGender($program)) {
            $limit = match ($gender) {
                ProfileGender::Male => $program->capacity_male,
                ProfileGender::Female => $program->capacity_female,
                default => null,
            };

            if ($limit === null || ! $gender instanceof ProfileGender) {
                return false;
            }

            return self::approvedCount($program, $gender) >= $limit;
        }

        if ($program->capacity === null) {
            return false;
        }

        return self::approvedCount($program) >= $program->capacity;
    }

    public static function fullMessage(TrainingProgram $program, ?ProfileGender $gender): ?string
    {
        if (! self::usesPerGender($program) || ! $gender instanceof ProfileGender || ! self::isFull($program, $gender)) {
            return null;
        }

        return ProgramAcceptanceConditions::genderCapacityFullMessage($gender->value);
    }

    public static function assertAvailable(TrainingProgram $program, User $user): void
    {
        $gender = $user->profile?->gender;

        if (self::isFull($program, $gender instanceof ProfileGender ? $gender : null)) {
            throw new ProgramCapacityExceededException;
        }
    }
}
