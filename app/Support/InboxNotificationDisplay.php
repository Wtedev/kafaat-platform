<?php

namespace App\Support;

use App\Enums\InboxNotificationType;
use App\Models\InboxNotification;
use App\Models\TrainingProgram;
use App\Models\User;

/**
 * تجهيز نص التنبيه للعرض مع استخراج رابط واتساب كزر منفصل.
 */
final class InboxNotificationDisplay
{
    /**
     * @return array{heading: string, message: string|null, whatsapp_url: string|null}
     */
    public static function present(InboxNotification $notification, ?User $viewer = null): array
    {
        $raw = is_string($notification->message) ? $notification->message : null;
        $whatsapp = self::whatsappUrlFromContext($notification);

        if ($whatsapp === null && is_string($raw)) {
            [$raw, $extracted] = self::stripWhatsappFromMessage($raw);
            $whatsapp = $extracted;
        }

        if ($whatsapp === null && $viewer !== null) {
            $whatsapp = self::whatsappUrlFromProgramContext($notification, $viewer);
        }

        $message = is_string($raw) ? trim($raw) : null;
        if ($message === '') {
            $message = null;
        }

        return [
            'heading' => self::heading($notification),
            'message' => $message,
            'whatsapp_url' => $whatsapp,
        ];
    }

    /**
     * Small top-line label for notification rows: prefer title; fall back to type label.
     * Avoids duplicating type label when it matches the title.
     */
    public static function heading(InboxNotification $notification): string
    {
        $title = is_string($notification->title) ? trim($notification->title) : '';
        if ($title !== '') {
            return $title;
        }

        return $notification->type->arabicLabel();
    }

    private static function whatsappUrlFromContext(InboxNotification $notification): ?string
    {
        $context = is_array($notification->context) ? $notification->context : [];
        $url = $context['whatsapp_url'] ?? null;

        return is_string($url) ? TrainingProgramExtrasSupport::normalizeWhatsappUrl($url) : null;
    }

    private static function whatsappUrlFromProgramContext(InboxNotification $notification, User $viewer): ?string
    {
        if ($notification->type !== InboxNotificationType::RegistrationApproved) {
            return null;
        }

        $context = is_array($notification->context) ? $notification->context : [];
        if (($context['resource'] ?? null) !== 'training_program') {
            return null;
        }

        $id = isset($context['id']) ? (int) $context['id'] : 0;
        if ($id <= 0) {
            return null;
        }

        $program = TrainingProgram::query()->find($id);
        if ($program === null) {
            return null;
        }

        $viewer->loadMissing('profile');

        return TrainingProgramExtrasSupport::whatsappGroupUrlFor($program, $viewer);
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    public static function stripWhatsappFromMessage(string $message): array
    {
        $url = null;
        if (preg_match('#https?://(?:chat\.)?whatsapp\.com/[^\s]+#i', $message, $m) === 1) {
            $url = TrainingProgramExtrasSupport::normalizeWhatsappUrl(rtrim($m[0], ")\"]'.,"));
        }

        $cleaned = preg_replace(
            '#(?:\r?\n)*رابط مجموعة الواتساب\s*:?\s*(?:\r?\n)?\s*https?://(?:chat\.)?whatsapp\.com/[^\s]+#iu',
            '',
            $message,
        );
        $cleaned = is_string($cleaned) ? trim($cleaned) : trim($message);

        if ($url === null && preg_match('#https?://(?:chat\.)?whatsapp\.com/[^\s]+#i', $cleaned) === 1) {
            // Keep message; URL already captured or missing
        }

        return [$cleaned, $url];
    }
}
