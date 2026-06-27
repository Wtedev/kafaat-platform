<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;
use Throwable;

trait HasTrainingEntityPublicationActions
{
    public function publishEntityNowAction(): Action
    {
        return Action::make('publishEntityNow')
            ->label('نشر الآن')
            ->requiresConfirmation()
            ->modalHeading('نشر الآن')
            ->modalDescription('سيتم نشر المحتوى للعامة فوراً.')
            ->modalSubmitActionLabel('نشر الآن')
            ->modalCancelActionLabel('إلغاء')
            ->visible(fn (): bool => $this->canPublishEntityNow())
            ->action(function (): void {
                $this->publishEntityNow();
            });
    }

    protected function canPublishEntityNow(): bool
    {
        if (! $this->canInlineEditEntityView()) {
            return false;
        }

        return method_exists($this, 'recordIsPublishedForPublicationActions')
            ? ! $this->recordIsPublishedForPublicationActions()
            : true;
    }

    protected function publishEntityNow(): void
    {
        abort_unless($this->canPublishEntityNow(), 403);

        try {
            $this->fillSettingsForm();
            $current = is_array($this->form->getRawState()) ? $this->form->getRawState() : [];
            $this->form->fill(array_merge($current, [
                'publish_immediately' => true,
            ]));
            $this->saveTrainingEntitySettings(false);
            $this->forceRender();

            Notification::make()
                ->success()
                ->title('تم النشر بنجاح')
                ->send();
        } catch (Halt) {
            return;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Notification::make()
                ->title('تعذّر النشر')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }
}
