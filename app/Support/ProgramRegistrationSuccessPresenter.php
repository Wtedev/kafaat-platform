<?php

namespace App\Support;

use App\Enums\RegistrationStatus;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;

/**
 * بيانات صفحة نجاح تسجيل البرنامج العامة.
 */
final class ProgramRegistrationSuccessPresenter
{
    /**
     * @return array{
     *     approved: bool,
     *     pending: bool,
     *     pending_message: string,
     *     in_person: bool,
     *     venue_label: string|null,
     *     whatsapp_url: string|null,
     *     show_qr: bool,
     *     qr_data_uri: string|null,
     *     pass_code: string,
     *     notifications_settings_url: string
     * }
     */
    /**
     * Code the prep officer scans at the door (KAFAAT-P{program}-R{registration}).
     */
    public static function passCode(TrainingProgram $program, ProgramRegistration $registration): string
    {
        return sprintf('KAFAAT-P%d-R%d', (int) $program->id, (int) $registration->id);
    }

    public static function passUrl(TrainingProgram $program, ProgramRegistration $registration): string
    {
        return route('public.programs.registered', [
            'trainingProgram' => $program->slug,
            'registration' => $registration->getKey(),
        ], absolute: true);
    }

    /**
     * What the attendance QR encodes: the pass page URL with the pass code as fragment.
     */
    public static function qrPayload(TrainingProgram $program, ProgramRegistration $registration): string
    {
        return self::passUrl($program, $registration).'#'.self::passCode($program, $registration);
    }

    public static function present(TrainingProgram $program, ProgramRegistration $registration, User $user): array
    {
        $user->loadMissing('profile');
        $approved = $registration->status === RegistrationStatus::Approved
            || $registration->status === RegistrationStatus::Completed;
        $inPerson = $program->delivery_mode?->hasPhysicalComponent() ?? false;
        $whatsapp = $approved
            ? TrainingProgramExtrasSupport::whatsappGroupUrlFor($program, $user)
            : null;

        $passCode = self::passCode($program, $registration);

        $showQr = $approved && $inPerson;

        return [
            'approved' => $approved,
            'pending' => $registration->status === RegistrationStatus::Pending,
            'pending_message' => TrainingProgramExtrasSupport::registrationReceivedNotice($program)
                ?? 'طلبك قيد المراجعة. سنُبلغك بنتيجة القبول عبر المنصة والبريد الإلكتروني.',
            'in_person' => $inPerson,
            'venue_label' => $inPerson
                ? (filled($program->venue) ? (string) $program->venue : null)
                : null,
            'whatsapp_url' => $whatsapp,
            'show_qr' => $showQr,
            'qr_data_uri' => $showQr ? QrCodeImage::dataUri(self::qrPayload($program, $registration), 300) : null,
            'pass_code' => $passCode,
            'notifications_settings_url' => route('portal.notifications.settings'),
        ];
    }
}
