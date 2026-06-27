<?php

namespace App\Services\Privacy;

use App\Data\Privacy\PrivacyAccessResponseSnapshot;

use App\Models\Certificate;
use App\Models\PrivacyPolicyAcknowledgement;
use App\Models\ProgramAttendance;
use App\Models\ProgramRegistration;
use App\Models\User;
use App\Models\UserDocument;
use App\Services\CandidatePool\CandidatePoolConsentVersionService;
use App\Services\Documents\CvDocumentService;

final class PrivacyAccessResponseBuilder
{
    public function __construct(
        private readonly CvDocumentService $cvDocumentService,
    ) {}

    public function buildFor(User $user): PrivacyAccessResponseSnapshot
    {
        $user->loadMissing(['profile', 'candidatePoolPreference']);

        $categories = [
            $this->accountCategory($user),
            $this->profileCategory($user),
            $this->policyCategory($user),
            $this->candidatePoolCategory($user),
            $this->registrationsCategory($user),
            $this->attendanceCategory($user),
            $this->certificatesCategory($user),
            $this->documentsCategory($user),
        ];

        return new PrivacyAccessResponseSnapshot(
            categories: array_values(array_filter($categories)),
            generatedAt: now()->toIso8601String(),
        );
    }

    /**
     * @return array{category: string, summary: string, sources: list<string>}|null
     */
    private function accountCategory(User $user): array
    {
        return [
            'category' => 'بيانات الحساب',
            'summary' => 'الاسم، البريد، الجوال، حالة الحساب ('.($user->account_status?->label() ?? '—').').',
            'sources' => ['تسجيل الحساب', 'الملف الشخصي'],
        ];
    }

    /**
     * @return array{category: string, summary: string, sources: list<string>}|null
     */
    private function profileCategory(User $user): ?array
    {
        if ($user->profile === null) {
            return null;
        }

        return [
            'category' => 'الملف الشخصي',
            'summary' => 'تاريخ الميلاد والمدينة والبيانات المهنية المسجلة في ملفك.',
            'sources' => ['الملف الشخصي'],
        ];
    }

    /**
     * @return array{category: string, summary: string, sources: list<string>}|null
     */
    private function policyCategory(User $user): ?array
    {
        $count = PrivacyPolicyAcknowledgement::query()->where('user_id', $user->id)->count();
        if ($count === 0) {
            return null;
        }

        return [
            'category' => 'إقرارات سياسة الخصوصية',
            'summary' => "سجلات إقرار بسياسة الخصوصية ({$count}).",
            'sources' => ['سياسة الخصوصية'],
        ];
    }

    /**
     * @return array{category: string, summary: string, sources: list<string>}|null
     */
    private function candidatePoolCategory(User $user): ?array
    {
        $preference = $user->candidatePoolPreference;
        if ($preference === null) {
            return null;
        }

        $status = $preference->current_status?->label() ?? 'غير محدد';

        return [
            'category' => 'موافقة قاعدة المرشحين',
            'summary' => "الحالة الحالية: {$status}.",
            'sources' => ['قاعدة المرشحين'],
        ];
    }

    /**
     * @return array{category: string, summary: string, sources: list<string>}|null
     */
    private function registrationsCategory(User $user): ?array
    {
        $count = ProgramRegistration::query()->where('user_id', $user->id)->count();
        if ($count === 0) {
            return null;
        }

        return [
            'category' => 'التسجيلات',
            'summary' => "تسجيلات البرامج والمسارات ({$count}).",
            'sources' => ['البرامج التدريبية', 'المسارات'],
        ];
    }

    /**
     * @return array{category: string, summary: string, sources: list<string>}|null
     */
    private function attendanceCategory(User $user): ?array
    {
        $count = ProgramAttendance::query()
            ->whereHas('registration', fn ($q) => $q->where('user_id', $user->id))
            ->count();

        if ($count === 0) {
            return null;
        }

        return [
            'category' => 'الحضور',
            'summary' => "سجلات حضور مرتبطة بتسجيلاتك ({$count}).",
            'sources' => ['الحضور'],
        ];
    }

    /**
     * @return array{category: string, summary: string, sources: list<string>}|null
     */
    private function certificatesCategory(User $user): ?array
    {
        $count = Certificate::query()->where('user_id', $user->id)->count();
        if ($count === 0) {
            return null;
        }

        return [
            'category' => 'الشهادات',
            'summary' => "شهادات صادرة لك ({$count}). تبقى قابلة للتحقق دون تعديل تلقائي عند تصحيح بيانات الحساب.",
            'sources' => ['الشهادات'],
        ];
    }

    /**
     * @return array{category: string, summary: string, sources: list<string>}|null
     */
    private function documentsCategory(User $user): ?array
    {
        $cv = $this->cvDocumentService->currentCv($user);
        $docCount = UserDocument::query()->where('user_id', $user->id)->where('status', 'active')->count();

        if ($cv === null && $docCount === 0) {
            return null;
        }

        $parts = [];
        if ($cv !== null) {
            $parts[] = 'سيرة ذاتية نشطة';
        }
        if ($docCount > 0) {
            $parts[] = "وثائق أخرى ({$docCount})";
        }

        return [
            'category' => 'الوثائق',
            'summary' => implode('، ', $parts).'.',
            'sources' => ['السيرة الذاتية', 'الوثائق'],
        ];
    }
}
