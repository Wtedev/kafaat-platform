<?php

namespace Database\Seeders;

use App\Enums\RegistrationStatus;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Models\Profile;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 25 مستخدماً للبوابة (متدرب / مستفيد / متطوع) مع توزيع تسجيلات المسارات والبرامج والتطوع.
 */
class RegistrationsSeeder extends Seeder
{
    /** @var list<string> */
    private const REQUIRED_PATH_SLUGS = [
        'masar-altasim-algrafiki-101',
        'masar-altahil-almehnawi',
        'masar-allugha-alengliziyya',
        'masar-muhalil-albayanat',
    ];

    /** @var list<string> */
    private const REQUIRED_PROGRAM_SLUGS = [
        'qadah',
        'zarih',
        'akfaa',
        'sanad',
        'multaqa-tahlil-albayanat',
        'maharat-altawasul-billugha-alengliziyya',
    ];

    private function passwordHash(): string
    {
        return Hash::make(env('SEED_USER_PASSWORD', 'Kafaat-Seed-2026-Secure!Change'));
    }

    public function run(): void
    {
        $approver = User::query()->where('email', 'lama.almeshiqeh@kafaat.org.sa')->first();

        if ($approver === null) {
            $this->command?->error('RegistrationsSeeder: approver admin missing.');

            return;
        }

        if (! $this->dependenciesMet()) {
            return;
        }

        $pw = $this->passwordHash();

        $definitions = $this->portalUserDefinitions();

        $usersByEmail = [];

        foreach ($definitions as $def) {
            $user = User::updateOrCreate(
                ['email' => $def['email']],
                [
                    'name' => $def['name'],
                    'password' => $pw,
                    'role_type' => $def['role_type'],
                    'is_active' => true,
                ]
            );
            $user->syncRoles([$def['spatie_role']]);
            Profile::firstOrCreate(['user_id' => $user->id]);
            $usersByEmail[$def['email']] = $user;
        }

        $this->seedPathRegistrations($usersByEmail, $approver);
        $this->seedProgramRegistrations($usersByEmail, $approver);
        $this->seedVolunteerRegistrations($usersByEmail, $approver);
    }

    /**
     * Requires LearningPathSeeder, TrainingProgramSeeder, and VolunteerOpportunitySeeder to have run first.
     */
    private function dependenciesMet(): bool
    {
        $issues = [];

        foreach (self::REQUIRED_PATH_SLUGS as $slug) {
            if (! LearningPath::query()->where('slug', $slug)->exists()) {
                $issues[] = "learning path slug `{$slug}`";
            }
        }

        foreach (self::REQUIRED_PROGRAM_SLUGS as $slug) {
            if (! TrainingProgram::query()->where('slug', $slug)->exists()) {
                $issues[] = "training program slug `{$slug}`";
            }
        }

        if (VolunteerOpportunity::count() < 6) {
            $issues[] = 'at least 6 volunteer opportunities (have '.VolunteerOpportunity::count().')';
        }

        if ($issues === []) {
            return true;
        }

        $this->command?->warn('RegistrationsSeeder skipped — fix dependencies then re-run: '.implode('; ', $issues).'.');

        return false;
    }

