<?php

namespace App\Filament\Concerns;

use App\Models\AttendanceLiveSession;
use App\Services\AttendanceLiveSessionService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait InteractsWithAttendanceLiveSession
{
    public function startAttendanceLiveSession(): void
    {
        $admin = auth()->user();

        if ($admin === null) {
            return;
        }

        $owner = $this->getOwnerRecord();

        try {
            app(AttendanceLiveSessionService::class)->startSession($owner, $admin);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('تعذّر فتح جلسة الحضور')
                ->body(collect($exception->errors())->flatten()->first() ?? 'حدث خطأ غير متوقع.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('تم فتح جلسة حضور مباشرة')
            ->body('لدى المستفيدين 5 دقائق لتسجيل حضورهم من بوابتهم.')
            ->success()
            ->send();
    }

    public function activeAttendanceSession(): ?AttendanceLiveSession
    {
        return app(AttendanceLiveSessionService::class)->activeSessionFor($this->getOwnerRecord());
    }

    public function attendanceLiveSessionTablePollInterval(): ?string
    {
        $session = $this->activeAttendanceSession();

        return ($session !== null && $session->isActive()) ? '1s' : null;
    }

    public function attendanceLiveSessionCountdownLabel(): string
    {
        $session = $this->activeAttendanceSession();

        if ($session === null || ! $session->isActive()) {
            return '';
        }

        $remaining = $session->remainingSeconds();
        $minutes = intdiv($remaining, 60);
        $seconds = $remaining % 60;

        return sprintf('جلسة حضور مفتوحة — %d:%02d', $minutes, $seconds);
    }

    public function makeAttendanceLiveSessionCountdownAction(): Action
    {
        return Action::make('liveSessionCountdown')
            ->label(fn (): string => $this->attendanceLiveSessionCountdownLabel())
            ->icon('heroicon-o-signal')
            ->color('success')
            ->disabled()
            ->visible(fn (): bool => $this->activeAttendanceSession()?->isActive() ?? false);
    }

    abstract protected function getOwnerRecord(): Model;
}
