<?php

namespace App\Support;

use App\Enums\ProgramDeliveryMode;
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
     *     in_person: bool,
     *     venue_label: string|null,
     *     whatsapp_url: string|null,
     *     show_qr: bool,
     *     qr_data_uri: string|null,
     *     pass_code: string,
     *     notifications_settings_url: string
     * }
     */
    public static function present(TrainingProgram $program, ProgramRegistration $registration, User $user): array
    {
        $user->loadMissing('profile');
        $approved = $registration->status === RegistrationStatus::Approved
            || $registration->status === RegistrationStatus::Completed;
        $inPerson = $program->delivery_mode?->hasPhysicalComponent() ?? false;
        $whatsapp = $approved
            ? TrainingProgramExtrasSupport::whatsappGroupUrlFor($program, $user)
            : null;

        $passCode = sprintf('KAFAAT-P%d-R%d', (int) $program->id, (int) $registration->id);
        $passUrl = route('public.programs.registered', [
            'trainingProgram' => $program->slug,
            'registration' => $registration->getKey(),
        ], absolute: true);

        $showQr = $approved && $inPerson;

        return [
            'approved' => $approved,
            'pending' => $registration->status === RegistrationStatus::Pending,
            'in_person' => $inPerson,
            'venue_label' => $inPerson
                ? (filled($program->venue) ? (string) $program->venue : null)
                : null,
            'whatsapp_url' => $whatsapp,
            'show_qr' => $showQr,
            'qr_data_uri' => $showQr ? QrCodeImage::dataUri($passUrl.'#'.$passCode, 300) : null,
            'pass_code' => $passCode,
            'notifications_settings_url' => route('portal.notifications.settings'),
        ];
    }
}
