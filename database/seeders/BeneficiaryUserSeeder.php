<?php

namespace Database\Seeders;

use App\Enums\MembershipType;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 150 portal users for registration/certificate demos. Emails use @seed.kafaat.org.sa so
 * {@see CleanDemoDataSeeder} can purge them without touching staff @kafaat.org.sa accounts.
 */
class BeneficiaryUserSeeder extends Seeder
{
    private const PASSWORD = 'password';

    private const EMAIL_DOMAIN = 'seed.kafaat.org.sa';

    /** @var list<string> */
    private const FIRST_NAMES = [
        'نورة', 'سارة', 'لينا', 'هند', 'دانة', 'أمل', 'شهد', 'رنا', 'مها', 'هيفاء',
        'غادة', 'منى', 'ريم', 'لطيفة', 'تركي', 'فهد', 'سلمان', 'عمر', 'ماجد', 'خالد',
        'ياسر', 'بندر', 'سعد', 'عادل', 'فيصل', 'عبدالرحمن', 'سعود', 'نايف', 'مشعل', 'طلال',
    ];

    /** @var list<string> */
    private const LAST_NAMES = [
        'العتيبي', 'الدوسري', 'القحطاني', 'الشمري', 'الزهراني', 'الغامدي', 'الحربي', 'السبيعي',
        'الرشيد', 'العنزي', 'الفيفي', 'البقمي', 'العمري', 'الدخيل', 'المطيري', 'السهلي',
        'الشهري', 'القرني', 'المالكي', 'الحميضان', 'التويجري', 'السعوي', 'القصير', 'المشيقح',
    ];

    /** @var list<string> */
    private const CITIES = ['الرياض', 'جدة', 'الدمام', 'مكة المكرمة', 'المدينة المنورة', 'أبها', 'بريدة', 'حائل'];

    public function run(): void
    {
        for ($i = 1; $i <= 150; $i++) {
            $email = sprintf('beneficiary.%03d@%s', $i, self::EMAIL_DOMAIN);
            $name = $this->arabicNameForIndex($i);
            $phone = $this->saudiMobileForIndex($i);

            [$membershipType, $badges] = $this->membershipProfileForIndex($i);

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(self::PASSWORD),
                    'phone' => $phone,
                    'role_type' => match ($membershipType) {
                        MembershipType::Trainee => 'trainee',
                        MembershipType::Volunteer => 'volunteer',
                        default => 'beneficiary',
                    },
                    'is_active' => true,
                ]
            );

            $spatieRoles = $this->spatieRolesFromProfile($membershipType, $badges);
            $user->syncRoles($spatieRoles);

            Profile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'membership_type' => $membershipType,
                    'membership_badges' => $badges,
                    'city' => self::CITIES[$i % count(self::CITIES)],
                    'gender' => ($i % 2 === 0) ? 'female' : 'male',
                    'bio' => 'مستفيد من منصة كفاءات — حساب تجريبي للاختبار.',
                ]
            );
        }

        $this->command?->info('  BeneficiaryUserSeeder: 150 users at beneficiary.*@'.self::EMAIL_DOMAIN);
    }

    private function arabicNameForIndex(int $i): string
    {
        $first = self::FIRST_NAMES[$i % count(self::FIRST_NAMES)];
        $last = self::LAST_NAMES[($i * 7) % count(self::LAST_NAMES)];

        return $first.' '.$last;
    }

    private function saudiMobileForIndex(int $i): string
    {
        $suffix = str_pad((string) (($i * 7919) % 10_000_000), 7, '0', STR_PAD_LEFT);

        return '05'.$suffix;
    }

    /**
     * @return array{0: MembershipType, 1: array<string>|null}
     */
    private function membershipProfileForIndex(int $i): array
    {
        $mod = $i % 7;

        return match ($mod) {
            0 => [MembershipType::Trainee, ['trainee']],
            1 => [MembershipType::Volunteer, ['volunteer']],
            2 => [MembershipType::Beneficiary, null],
            3 => [MembershipType::Beneficiary, ['trainee']],
            4 => [MembershipType::Beneficiary, ['volunteer']],
            5 => [MembershipType::Beneficiary, ['trainee', 'volunteer']],
            default => [MembershipType::Trainee, ['trainee', 'volunteer']],
        };
    }

    /**
     * @param  array<string>|null  $badges
     * @return list<string>
     */
    private function spatieRolesFromProfile(MembershipType $membershipType, ?array $badges): array
    {
        $roles = [];
        $badgeKeys = is_array($badges) ? $badges : [];

        if ($badgeKeys !== []) {
            if (in_array('trainee', $badgeKeys, true)) {
                $roles[] = 'trainee';
            }
            if (in_array('volunteer', $badgeKeys, true)) {
                $roles[] = 'volunteer';
            }
        }

        if ($roles === []) {
            $roles[] = match ($membershipType) {
                MembershipType::Volunteer => 'volunteer',
                MembershipType::Trainee => 'trainee',
                default => 'trainee',
            };
        }

        return array_values(array_unique($roles));
    }
}