    /**
     * @return list<array{name: string, email: string, role_type: string, spatie_role: string}>
     */
    private function portalUserDefinitions(): array
    {
        return [
            ['name' => 'فهد الدوسري', 'email' => 'portal.user01@kafaat.org.sa', 'role_type' => 'trainee', 'spatie_role' => 'trainee'],
            ['name' => 'نوف العتيبي', 'email' => 'portal.user02@kafaat.org.sa', 'role_type' => 'trainee', 'spatie_role' => 'trainee'],
            ['name' => 'سلمان الغامدي', 'email' => 'portal.user03@kafaat.org.sa', 'role_type' => 'trainee', 'spatie_role' => 'trainee'],
            ['name' => 'هند القحطاني', 'email' => 'portal.user04@kafaat.org.sa', 'role_type' => 'trainee', 'spatie_role' => 'trainee'],
            ['name' => 'عمر الزهراني', 'email' => 'portal.user05@kafaat.org.sa', 'role_type' => 'trainee', 'spatie_role' => 'trainee'],
            ['name' => 'لينا الشمري', 'email' => 'portal.user06@kafaat.org.sa', 'role_type' => 'trainee', 'spatie_role' => 'trainee'],
            ['name' => 'تركي المالكي', 'email' => 'portal.user07@kafaat.org.sa', 'role_type' => 'trainee', 'spatie_role' => 'trainee'],
            ['name' => 'دانة الحربي', 'email' => 'portal.user08@kafaat.org.sa', 'role_type' => 'trainee', 'spatie_role' => 'trainee'],
            ['name' => 'ماجد السبيعي', 'email' => 'portal.user09@kafaat.org.sa', 'role_type' => 'trainee', 'spatie_role' => 'trainee'],
            ['name' => 'أمل الرشيد', 'email' => 'portal.user10@kafaat.org.sa', 'role_type' => 'trainee', 'spatie_role' => 'trainee'],
            ['name' => 'خالد بن سعيد', 'email' => 'portal.user11@kafaat.org.sa', 'role_type' => 'beneficiary', 'spatie_role' => 'trainee'],
            ['name' => 'مها العنزي', 'email' => 'portal.user12@kafaat.org.sa', 'role_type' => 'beneficiary', 'spatie_role' => 'trainee'],
            ['name' => 'ياسر الفيفي', 'email' => 'portal.user13@kafaat.org.sa', 'role_type' => 'beneficiary', 'spatie_role' => 'trainee'],
            ['name' => 'شهد البقمي', 'email' => 'portal.user14@kafaat.org.sa', 'role_type' => 'beneficiary', 'spatie_role' => 'trainee'],
            ['name' => 'بندر العمري', 'email' => 'portal.user15@kafaat.org.sa', 'role_type' => 'beneficiary', 'spatie_role' => 'trainee'],
            ['name' => 'رنا الدخيل', 'email' => 'portal.user16@kafaat.org.sa', 'role_type' => 'beneficiary', 'spatie_role' => 'trainee'],
            ['name' => 'سعد المطيري', 'email' => 'portal.user17@kafaat.org.sa', 'role_type' => 'beneficiary', 'spatie_role' => 'trainee'],
            ['name' => 'هيفاء السهلي', 'email' => 'portal.user18@kafaat.org.sa', 'role_type' => 'beneficiary', 'spatie_role' => 'trainee'],
            ['name' => 'نورة المطيري', 'email' => 'portal.user19@kafaat.org.sa', 'role_type' => 'trainee', 'spatie_role' => 'volunteer'],
            ['name' => 'ريم الشهري', 'email' => 'portal.user20@kafaat.org.sa', 'role_type' => 'trainee', 'spatie_role' => 'volunteer'],
            ['name' => 'لطيفة العتيبي', 'email' => 'portal.user21@kafaat.org.sa', 'role_type' => 'trainee', 'spatie_role' => 'volunteer'],
            ['name' => 'عادل القرني', 'email' => 'portal.user22@kafaat.org.sa', 'role_type' => 'trainee', 'spatie_role' => 'volunteer'],
            ['name' => 'منى الزهراني', 'email' => 'portal.user23@kafaat.org.sa', 'role_type' => 'trainee', 'spatie_role' => 'volunteer'],
            ['name' => 'فيصل الشهري', 'email' => 'portal.user24@kafaat.org.sa', 'role_type' => 'trainee', 'spatie_role' => 'volunteer'],
            ['name' => 'غادة العنزي', 'email' => 'portal.user25@kafaat.org.sa', 'role_type' => 'trainee', 'spatie_role' => 'volunteer'],
        ];
    }

