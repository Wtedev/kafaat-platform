<?php

namespace App\Services\Certificates;

use App\Enums\CertificatePdfStatus;
use App\Enums\CertificateTemplateStatus;
use App\Enums\RegistrationStatus;
use App\Jobs\GenerateCertificatePdfJob;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;
use App\Services\Inbox\InboxNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * المكان الوحيد المسموح فيه إنشاء شهادة.
 * السجل يُنشأ داخل معاملة قصيرة، وتوليد PDF وإشعار الوارد بعد الالتزام فقط.
 */
class CertificateIssuanceService
{
    public function __construct(
        private readonly CertificateEligibilityService $eligibility,
        private readonly InboxNotificationService $inboxNotifications,
    ) {}

    public function issueForProgramRegistration(
        ProgramRegistration $registration,
        ?User $issuedBy = null,
        bool $exceptional = false,
        ?string $overrideReason = null,
        bool $automatic = false,
    ): ?Certificate {
        return $this->issueForRegistration($registration, $issuedBy, $exceptional, $overrideReason, $automatic);
    }

    public function issueForRegistration(
        Model $registration,
        ?User $issuedBy = null,
        bool $exceptional = false,
        ?string $overrideReason = null,
        bool $automatic = false,
    ): ?Certificate {
        $owner = $this->ownerOf($registration);
        $user = $registration->getAttribute('user') ?? $registration->user()->first();

        if (! $user instanceof User || ! $owner instanceof Model) {
            return null;
        }

        return $this->issue(
            $user,
            $owner,
            $issuedBy,
            $exceptional,
            $overrideReason,
            $automatic,
        );
    }

