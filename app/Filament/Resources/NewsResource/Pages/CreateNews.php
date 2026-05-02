<?php

namespace App\Filament\Resources\NewsResource\Pages;

use App\Filament\Resources\NewsResource;
use App\Filament\Resources\NewsResource\NewsPublicationFilamentActions;
use App\Filament\Resources\Pages\BaseCreateRecord;
use App\Services\News\NewsPublicationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CreateNews extends BaseCreateRecord
{
    protected static string $resource = NewsResource::class;

    public bool $pendingPublishNow = false;

    public ?Carbon $pendingScheduleAt = null;

    public function form(Schema $schema): Schema
    {
        return NewsResource::createForm($schema);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($this->pendingPublishNow) {
            $data['published_at'] = now();
        } elseif ($this->pendingScheduleAt instanceof Carbon) {
            if ($this->pendingScheduleAt->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages([
                    'publish_at' => NewsPublicationService::schedulePublishMustBeFutureMessage(),
                ]);
            }
            $data['published_at'] = $this->pendingScheduleAt;
        } else {
            $data['published_at'] = null;
        }

        return $data;
    }

    protected function getCreateFormAction(): Action
    {
        $action = parent::getCreateFormAction();

        return $action->label('مسودة')->color('gray');
    }

    protected function getFormActions(): array
    {
        return [
            ...($this->shouldOfferPublicationActions() ? [
                $this->getCreateAndPublishFormAction(),
                $this->getCreateAndScheduleFormAction(),
                $this->getCreateFormAction(),
            ] : [
                $this->getCreateFormAction(),
            ]),
            $this->getCancelFormAction(),
        ];
    }

    protected function shouldOfferPublicationActions(): bool
    {
        return auth()->user()?->can('manage_news') ?? false;
    }

    protected function getCreateAndPublishFormAction(): Action
    {
        return Action::make('createAndPublish')
            ->label('نشر')
            ->icon('heroicon-o-megaphone')
            ->color('success')
            ->action(function (): void {
                $this->pendingPublishNow = true;
                try {
                    $this->create(another: false);
                } finally {
                    $this->pendingPublishNow = false;
                }
            });
    }

    protected function getCreateAndScheduleFormAction(): Action
    {
        return Action::make('createAndSchedule')
            ->label('جدولة')
            ->icon('heroicon-o-clock')
            ->color('gray')
            ->form([
                NewsPublicationFilamentActions::schedulePublishAtPicker(),
            ])
            ->action(function (array $data): void {
                $this->pendingScheduleAt = Carbon::parse($data['publish_at'])->timezone(config('app.timezone'));
                try {
                    $this->create(another: false);
                } finally {
                    $this->pendingScheduleAt = null;
                }
            });
    }

    protected function getCreatedNotification(): ?Notification
    {
        if ($this->pendingScheduleAt instanceof Carbon) {
            $at = $this->pendingScheduleAt->copy()->timezone(config('app.timezone'));

            return Notification::make()
                ->success()
                ->title('تم إنشاء الخبر وجدولته للنشر.')
                ->body('موعد الظهور للعامة: '.$at->format('Y/m/d H:i').' ('.NewsResource::platformTimezoneLabel().').');
        }

        if ($this->pendingPublishNow) {
            return Notification::make()
                ->success()
                ->title('تم إنشاء الخبر ونشره بنجاح.');
        }

        return Notification::make()
            ->success()
            ->title('تم حفظ الخبر كمسودة.');
    }
}
