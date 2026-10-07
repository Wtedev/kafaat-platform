<?php

namespace App\Services\StaffUi;

use App\Enums\ProgramStatus;
use App\Models\TrainingProgram;
use Illuminate\Support\Carbon;

/**
 * حالة البرنامج وحالة التسجيل في قائمة الموظفين.
 *
 * حالة التسجيل (مستقلة عن مسودة/منشور). المقارنة على اليوم بتوقيت التطبيق، والحقول كما هي في training_programs:
 * 1. learning_path_id غير فارغ → «عبر المسار». البرنامج المرتبط بمسار لا يملك نافذة تسجيل مستقلة (isRegistrationOpen ترجع false).
 * 2. end_date أقدم من اليوم، أو registration_end أقدم من اليوم → «مغلق». هذه حالة «منتهي» في registrationWindowStatusLabel.
 * 3. registration_start بعد اليوم → «لم يبدأ».
 * 4. النافذة مفتوحة والسعة غير فارغة وعدد المقبولين (status = approved فقط) أكبر من السعة أو يساويها → «مكتمل العدد».
 *    الاكتمال يُفحص فقط عندما تكون النافذة مفتوحة. الإغلاق أو «لم يبدأ» يسبق اكتمال العدد. السعة null غير محدودة ولا تكتمل.
 *    المكتمل (completed) والمعلّق والمرفوض لا يدخلون في مقارنة السعة.
 * 5. النافذة مفتوحة → «مفتوح». النافذة مفتوحة عندما لا يوجد مسار، وبداية التسجيل فارغة أو <= اليوم، ونهاية التسجيل فارغة أو >= اليوم.
 *    فراغ التاريخين معاً يعني التسجيل مفتوح.
 * 6. غير ذلك → «مغلق».
 * start_date وحده لا يغيّر الحالة: فرع «لم يبدأ» المعتمد على تاريخ بداية البرنامج في النموذج لا يُنفَّذ بعد الفحوصات السابقة.
 *
 * حالة البرنامج للعرض (شارة + سطر النشر):
 * - status = archived → شارة «مؤرشف» بلا سطر نشر.
 * - published_at في المستقبل (سواء كانت الحالة مسودة أو منشوراً) → شارة «مجدول» وسطر «مجدول للنشر في {التاريخ}».
 * - status = draft بغير ذلك → شارة «مسودة». تاريخ نشر ماضٍ على مسودة يعني أن الجدولة لم تُنفَّذ بعد، فيبقى «مسودة» بلا «نُشر منذ».
 * - status = published و published_at فارغ أو <= الآن → شارة «منشور». مع تاريخ: «نُشر منذ ...» بأكبر وحدة (يوم، أسبوع، شهر، سنة) وصيغ العربية: 1، 2، 3–10، 11 فأكثر. اليوم نفسه: «نُشر اليوم».
 */
final class StaffProgramStatus
{
    public const REGISTRATION_OPEN = 'open';

    public const REGISTRATION_CLOSED = 'closed';

    public const REGISTRATION_UPCOMING = 'upcoming';

    public const REGISTRATION_FULL = 'full';

    public const REGISTRATION_PATH = 'path';

    public function snapshot(TrainingProgram $program): StaffProgramSnapshot
    {
        $registration = $this->registration($program);

        return new StaffProgramSnapshot(
            registrationKey: $registration['key'],
            registrationLabel: $registration['label'],
            registrationTone: $registration['tone'],
            programLabel: $this->programLabel($program),
            programTone: $this->programTone($program),
            publicationLine: $this->publicationLine($program),
            pendingCount: $this->pendingCount($program),
            acceptedLabel: $this->acceptedLabel($program),
        );
    }