    public function issueExceptional(Model $registration, User $admin, string $reason): Certificate
    {
        $reason = trim($reason);
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages([
                'reason' => 'الإصدار الاستثنائي للمدير فقط.',
            ]);
        }

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'سبب الإصدار الاستثنائي مطلوب.',
            ]);
        }

        $owner = $this->ownerOf($registration);
        $template = $owner?->certificateTemplate;
        if (! $this->templateIsReady($template)) {
            throw ValidationException::withMessages([
                'reason' => 'لم يُعتمد تصميم الشهادة بعد.',
            ]);
        }

        $certificate = $this->issueForRegistration($registration, $admin, true, $reason);
        if (! $certificate instanceof Certificate) {
            throw ValidationException::withMessages([
                'reason' => 'تعذر إصدار الشهادة.',
            ]);
        }

        return $certificate;
    }

    public function revoke(Certificate $certificate, User $admin, string $reason): void
    {
        $reason = trim($reason);
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages([
                'reason' => 'إلغاء الشهادة للمدير فقط.',
            ]);
        }

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'سبب الإلغاء مطلوب.',
            ]);
        }

        if ($certificate->isRevoked()) {
            return;
        }

        $certificate->update([
            'revoked_at' => now(),
            'revoke_reason' => $reason,
        ]);

        activity()
            ->causedBy($admin)
            ->performedOn($certificate)
            ->event('certificate_revoked')
            ->withProperties(['revoke_reason' => $reason])
            ->log('أُلغيت الشهادة');
    }

    public function issue(
        User $user,
        Model $certificateable,
        ?User $issuedBy = null,
        bool $exceptional = false,
        ?string $overrideReason = null,
        bool $automatic = false,
    ): ?Certificate {
        if ($exceptional && ($issuedBy === null || ! $issuedBy->isAdmin() || trim((string) $overrideReason) === '')) {
            return null;
        }

        $template = method_exists($certificateable, 'certificateTemplate')
            ? $certificateable->certificateTemplate
            : null;
        if (! $this->templateIsReady($template)) {
            return null;
        }

        if ($automatic && ! $template->auto_issue) {
            return null;
        }

        if (! $exceptional) {
            $registration = $this->registrationFor($user, $certificateable);
            if ($registration === null || ! $this->eligibility->evaluate($registration)->eligible) {
                return null;
            }
        }

        $existing = $this->activeCertificate($user, $certificateable);
        if ($existing instanceof Certificate) {
            return $existing;
        }

        $overrideReason = $exceptional ? trim((string) $overrideReason) : null;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return $this->createCertificate(
                    $user,
                    $certificateable,
                    $issuedBy,
                    $template,
                    $exceptional,
                    $overrideReason,
                );
            } catch (UniqueConstraintViolationException) {
                $existing = $this->activeCertificate($user, $certificateable);
                if ($existing instanceof Certificate) {
                    return $existing;
                }
            }
        }

        return $this->activeCertificate($user, $certificateable);
    }

    private function createCertificate(
        User $user,
        Model $certificateable,
        ?User $issuedBy,
        ?CertificateTemplate $template,
        bool $exceptional,
        ?string $overrideReason,
    ): Certificate {
        return DB::transaction(function () use ($user, $certificateable, $issuedBy, $template, $exceptional, $overrideReason): Certificate {
            $existing = $this->activeCertificate($user, $certificateable);
            if ($existing instanceof Certificate) {
                return $existing;
            }

            $attributes = [
                'user_id' => $user->id,
                'certificateable_type' => $certificateable->getMorphClass(),
                'certificateable_id' => $certificateable->getKey(),
                'certificate_template_id' => $template?->id,
                'template_version' => $template?->version,
                'certificate_number' => $this->generateCertificateNumber(),
                'verification_code' => $this->generateVerificationCode(),
                'pdf_status' => CertificatePdfStatus::Pending,
                'issued_at' => now(),
                'override_reason' => $exceptional ? $overrideReason : null,
                'overridden_by' => $exceptional ? $issuedBy?->id : null,
            ];
            $draft = new Certificate($attributes);
            $draft->setRelation('user', $user);
            $draft->setRelation('certificateable', $certificateable);
            $attributes['data_snapshot'] = CertificateSnapshotBuilder::build($draft);
            $certificate = Certificate::create($attributes);
            $certificate->setRelation('user', $user);
            $certificate->setRelation('certificateable', $certificateable);

            $this->completeApprovedRegistration($user, $certificateable);

            if ($exceptional) {
                activity()
                    ->causedBy($issuedBy)
                    ->performedOn($certificate)
                    ->event('exceptional_issue')
                    ->withProperties(['override_reason' => $overrideReason])
                    ->log('إصدار استثنائي للشهادة');
            }

            $certificateId = (int) $certificate->getKey();
            $issuedById = $issuedBy?->id;
            DB::afterCommit(function () use ($certificateId, $issuedById): void {
                GenerateCertificatePdfJob::dispatch($certificateId);

                $fresh = Certificate::query()->with('user')->find($certificateId);
                if ($fresh?->user === null) {
                    return;
                }

                $this->inboxNotifications->certificateIssued(
                    $fresh->user,
                    $fresh,
                    $issuedById !== null ? User::query()->find($issuedById) : null,
                );
            });

            return $certificate;
        });
    }

    private function templateIsReady(?CertificateTemplate $template): bool
    {
        return $template instanceof CertificateTemplate
            && $template->status === CertificateTemplateStatus::Ready;
    }

    private function registrationFor(User $user, Model $certificateable): ?Model
    {
        if ($certificateable instanceof TrainingProgram) {
            return ProgramRegistration::query()
                ->where('user_id', $user->id)
                ->where('training_program_id', $certificateable->getKey())
                ->first();
        }

        if ($certificateable instanceof LearningPath) {
            return PathRegistration::query()
                ->where('user_id', $user->id)
                ->where('learning_path_id', $certificateable->getKey())
                ->first();
        }

        if ($certificateable instanceof VolunteerOpportunity) {
            return VolunteerRegistration::query()
                ->where('user_id', $user->id)
                ->where('opportunity_id', $certificateable->getKey())
                ->first();
        }

        return null;
    }

    private function ownerOf(Model $registration): ?Model
    {
        if ($registration instanceof ProgramRegistration) {
            $registration->loadMissing(['user', 'trainingProgram']);

            return $registration->trainingProgram;
        }

        if ($registration instanceof PathRegistration) {
            $registration->loadMissing(['user', 'learningPath']);

            return $registration->learningPath;
        }

        if ($registration instanceof VolunteerRegistration) {
            $registration->loadMissing(['user', 'opportunity']);

            return $registration->opportunity;
        }

        return null;
    }

    private function completeApprovedRegistration(User $user, Model $certificateable): void
    {
        if ($certificateable instanceof TrainingProgram) {
            ProgramRegistration::query()
                ->where('user_id', $user->id)
                ->where('training_program_id', $certificateable->getKey())
                ->where('status', RegistrationStatus::Approved)
                ->update(['status' => RegistrationStatus::Completed]);

            return;
        }

        if ($certificateable instanceof LearningPath) {
            PathRegistration::query()
                ->where('user_id', $user->id)
                ->where('learning_path_id', $certificateable->getKey())
                ->where('status', RegistrationStatus::Approved)
                ->update([
                    'status' => RegistrationStatus::Completed,
                    'completed_at' => now(),
                ]);

            return;
        }

        if ($certificateable instanceof VolunteerOpportunity) {
            VolunteerRegistration::query()
                ->where('user_id', $user->id)
                ->where('opportunity_id', $certificateable->getKey())
                ->where('status', RegistrationStatus::Approved)
                ->update(['status' => RegistrationStatus::Completed]);
        }
    }

    private function activeCertificate(User $user, Model $certificateable): ?Certificate
    {
        return Certificate::query()
            ->active()
            ->where('user_id', $user->id)
            ->where('certificateable_type', $certificateable->getMorphClass())
            ->where('certificateable_id', $certificateable->getKey())
            ->first();
    }

    private function generateCertificateNumber(): string
    {
        do {
            $number = 'CERT-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
        } while (Certificate::query()->where('certificate_number', $number)->exists());

        return $number;
    }

    private function generateVerificationCode(): string
    {
        do {
            $code = bin2hex(random_bytes(16));
        } while (Certificate::query()->where('verification_code', $code)->exists());

        return $code;
    }
}