    /**
     * @param  array<string, User>  $usersByEmail
     */
    private function seedPathRegistrations(array $usersByEmail, User $approver): void
    {
        $path = fn (string $slug) => LearningPath::query()->where('slug', $slug)->first();

        $rows = [
            ['email' => 'portal.user01@kafaat.org.sa', 'path' => 'masar-altasim-algrafiki-101', 'status' => RegistrationStatus::Approved],
            ['email' => 'portal.user02@kafaat.org.sa', 'path' => 'masar-altahil-almehnawi', 'status' => RegistrationStatus::Pending],
            ['email' => 'portal.user03@kafaat.org.sa', 'path' => 'masar-allugha-alengliziyya', 'status' => RegistrationStatus::Rejected, 'reason' => 'عدم استيفاء شرط المستوى المبدئي.'],
            ['email' => 'portal.user04@kafaat.org.sa', 'path' => 'masar-muhalil-albayanat', 'status' => RegistrationStatus::Completed, 'completed' => true],
            ['email' => 'portal.user05@kafaat.org.sa', 'path' => 'masar-altahil-almehnawi', 'status' => RegistrationStatus::Approved],
            ['email' => 'portal.user06@kafaat.org.sa', 'path' => 'masar-muhalil-albayanat', 'status' => RegistrationStatus::Pending],
            ['email' => 'portal.user11@kafaat.org.sa', 'path' => 'masar-altasim-algrafiki-101', 'status' => RegistrationStatus::Approved],
            ['email' => 'portal.user12@kafaat.org.sa', 'path' => 'masar-allugha-alengliziyya', 'status' => RegistrationStatus::Approved],
            ['email' => 'portal.user13@kafaat.org.sa', 'path' => 'masar-muhalil-albayanat', 'status' => RegistrationStatus::Rejected, 'reason' => 'اكتمال الطاقة الاستيعابية.'],
            ['email' => 'portal.user14@kafaat.org.sa', 'path' => 'masar-altahil-almehnawi', 'status' => RegistrationStatus::Completed, 'completed' => true],
        ];

        foreach ($rows as $row) {
            $p = $path($row['path']);
            $u = $usersByEmail[$row['email']] ?? null;
            if ($p === null || $u === null) {
                continue;
            }

            $data = [
                'status' => $row['status'],
                'rejected_reason' => $row['reason'] ?? null,
                'approved_by' => in_array($row['status'], [RegistrationStatus::Approved, RegistrationStatus::Completed, RegistrationStatus::Rejected], true)
                    ? $approver->id
                    : null,
                'approved_at' => in_array($row['status'], [RegistrationStatus::Approved, RegistrationStatus::Completed], true)
                    ? Carbon::now()->subDays(rand(5, 40))
                    : null,
                'completed_at' => ($row['completed'] ?? false)
                    ? Carbon::now()->subDays(rand(2, 15))
                    : null,
            ];

            PathRegistration::updateOrCreate(
                ['learning_path_id' => $p->id, 'user_id' => $u->id],
                $data
            );
        }
    }

    /**
     * @param  array<string, User>  $usersByEmail
     */
    private function seedProgramRegistrations(array $usersByEmail, User $approver): void
    {
        $prog = fn (string $slug) => TrainingProgram::query()->where('slug', $slug)->first();

        $rows = [
            ['email' => 'portal.user01@kafaat.org.sa', 'slug' => 'qadah', 'status' => RegistrationStatus::Approved],
            ['email' => 'portal.user02@kafaat.org.sa', 'slug' => 'zarih', 'status' => RegistrationStatus::Pending],
            ['email' => 'portal.user03@kafaat.org.sa', 'slug' => 'akfaa', 'status' => RegistrationStatus::Completed, 'att' => 88.0, 'score' => 78.0],
            ['email' => 'portal.user04@kafaat.org.sa', 'slug' => 'sanad', 'status' => RegistrationStatus::Completed, 'att' => 92.0, 'score' => 81.0],
            ['email' => 'portal.user05@kafaat.org.sa', 'slug' => 'multaqa-tahlil-albayanat', 'status' => RegistrationStatus::Approved],
            ['email' => 'portal.user06@kafaat.org.sa', 'slug' => 'maharat-altawasul-billugha-alengliziyya', 'status' => RegistrationStatus::Pending],
            ['email' => 'portal.user07@kafaat.org.sa', 'slug' => 'akfaa', 'status' => RegistrationStatus::Rejected, 'reason' => 'عدم اكتمال الملف الشخصي.'],
            ['email' => 'portal.user08@kafaat.org.sa', 'slug' => 'qadah', 'status' => RegistrationStatus::Completed, 'att' => 85.0, 'score' => 72.0],
            ['email' => 'portal.user09@kafaat.org.sa', 'slug' => 'sanad', 'status' => RegistrationStatus::Approved],
            ['email' => 'portal.user10@kafaat.org.sa', 'slug' => 'zarih', 'status' => RegistrationStatus::Completed, 'att' => 90.0, 'score' => 76.0],
            ['email' => 'portal.user15@kafaat.org.sa', 'slug' => 'maharat-altawasul-billugha-alengliziyya', 'status' => RegistrationStatus::Approved],
            ['email' => 'portal.user16@kafaat.org.sa', 'slug' => 'multaqa-tahlil-albayanat', 'status' => RegistrationStatus::Pending],
            ['email' => 'portal.user17@kafaat.org.sa', 'slug' => 'akfaa', 'status' => RegistrationStatus::Completed, 'att' => 86.0, 'score' => 68.0],
            ['email' => 'portal.user18@kafaat.org.sa', 'slug' => 'qadah', 'status' => RegistrationStatus::Completed, 'att' => 91.0, 'score' => 84.0],
        ];

        foreach ($rows as $row) {
            $p = $prog($row['slug']);
            $u = $usersByEmail[$row['email']] ?? null;
            if ($p === null || $u === null) {
                continue;
            }

            $status = $row['status'];
            $data = [
                'status' => $status,
                'rejected_reason' => $row['reason'] ?? null,
                'approved_by' => in_array($status, [RegistrationStatus::Approved, RegistrationStatus::Completed, RegistrationStatus::Rejected], true)
                    ? $approver->id
                    : null,
                'approved_at' => in_array($status, [RegistrationStatus::Approved, RegistrationStatus::Completed], true)
                    ? Carbon::now()->subDays(rand(3, 35))
                    : null,
                'attendance_percentage' => $row['att'] ?? 0.0,
                'score' => array_key_exists('score', $row) ? $row['score'] : null,
            ];

            ProgramRegistration::updateOrCreate(
                ['training_program_id' => $p->id, 'user_id' => $u->id],
                $data
            );
        }
    }

