<?php

namespace App\Enums;

/**
 * Beneficiary portal membership label (admin-set on profile; null defaults to مستفيد).
 */
enum MembershipType: string
{
    case Trainee = 'trainee';
    case Volunteer = 'volunteer';
    case Beneficiary = 'beneficiary';

    public function label(): string
    {
        return match ($this) {
            self::Trainee => 'متدرب',
            self::Volunteer => 'متطوع',
            self::Beneficiary => 'مستفيد',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Trainee => 'مسجّل في مسارات أو برامج تدريبية',
            self::Volunteer => 'يشارك في فرص تطوعية',
            self::Beneficiary => 'عضو في منصة كفاءات',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Trainee => 'bg-[#EAF2FA] text-[#253B5B] ring-1 ring-[#c5ddef]',
            self::Volunteer => 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200',
            self::Beneficiary => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
        };
    }
}
