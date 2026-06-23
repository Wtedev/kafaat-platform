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
            self::Trainee => 'bg-[#e9eff6] text-[#335483] ring-1 ring-[#c5d4e4]',
            self::Volunteer => 'bg-[#e6f5f6] text-brand-secondary ring-1 ring-[#b8e0e2]',
            self::Beneficiary => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
        };
    }
}