    /**
     * @param  array<string, User>  $usersByEmail
     */
    private function seedVolunteerRegistrations(array $usersByEmail, User $approver): void
    {
        $opps = VolunteerOpportunity::query()->orderBy('id')->get();
        if ($opps->isEmpty()) {
            return;
        }

        $volunteerEmails = [
            'portal.user19@kafaat.org.sa',
            'portal.user20@kafaat.org.sa',
            'portal.user21@kafaat.org.sa',
            'portal.user22@kafaat.org.sa',
            'portal.user23@kafaat.org.sa',
            'portal.user24@kafaat.org.sa',
            'portal.user25@kafaat.org.sa',
        ];

        $matrix = [
            [0, 'portal.user19@kafaat.org.sa', RegistrationStatus::Approved],
            [1, 'portal.user20@kafaat.org.sa', RegistrationStatus::Pending],
            [2, 'portal.user21@kafaat.org.sa', RegistrationStatus::Rejected, 'عدم توفر الشهادة المطلوبة.'],
            [3, 'portal.user22@kafaat.org.sa', RegistrationStatus::Completed],
            [4, 'portal.user23@kafaat.org.sa', RegistrationStatus::Approved],
            [5, 'portal.user24@kafaat.org.sa', RegistrationStatus::Pending],
            [0, 'portal.user25@kafaat.org.sa', RegistrationStatus::Approved],
            [1, 'portal.user19@kafaat.org.sa', RegistrationStatus::Completed],
        ];

        foreach ($matrix as $m) {
            $oppIndex = $m[0];
            $email = $m[1];
            $status = $m[2];
            $opp = $opps->get($oppIndex);
            $u = $usersByEmail[$email] ?? null;
            if ($opp === null || $u === null) {
                continue;
            }

            $data = [
                'status' => $status,
                'rejected_reason' => $m[3] ?? null,
                'approved_by' => in_array($status, [RegistrationStatus::Approved, RegistrationStatus::Completed, RegistrationStatus::Rejected], true)
                    ? $approver->id
                    : null,
                'approved_at' => in_array($status, [RegistrationStatus::Approved, RegistrationStatus::Completed], true)
                    ? Carbon::now()->subDays(rand(4, 30))
                    : null,
            ];

            VolunteerRegistration::updateOrCreate(
                ['opportunity_id' => $opp->id, 'user_id' => $u->id],
                $data
            );
        }
    }
}
