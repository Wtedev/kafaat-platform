<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\AttendanceStatus;
use App\Enums\IdentityType;
use App\Enums\MembershipType;
use App\Enums\OpportunityStatus;
use App\Enums\ProfileGender;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramPrepDayType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Enums\UserDocumentStatus;
use App\Enums\UserDocumentType;
use App\Models\Certificate;
use App\Models\EntityNote;
use App\Models\Profile;
use App\Models\ProgramAttendance;
use App\Models\ProgramBroadcastRecipient;
use App\Models\ProgramPrepDay;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\UserDocument;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;
use App\Services\Documents\PrivateDocumentsStorage;
use App\Services\Identity\IdentityNumberService;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * بيانات معاينة عربية للواجهة المحلية فقط. لا يُستدعى من DatabaseSeeder.
 *
 * php artisan db:seed --class=StaffUiPreviewSeeder
 */
class StaffUiPreviewSeeder extends Seeder
{
    public const SUPER_EMAIL = 'preview.super@kafaat.local';

    public const PASSWORD = 'password';

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('StaffUiPreviewSeeder يعمل فقط عندما تكون APP_ENV=local.');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->ensureRoles();
        $admin = $this->superAdmin();
        $staff = $this->staffUsers();
        $beneficiaries = $this->beneficiaries($admin);
        $programs = $this->programs($admin);
        $this->registrations($programs, $beneficiaries, $staff[1], $admin);

