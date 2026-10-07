<?php

namespace App\Filament\Support;

use App\Data\Certificates\EligibilityResult;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\User;
use App\Models\VolunteerRegistration;
use App\Services\Certificates\CertificateEligibilityService;
use App\Services\Certificates\CertificateIssuanceService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CertificateManualActions
{
    public static function canUse(): bool
    {
        $user = auth()->user();

        return $user instanceof User && app(CertificateIssuanceService::class)->canDecide($user);
    }

    /**
     * @return list<TextColumn>
     */
    public static function helperColumns(): array
    {
        return [
            TextColumn::make('certificate_helper_attendance')
                ->label('نسبة الحضور')
                ->getStateUsing(function (Model $record): string {
                    if ($record instanceof VolunteerRegistration || ! method_exists($record, 'effectiveAttendancePercentage')) {
                        return '—';
                    }

                    return RegistrationFilamentTableSupport::formatPercentage($record->effectiveAttendancePercentage());
                }),
            TextColumn::make('certificate_helper_score')
                ->label('الدرجة')
                ->getStateUsing(function (Model $record): string {
                    $score = $record->getAttribute('score');

                    return $score !== null && $score !== '' ? (string) $score : '—';
                }),
            TextColumn::make('certificate_helper_hours')
                ->label('الساعات المعتمدة')
                ->getStateUsing(function (Model $record): string {
                    if (! $record instanceof VolunteerRegistration) {
                        return '—';
                    }

                    return number_format($record->getApprovedHours(), 1);
                }),
        ];
    }

    /**
     * @return list<Action>
     */
    public static function recordActions(): array
    {
        return [
            Action::make('markCertificateEligible')
                ->label('مؤهل للشهادة')
                ->icon('heroicon-o-academic-cap')
                ->color('success')
                ->visible(fn (Model $record): bool => self::canUse() && ! self::hasActiveCertificate($record))
                ->requiresConfirmation()
                ->modalHeading('تأهيل للشهادة')
                ->modalDescription('سيُصدر النظام الشهادة الآن باستخدام تصميم النشاط، دون التحقق من شروط الأحقية.')
                ->modalSubmitActionLabel('إصدار الشهادة')
                ->action(function (Model $record): void {
                    self::issueOne($record);
                }),
            Action::make('revokeCertificateEligibility')
                ->label('إلغاء الأهلية')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Model $record): bool => self::canUse() && self::hasActiveCertificate($record))
                ->requiresConfirmation()
                ->modalHeading('إلغاء أهلية الشهادة')
                ->modalDescription('ستُلغى الشهادة وتظهر «ملغاة» في صفحة التحقق.')
                ->modalSubmitActionLabel('إلغاء الأهلية')
                ->form([
                    Textarea::make('reason')
                        ->label('سبب الإلغاء')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (Model $record, array $data): void {
                    $actor = auth()->user();
                    if (! $actor instanceof User) {
                        return;
                    }

                    try {
                        app(CertificateIssuanceService::class)->revokeForRegistration($record, $actor, (string) ($data['reason'] ?? ''));
                        Notification::make()->title('أُلغيت الشهادة')->success()->send();
                    } catch (ValidationException $exception) {
                        Notification::make()->title(self::firstError($exception))->danger()->send();
                    }
                }),
        ];
    }

    public static function bulkMarkEligible(): BulkAction
    {
        return BulkAction::make('markCertificateEligibleBulk')
            ->label('مؤهل للشهادة')
            ->icon('heroicon-o-academic-cap')
            ->visible(fn (): bool => self::canUse())
            ->requiresConfirmation()
            ->modalHeading('تأهيل المحددين للشهادة')
            ->modalDescription(function (Collection $records): string {
                $current = 'ستُصدر الشهادة لكل صف محدد باستخدام تصميم النشاط، دون التحقق من شروط الأحقية.';
                $selected = $records->filter(fn (mixed $record): bool => $record instanceof Model)->values();
                if ($selected->isEmpty()) {
                    return $current;
                }

                $results = app(CertificateEligibilityService::class)->evaluateMany($selected);
                $outside = $selected->filter(function (Model $record) use ($results): bool {
                    $result = $results->get($record->getKey());

                    return ! $result instanceof EligibilityResult || ! $result->eligible;
                })->count();

                if ($outside === 0) {
                    return $current;
                }

                return $outside.' من المحددين لا يحققون شروط الأحقية في القالب. سيُصدر لهم أيضاً لأن التأهيل يدوي.';
            })
            ->modalSubmitActionLabel('إصدار الشهادات')
            ->action(function (Collection $records): void {
                $issued = 0;
                foreach ($records as $record) {
                    if (! $record instanceof Model) {
                        continue;
                    }
                    if (! self::issueOne($record)) {
                        return;
                    }
                    $issued++;
                }

                Notification::make()
                    ->title($issued > 0 ? 'صدرت الشهادات المحددة' : 'لا توجد شهادات جديدة')
                    ->success()
                    ->send();
            });
    }

    private static function issueOne(Model $record): bool
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return false;
        }

        try {
            $before = self::hasActiveCertificate($record);
            app(CertificateIssuanceService::class)->markEligible($record, $actor);
            Notification::make()
                ->title($before ? 'الشهادة موجودة مسبقاً' : 'صدرت الشهادة')
                ->success()
                ->send();

            return true;
        } catch (ValidationException $exception) {
            Notification::make()->title(self::firstError($exception))->danger()->send();

            return false;
        }
    }

    private static function hasActiveCertificate(Model $record): bool
    {
        $record->loadMissing('user');
        $user = $record->getAttribute('user');
        if (! $user instanceof User) {
            return false;
        }

        $owner = match (true) {
            $record instanceof ProgramRegistration => $record->trainingProgram()->first(),
            $record instanceof PathRegistration => $record->learningPath()->first(),
            $record instanceof VolunteerRegistration => $record->opportunity()->first(),
            default => null,
        };

        if (! $owner instanceof Model || ! method_exists($owner, 'certificates')) {
            return false;
        }

        return $owner->certificates()
            ->active()
            ->where('user_id', $user->id)
            ->exists();
    }

    private static function firstError(ValidationException $exception): string
    {
        $message = collect($exception->errors())->flatten()->first();

        return is_string($message) && $message !== '' ? $message : 'تعذر تنفيذ الإجراء.';
    }
}