    /**
     * @return array{key: string, label: string, tone: string}
     */
    public function registration(TrainingProgram $program): array
    {
        if ($program->learning_path_id !== null) {
            return ['key' => self::REGISTRATION_PATH, 'label' => 'عبر المسار', 'tone' => 'info'];
        }

        $today = Carbon::today();

        if ($program->end_date !== null && $program->end_date->lt($today)) {
            return ['key' => self::REGISTRATION_CLOSED, 'label' => 'مغلق', 'tone' => 'muted'];
        }

        if ($program->registration_end !== null && $program->registration_end->lt($today)) {
            return ['key' => self::REGISTRATION_CLOSED, 'label' => 'مغلق', 'tone' => 'muted'];
        }

        if ($program->registration_start !== null && $program->registration_start->gt($today)) {
            return ['key' => self::REGISTRATION_UPCOMING, 'label' => 'لم يبدأ', 'tone' => 'warning'];
        }

        if ($program->isRegistrationOpen() && $this->isCapacityFull($program)) {
            return ['key' => self::REGISTRATION_FULL, 'label' => 'مكتمل العدد', 'tone' => 'warning'];
        }

        if ($program->isRegistrationOpen()) {
            return ['key' => self::REGISTRATION_OPEN, 'label' => 'مفتوح', 'tone' => 'success'];
        }

        return ['key' => self::REGISTRATION_CLOSED, 'label' => 'مغلق', 'tone' => 'muted'];
    }

    public function programLabel(TrainingProgram $program): string
    {
        if ($program->status === ProgramStatus::Archived) {
            return 'مؤرشف';
        }

        if ($this->isScheduled($program)) {
            return 'مجدول';
        }

        if ($program->status === ProgramStatus::Draft) {
            return 'مسودة';
        }

        return 'منشور';
    }

    public function programTone(TrainingProgram $program): string
    {
        return match ($this->programLabel($program)) {
            'منشور' => 'success',
            'مجدول' => 'info',
            'مؤرشف' => 'muted',
            default => 'muted',
        };
    }

    public function publicationLine(TrainingProgram $program): ?string
    {
        if ($program->status === ProgramStatus::Archived) {
            return null;
        }

        $publishedAt = $program->published_at;
        if ($publishedAt === null) {
            return null;
        }

        if ($publishedAt->greaterThan(now())) {
            return 'مجدول للنشر في '.$publishedAt->timezone((string) config('app.timezone'))->format('Y/m/d');
        }

        if ($program->status !== ProgramStatus::Published) {
            return null;
        }

        return $this->publishedAgo($publishedAt);
    }

    public function pendingCount(TrainingProgram $program): int
    {
        return (int) ($program->pending_registrations_count ?? 0);
    }

    public function approvedCount(TrainingProgram $program): int
    {
        return (int) ($program->approved_registrations_count ?? 0);
    }

    public function acceptedLabel(TrainingProgram $program): string
    {
        $approved = $this->approvedCount($program);

        if ($program->capacity === null) {
            return $approved.' / غير محدود';
        }

        return $approved.' / '.$program->capacity;
    }

    public function publishedAgo(Carbon $publishedAt): string
    {
        $timezone = (string) config('app.timezone');
        $published = $publishedAt->copy()->timezone($timezone)->startOfDay();
        $today = now()->timezone($timezone)->startOfDay();
        $days = (int) $published->diffInDays($today);

        if ($days === 0) {
            return 'نُشر اليوم';
        }

        if ($days < 7) {
            return 'نُشر '.$this->ago($days, 'يوم', 'يومين', 'أيام', 'يوماً');
        }

        if ($days < 30) {
            return 'نُشر '.$this->ago(intdiv($days, 7), 'أسبوع', 'أسبوعين', 'أسابيع', 'أسبوعاً');
        }

        if ($days < 365) {
            return 'نُشر '.$this->ago(intdiv($days, 30), 'شهر', 'شهرين', 'أشهر', 'شهراً');
        }

        return 'نُشر '.$this->ago(intdiv($days, 365), 'سنة', 'سنتين', 'سنوات', 'سنة');
    }

    private function isScheduled(TrainingProgram $program): bool
    {
        return $program->status !== ProgramStatus::Archived
            && $program->published_at !== null
            && $program->published_at->greaterThan(now());
    }

    private function isCapacityFull(TrainingProgram $program): bool
    {
        if ($program->capacity === null) {
            return false;
        }

        return $this->approvedCount($program) >= $program->capacity;
    }

    private function ago(int $count, string $one, string $two, string $few, string $many): string
    {
        return match (true) {
            $count === 1 => 'منذ '.$one,
            $count === 2 => 'منذ '.$two,
            $count >= 3 && $count <= 10 => 'منذ '.$count.' '.$few,
            default => 'منذ '.$count.' '.$many,
        };
    }
}