        $this->command?->info('معاينة واجهة الموظفين جاهزة.');
        $this->command?->info('  الدخول: '.self::SUPER_EMAIL.' / '.self::PASSWORD);
    }

    private function ensureRoles(): void
    {
        foreach ([
            RbacCatalog::ROLE_ADMIN,
            RbacCatalog::ROLE_STAFF,
            RbacCatalog::ROLE_BENEFICIARY,
            'super_admin',
        ] as $name) {
            Role::firstOrCreate([
                'name' => $name,
                'guard_name' => RbacCatalog::GUARD_WEB,
            ]);
        }
    }

    private function superAdmin(): User
    {
        $user = $this->upsertUser(
            self::SUPER_EMAIL,
            'فيصل',
            'عبدالله',
            'الحربي',
            'admin',
            true,
        );
        $user->syncRoles([RbacCatalog::ROLE_ADMIN, 'super_admin']);

        Profile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'membership_type' => MembershipType::Beneficiary,
                'gender' => ProfileGender::Male,
                'city' => 'الرياض',
                'job_title' => 'مدير النظام',
            ],
        );

        return $user;
    }

    /**
     * @return list<User>
     */
    private function staffUsers(): array
    {
        $rows = [
            ['نورة', 'سعد', 'القحطاني', 'preview.staff.programs@kafaat.local', 'مسؤولة البرامج', ['programs.view', 'programs.create', 'programs.update', 'manage_programs']],
            ['خالد', 'محمد', 'الدوسري', 'preview.staff.registrations@kafaat.local', 'مسؤول التسجيلات', ['registrations.view', 'registrations.approve', 'registrations.reject', 'approve_registrations']],
            ['لمى', 'فهد', 'العتيبي', 'preview.staff.attendance@kafaat.local', 'مسؤولة الحضور', ['progress.view', 'progress.update']],
            ['سلمان', 'علي', 'الغامدي', 'preview.staff.certificates@kafaat.local', 'مسؤول الشهادات', ['certificates.view', 'certificates.issue', 'issue_certificates']],
            ['هند', 'عبدالرحمن', 'الشهري', 'preview.staff.volunteers@kafaat.local', 'مسؤولة التطوع', ['volunteering.view', 'manage_volunteers']],
            ['ماجد', 'إبراهيم', 'المطيري', 'preview.staff.privacy@kafaat.local', 'مسؤول الخصوصية', ['privacy_requests.view', 'beneficiaries.view_basic', 'beneficiaries.view_contact']],
        ];

        $users = [];
        foreach ($rows as [$first, $father, $family, $email, $title, $permissions]) {
            $user = $this->upsertUser($email, $first, $father, $family, 'staff', true);
            $user->syncRoles([RbacCatalog::ROLE_STAFF]);
            $user->syncPermissions($this->permissions($permissions));
            Profile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'membership_type' => MembershipType::Beneficiary,
                    'gender' => in_array($first, ['نورة', 'لمى', 'هند'], true) ? ProfileGender::Female : ProfileGender::Male,
                    'city' => 'الرياض',
                    'job_title' => $title,
                ],
            );
            $users[] = $user;
        }

        return $users;
    }

    /**
     * @param  list<string>  $names
     * @return list<Permission>
     */
    private function permissions(array $names): array
    {
        $permissions = [];
        foreach ($names as $name) {
            $permissions[] = Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => RbacCatalog::GUARD_WEB,
            ]);
        }

        return $permissions;
    }

    /**
     * @return list<User>
     */
    private function beneficiaries(User $admin): array
    {
        $people = [
            ['فهد', 'ناصر', 'الدوسري', 'male'],
            ['نوف', 'عبدالعزيز', 'العتيبي', 'female'],
            ['سلمان', 'خالد', 'الغامدي', 'male'],
            ['هند', 'سعد', 'القحطاني', 'female'],
            ['عمر', 'يوسف', 'الزهراني', 'male'],
            ['لينا', 'ماجد', 'الشمري', 'female'],
            ['تركي', 'فيصل', 'المالكي', 'male'],
            ['دانة', 'عبدالله', 'الحربي', 'female'],
            ['ماجد', 'إبراهيم', 'السبيعي', 'male'],
            ['أمل', 'محمد', 'الرشيد', 'female'],
            ['خالد', 'سعد', 'بن سعيد', 'male'],
            ['مها', 'علي', 'العنزي', 'female'],
            ['ياسر', 'فهد', 'الفيفي', 'male'],
            ['شهد', 'ناصر', 'البقمي', 'female'],
            ['بندر', 'عبدالرحمن', 'العمري', 'male'],
            ['رنا', 'خالد', 'الدخيل', 'female'],
            ['سعد', 'ماجد', 'المطيري', 'male'],
            ['هيفاء', 'عبدالعزيز', 'السهلي', 'female'],
            ['عبدالله', 'سلمان', 'الشهراني', 'male'],
            ['جود', 'فهد', 'الحارثي', 'female'],
            ['نايف', 'محمد', 'الجهني', 'male'],
            ['ريم', 'سعد', 'الأنصاري', 'female'],
            ['وليد', 'عبدالله', 'الباز', 'male'],
            ['لمياء', 'علي', 'الخالدي', 'female'],
            ['حسام', 'ناصر', 'التميمي', 'male'],
            ['غادة', 'عبدالرحمن', 'اليامي', 'female'],
            ['طلال', 'خالد', 'الرشيدي', 'male'],
            ['أسماء', 'فهد', 'الدوسري', 'female'],
            ['زياد', 'سعد', 'القحطاني', 'male'],
            ['منال', 'ماجد', 'الغامدي', 'female'],
            ['إياد', 'عبدالعزيز', 'الحربي', 'male'],
            ['بتول', 'محمد', 'العتيبي', 'female'],
            ['راكان', 'علي', 'الزهراني', 'male'],
            ['سارة', 'ناصر', 'الشمري', 'female'],
            ['مشعل', 'فهد', 'المالكي', 'male'],
            ['ليان', 'سعد', 'السبيعي', 'female'],
            ['عبدالمجيد', 'خالد', 'العنزي', 'male'],
            ['وجدان', 'عبدالله', 'الشهري', 'female'],
            ['فيصل', 'ماجد', 'العمري', 'male'],
            ['جواهر', 'علي', 'المطيري', 'female'],
        ];

        $cities = ['الرياض', 'جدة', 'الدمام', 'أبها', 'المدينة المنورة', 'تبوك', 'حائل', 'جازان'];
        $jobs = ['أخصائية تدريب', 'محلل بيانات', 'منسق برامج', 'معلمة', 'مهندس برمجيات', 'أخصائية موارد بشرية'];
        $grandfathers = ['محمد', 'عبدالله', 'سعد', 'علي', 'فهد', 'ناصر', 'خالد', 'إبراهيم'];
        $users = [];

        foreach ($people as $index => [$first, $father, $family, $gender]) {
            $complete = $index < 20;
            $active = ! in_array($index, [3, 11, 18, 25, 33], true);
            $withSkills = $index % 3 !== 2;
            $withEducation = $index % 4 === 0;
            $withCv = $index % 2 === 0;
            $email = sprintf('preview.beneficiary%02d@kafaat.local', $index + 1);
            $user = $this->upsertUser(
                $email,
                $first,
                $father,
                $family,
                'beneficiary',
                $complete,
                $active,
                $grandfathers[$index % count($grandfathers)],
                $complete || $index < 30 ? sprintf('055%07d', 1000000 + $index) : null,
            );
            $user->syncRoles([RbacCatalog::ROLE_BENEFICIARY]);
            $this->syncIdentity($user, $complete, $index);

            $skills = $withSkills ? [[
                'skill_name' => $jobs[$index % count($jobs)],
                'level' => ['مبتدئ', 'متوسط', 'متقدم', 'خبير'][$index % 4],
                'category' => ['تقنية', 'شخصية', 'إدارية', 'أخرى'][$index % 4],
            ]] : [];
            $education = $withEducation ? [[
                'institution' => 'جامعة الملك سعود',
                'degree_or_program' => 'بكالوريوس',
                'field' => 'نظم المعلومات',
                'start_year' => '2014',
                'end_year' => '2018',
                'is_current' => false,
            ]] : [];

            $profile = Profile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'membership_type' => MembershipType::Beneficiary,
                    'gender' => $complete ? ($gender === 'female' ? ProfileGender::Female : ProfileGender::Male) : null,
                    'birth_date' => $complete ? sprintf('199%d-0%d-15', $index % 8, ($index % 9) + 1) : null,
                    'city' => $complete ? $cities[$index % count($cities)] : null,
                    'job_title' => $complete || $withSkills ? $jobs[$index % count($jobs)] : null,
                    'bio' => $complete ? 'مستفيد مكتمل بيانات الهوية في المعاينة المحلية.' : null,
                    'iconic_skill' => $skills[0]['skill_name'] ?? null,
                    'cv_sections' => ($skills !== [] || $education !== []) ? array_filter([
                        'skills' => $skills !== [] ? $skills : null,
                        'education' => $education !== [] ? $education : null,
                    ]) : null,
                ],
            );

            $this->syncCvFile($user, $profile, $withCv);

            if ($index % 6 === 0) {
                EntityNote::query()->updateOrCreate(
                    [
                        'noteable_type' => $user->getMorphClass(),
                        'noteable_id' => $user->id,
                        'body' => 'ملاحظة معاينة داخلية: راجع ملف المستفيد قبل المتابعة.',
                    ],
                    ['created_by' => $admin->id],
                );
            }

            $users[] = $user;
        }

        return $users;
    }

    /**
     * @return list<TrainingProgram>
     */
    private function programs(User $admin): array
    {
        $rows = [
            ['مهارات القيادة التطوعية', ProgramStatus::Published, 'images/programs/volunteer-leaders.png', TrainingProgramKind::Course, ProgramDeliveryMode::InPerson, 'قاعة كفاءات — الرياض'],
            ['ملتقى تحليل البيانات', ProgramStatus::Published, 'images/programs/multaqa-tahlil-al-bayanat-2.jpg', TrainingProgramKind::Forum, ProgramDeliveryMode::Hybrid, 'مركز الملك عبدالعزيز — جدة'],
            ['ورشة الكتابة المهنية', ProgramStatus::Published, 'images/programs/fok.jpg', TrainingProgramKind::Workshop, ProgramDeliveryMode::Remote, null],
            ['إعداد المدربين', ProgramStatus::Draft, null, TrainingProgramKind::Course, ProgramDeliveryMode::InPerson, 'قاعة التدريب — الدمام'],
            ['برنامج أُعيد أرشفته', ProgramStatus::Archived, 'images/programs/adeed-logo.png', TrainingProgramKind::Session, ProgramDeliveryMode::InPerson, 'مقر الجمعية'],
            ['أساسيات لغة الإشارة', ProgramStatus::Published, null, TrainingProgramKind::Course, ProgramDeliveryMode::Hybrid, 'عن بُعد وحضوري'],
            ['مسودة تصميم التجربة', ProgramStatus::Draft, null, TrainingProgramKind::Workshop, ProgramDeliveryMode::Remote, null],
            ['ملتقى المتطوعين السابق', ProgramStatus::Archived, null, TrainingProgramKind::Event, ProgramDeliveryMode::InPerson, 'واجهة الكورنيش — جدة'],
        ];

        $programs = [];
        foreach ($rows as $index => [$title, $status, $image, $kind, $delivery, $venue]) {
            $slug = 'staff-ui-preview-'.($index + 1);
            $program = TrainingProgram::query()->firstOrNew(['slug' => $slug]);
            $program->allowCoverUpdate = true;
            $program->fill([
                'title' => $title,
                'slug' => $slug,
                'description' => 'برنامج معاينة محلي لواجهة الموظفين. '.$title.'.',
                'program_kind' => $kind,
                'delivery_mode' => $delivery,
                'venue' => $venue,
                'image' => $image,
                'capacity' => 40,
                'start_date' => '2026-09-06',
                'end_date' => '2026-09-20',
                'registration_start' => '2026-08-01',
                'registration_end' => '2026-09-01',
                'status' => $status,
                'published_at' => $status === ProgramStatus::Published ? '2026-08-15 09:00:00' : null,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
                'owner_id' => $admin->id,
            ]);
            $program->save();

            foreach (['2026-09-07', '2026-09-09', '2026-09-14'] as $date) {
                $day = ProgramPrepDay::query()
                    ->where('training_program_id', $program->id)
                    ->whereDate('prep_date', $date)
                    ->first() ?? new ProgramPrepDay([
                        'training_program_id' => $program->id,
                        'prep_date' => $date,
                    ]);
                $day->fill([
                    'delivery_type' => $delivery === ProgramDeliveryMode::Remote
                        ? ProgramPrepDayType::Remote
                        : ProgramPrepDayType::InPerson,
                    'requires_attendance' => true,
                ])->save();
            }

            $programs[] = $program;
        }

        return $programs;
    }

    /**
     * @param  list<TrainingProgram>  $programs
     * @param  list<User>  $beneficiaries
     */
    private function registrations(array $programs, array $beneficiaries, User $reviewer, User $admin): void
    {
        $statuses = [
            RegistrationStatus::Pending,
            RegistrationStatus::Approved,
            RegistrationStatus::Rejected,
            RegistrationStatus::Cancelled,
            RegistrationStatus::Completed,
        ];
        $programIds = array_map(fn (TrainingProgram $program): int => $program->id, $programs);
        $opportunities = $this->volunteerOpportunities($admin);

        ProgramRegistration::withoutEvents(function () use ($programs, $beneficiaries, $reviewer, $statuses, $programIds): void {
            foreach ($beneficiaries as $index => $beneficiary) {
                $hasRegistration = $index % 7 !== 6;
                if (! $hasRegistration) {
                    $this->clearPreviewRegistrations($beneficiary, $programIds);

                    continue;
                }

                $status = $statuses[$index % count($statuses)];
                $program = $programs[$index % count($programs)];
                $registration = ProgramRegistration::query()->updateOrCreate(
                    [
                        'training_program_id' => $program->id,
                        'user_id' => $beneficiary->id,
                    ],
                    $this->registrationAttributes($status, $index, $reviewer),
                );

                $this->attendance($registration, $status, $index);
                $this->clearOtherPreviewRegistrations($beneficiary, $programIds, $program->id);

                Certificate::query()
                    ->where('user_id', $beneficiary->id)
                    ->where('certificate_number', 'like', 'CERT-PREVIEW-%')
                    ->delete();

                if ($status === RegistrationStatus::Completed && $index % 10 === 9) {
                    $this->certificate($beneficiary, $program);
                }
            }
        });

        VolunteerRegistration::withoutEvents(function () use ($beneficiaries, $opportunities, $reviewer): void {
            foreach ($beneficiaries as $index => $beneficiary) {
                $hasVolunteer = $index % 4 === 1;
                if (! $hasVolunteer) {
                    VolunteerRegistration::query()
                        ->where('user_id', $beneficiary->id)
                        ->whereIn('opportunity_id', array_map(fn (VolunteerOpportunity $opportunity): int => $opportunity->id, $opportunities))
                        ->delete();

                    continue;
                }

                $opportunity = $opportunities[$index % count($opportunities)];
                $status = [
                    RegistrationStatus::Pending,
                    RegistrationStatus::Approved,
                    RegistrationStatus::Completed,
                    RegistrationStatus::Rejected,
                ][intdiv($index, 4) % 4];

                VolunteerRegistration::query()->updateOrCreate(
                    [
                        'opportunity_id' => $opportunity->id,
                        'user_id' => $beneficiary->id,
                    ],
                    [
                        'status' => $status,
                        'approved_by' => in_array($status, [RegistrationStatus::Approved, RegistrationStatus::Completed, RegistrationStatus::Rejected], true)
                            ? $reviewer->id
                            : null,
                        'approved_at' => in_array($status, [RegistrationStatus::Approved, RegistrationStatus::Completed], true)
                            ? '2026-08-22 11:00:00'
                            : null,
                        'rejected_reason' => $status === RegistrationStatus::Rejected ? 'اكتمل العدد لهذه الفرصة.' : null,
                    ],
                );
            }
        });
    }

    /**
     * @param  list<int>  $programIds
     */
    private function clearPreviewRegistrations(User $beneficiary, array $programIds): void
    {
        $registrations = ProgramRegistration::query()
            ->where('user_id', $beneficiary->id)
            ->whereIn('training_program_id', $programIds)
            ->get();

        foreach ($registrations as $registration) {
            ProgramAttendance::query()->where('program_registration_id', $registration->id)->delete();
            ProgramBroadcastRecipient::query()->where('program_registration_id', $registration->id)->delete();
            $registration->delete();
        }

        Certificate::query()
            ->where('user_id', $beneficiary->id)
            ->where('certificate_number', 'like', 'CERT-PREVIEW-%')
            ->delete();
    }

    /**
     * @param  list<int>  $programIds
     */
    private function clearOtherPreviewRegistrations(User $beneficiary, array $programIds, int $keepProgramId): void
    {
        $registrations = ProgramRegistration::query()
            ->where('user_id', $beneficiary->id)
            ->whereIn('training_program_id', $programIds)
            ->where('training_program_id', '!=', $keepProgramId)
            ->get();

        foreach ($registrations as $registration) {
            ProgramAttendance::query()->where('program_registration_id', $registration->id)->delete();
            ProgramBroadcastRecipient::query()->where('program_registration_id', $registration->id)->delete();
            $registration->delete();
        }
    }

    /**
     * @return list<VolunteerOpportunity>
     */
    private function volunteerOpportunities(User $admin): array
    {
        $rows = [
            ['تنظيم ملتقى المتطوعين', 'staff-ui-preview-vol-1'],
            ['دعم التسجيل في يوم المهنة', 'staff-ui-preview-vol-2'],
        ];
        $opportunities = [];

        foreach ($rows as [$title, $slug]) {
            $opportunities[] = VolunteerOpportunity::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'description' => 'فرصة تطوع لمعاينة واجهة الموظفين محلياً.',
                    'capacity' => 20,
                    'hours_expected' => 8,
                    'start_date' => '2026-09-10',
                    'end_date' => '2026-09-12',
                    'status' => OpportunityStatus::Published,
                    'published_at' => '2026-08-01 09:00:00',
                    'notify_on_publish' => false,
                    'notify_registrants_on_update' => false,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }

        return $opportunities;
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationAttributes(RegistrationStatus $status, int $index, User $reviewer): array
    {
        $score = match ($status) {
            RegistrationStatus::Completed => 75 + ($index % 20),
            RegistrationStatus::Approved => 50 + ($index % 40),
            default => null,
        };

        return [
            'status' => $status,
            'approved_by' => in_array($status, [RegistrationStatus::Approved, RegistrationStatus::Completed, RegistrationStatus::Rejected], true)
                ? $reviewer->id
                : null,
            'approved_at' => in_array($status, [RegistrationStatus::Approved, RegistrationStatus::Completed], true)
                ? '2026-08-20 10:00:00'
                : null,
            'rejected_reason' => $status === RegistrationStatus::Rejected
                ? 'لم تكتمل شروط القبول في هذه الدفعة.'
                : null,
            'score' => $score,
        ];
    }

    private function attendance(ProgramRegistration $registration, RegistrationStatus $status, int $index): void
    {
        if (! in_array($status, [RegistrationStatus::Approved, RegistrationStatus::Completed], true)) {
            return;
        }

        $dates = ['2026-09-07', '2026-09-09', '2026-09-14'];
        $pattern = $status === RegistrationStatus::Completed
            ? [AttendanceStatus::Present, AttendanceStatus::Present, AttendanceStatus::Late]
            : [AttendanceStatus::Present, AttendanceStatus::Absent, AttendanceStatus::Excused];

        foreach ($dates as $day => $date) {
            $attendance = ProgramAttendance::query()
                ->where('program_registration_id', $registration->id)
                ->whereDate('training_date', $date)
                ->first() ?? new ProgramAttendance([
                    'program_registration_id' => $registration->id,
                    'training_date' => $date,
                ]);
            $attendance->fill([
                'status' => $pattern[($day + $index) % count($pattern)],
                'notes' => null,
            ])->save();
        }
    }

    private function certificate(User $beneficiary, TrainingProgram $program): void
    {
        $number = 'CERT-PREVIEW-'.$program->id.'-'.$beneficiary->id;

        Certificate::query()->updateOrCreate(
            ['certificate_number' => $number],
            [
                'user_id' => $beneficiary->id,
                'certificateable_type' => $program->getMorphClass(),
                'certificateable_id' => $program->id,
                'verification_code' => hash('sha256', $number),
                'file_path' => null,
                'issued_at' => '2026-09-21 12:00:00',
            ],
        );
    }

    private function upsertUser(
        string $email,
        string $first,
        string $father,
        string $family,
        string $roleType,
        bool $profileComplete,
        bool $active = true,
        ?string $grandfather = null,
        ?string $phone = null,
    ): User {
        $name = trim(implode(' ', array_filter([$first, $father, $grandfather, $family])));

        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'first_name' => $first,
                'father_name' => $father,
                'grandfather_name' => $grandfather,
                'family_name' => $family,
                'phone' => $phone,
                'password' => Hash::make(self::PASSWORD),
                'role_type' => $roleType,
                'is_active' => $active,
                'account_status' => AccountStatus::Active,
                'email_verified_at' => now(),
                'notification_prefs_set_at' => now(),
                'profile_completed_at' => $profileComplete ? now() : null,
            ],
        );
    }

    private function syncIdentity(User $user, bool $complete, int $index): void
    {
        if (! $complete) {
            $user->forceFill([
                'identity_type' => null,
                'identity_number_ciphertext' => null,
                'identity_number_lookup_hash' => null,
                'identity_number_last4' => null,
                'identity_confirmed_at' => null,
            ])->save();

            return;
        }

        $number = sprintf('1%09d', 90000000 + $index);
        $user->forceFill(IdentityNumberService::prepareStoragePayload(
            $number,
            $index % 2 === 0 ? IdentityType::NationalId : IdentityType::Iqama,
        ))->save();
    }

    private function syncCvFile(User $user, Profile $profile, bool $withCv): void
    {
        $relative = 'cv/preview/beneficiary-'.$user->id.'.pdf';

        if (! $withCv) {
            $profile->forceFill(['current_cv_document_id' => null])->save();
            UserDocument::query()
                ->where('user_id', $user->id)
                ->where('path', 'like', 'cv/preview/%')
                ->update([
                    'status' => UserDocumentStatus::Deleted,
                    'deleted_at' => now(),
                ]);

            return;
        }

        $disk = PrivateDocumentsStorage::disk();
        $disk->put($relative, "%PDF-1.1\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");

        $document = UserDocument::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'path' => $relative,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'document_type' => UserDocumentType::Cv,
                'disk' => PrivateDocumentsStorage::diskName(),
                'mime_type' => 'application/pdf',
                'extension' => 'pdf',
                'size_bytes' => strlen("%PDF-1.1\n"),
                'sha256_checksum' => hash('sha256', $relative),
                'status' => UserDocumentStatus::Active,
                'uploaded_by' => $user->id,
                'uploaded_at' => now(),
                'deleted_at' => null,
            ],
        );

        $profile->forceFill(['current_cv_document_id' => $document->id])->save();
    }
}
