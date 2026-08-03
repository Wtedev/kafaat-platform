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
        $service = app(AttendanceLiveSessionService::class);

        try {
            $before = $this->activeAttendanceSession();
            $session = $service->startSession($owner, $admin);

            if ($before !== null && $before->isActive() && $before->id === $session->id) {
                Notification::make()
                    ->title('جلسة التحضير مفتوحة بالفعل')
                    ->body($this->attendanceLiveSessionCountdownLabel())
                    ->warning()
                    ->send();

                return;
            }
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('تعذّر فتح جلسة الحضور')
                ->body(collect($exception->errors())->flatten()->first() ?? 'حدث خطأ غير متوقع.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('تم فتح التحضير')
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

        return sprintf('التحضير مفتوح — %d:%02d', $minutes, $seconds);
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
