<?php

namespace App\Filament\Concerns;

use App\Filament\Support\TrainingEntityFormSupport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Html;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * زر تعديل واحد لكل بطاقة في تبويب المعلومات يفتح نافذة بكل حقول البطاقة.
 */
trait HasInlineEntityViewEditing
{
    public function canInlineEditEntityView(): bool
    {
        return auth()->user()?->can('update', $this->getRecord()) ?? false;
    }

    /**
     * @param  callable(): array{stats?: array<int, array<string, mixed>>, sections?: array<int, array<string, mixed>>}  $resolvePresented
     */
    protected function renderEntityViewPanel(callable $resolvePresented): Html
    {
        return Html::make(function () use ($resolvePresented): HtmlString {
            $presented = $resolvePresented();

            return new HtmlString(
                View::make('filament.components.entity-view-panel', [
                    ...$presented,
                    'editable' => $this->canInlineEditEntityView(),
                ])->render(),
            );
        })
            ->columnSpanFull()
            ->key(fn (): string => $this->entityViewPanelStateKey());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    abstract protected function getInlineEditableFields(): array;

    /**
     * @return array<string, string>
     */
    protected function getInlineEditableFieldLabels(): array
    {
        return $this->getSettingsFieldLabels();
    }

    public function editEntityFieldAction(): Action
    {
        return Action::make('editEntityField')
            ->modalHeading(fn (array $arguments): string => 'تعديل: '.$this->resolveInlineEditLabel((string) ($arguments['field'] ?? '')))
            ->modalWidth(function (array $arguments): Width {
                $field = (string) ($arguments['field'] ?? '');

                if ($field === 'schedule') {
                    return Width::ThreeExtraLarge;
                }

                if (in_array($field, ['cover', 'image'], true)) {
                    return Width::TwoExtraLarge;
                }

                if ($field === 'description') {
                    return Width::ThreeExtraLarge;
                }

                return Width::Large;
            })
            ->modalSubmitActionLabel('حفظ')
            ->modalCancelActionLabel('إلغاء')
            ->fillForm(fn (array $arguments): array => $this->resolveInlineEditFormStateForField((string) ($arguments['field'] ?? '')))
            ->schema(fn (array $arguments): array => $this->getInlineEditableFields()[(string) ($arguments['field'] ?? '')] ?? [])
            ->action(function (Action $action, array $data, array $arguments): void {
                $data = array_merge($action->getRawData(), $data);

                $this->commitInlineEntityFieldEdit((string) ($arguments['field'] ?? ''), $data);
            });
    }

    protected function entityViewPanelStateKey(): string
    {
        $record = $this->getRecord();

        return 'kafaat-entity-view-'.$record->getKey().'-'.($record->updated_at?->getTimestamp() ?? 0);
    }

    protected function resolveInlineEditLabel(string $field): string
    {
        return $this->getInlineEditableFieldLabels()[$field] ?? $field;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveInlineEditFormState(): array
    {
        return array_merge(
            $this->buildInlineEditFormState(),
            $this->resolveInlineEditFormStateOverrides(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveInlineEditFormStateForField(string $field): array
    {
        $fieldState = $this->resolveInlineEditFormStateForFieldFromRecord($field);

        if ($fieldState !== null) {
            return $fieldState;
        }

        return $this->resolveInlineEditFormState();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveInlineEditFormStateForFieldFromRecord(string $field): ?array
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildInlineEditFormState(): array
    {
        $this->fillSettingsForm();

        $data = $this->form->getState();
        $raw = $this->form->getRawState();

        if (! is_array($data)) {
            $data = [];
        }

        if (! is_array($raw)) {
            $raw = [];
        }

        return TrainingEntityFormSupport::mergeNonDehydratedFormFlags($data, $raw, [
            'is_linked_to_path',
            'capacity_unlimited',
            'notify_audience',
            'publish_immediately',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveInlineEditFormStateOverrides(): array
    {
        return [];
    }

  /**
   * @param  array<string, mixed>  $data
   */
    public function commitInlineEntityFieldEdit(string $field, array $data): void
    {
        abort_unless($this->canInlineEditEntityView(), 403);
        abort_if(! array_key_exists($field, $this->getInlineEditableFields()), 404);

        try {
            $this->pendingInlineEditOverrides = null;
            $this->primePendingInlineEditOverrides($data);

            $this->fillSettingsForm();
            $current = is_array($this->form->getRawState()) ? $this->form->getRawState() : [];
            $this->form->fill(array_merge($current, $data, $this->pendingInlineEditOverrides ?? []));
            $this->absorbLiveFormStateIntoPendingOverrides($field);
            $this->saveTrainingEntitySettings();
            $this->afterInlineEntityFieldEdited($field);
            $this->forceRender();
        } catch (Halt) {
            $this->pendingInlineEditOverrides = null;

            return;
        } catch (ValidationException $exception) {
            $this->pendingInlineEditOverrides = null;

            throw $exception;
        } catch (Throwable $exception) {
            $this->pendingInlineEditOverrides = null;

            Notification::make()
                ->title('تعذّر حفظ التعديل')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $pendingInlineEditOverrides = null;

    /**
     * @param  array<string, mixed>  $submitted
     */
    protected function primePendingInlineEditOverrides(array $submitted): void
    {
        unset($submitted['schedule_calendar'], $submitted['publication_status_display']);

        $this->pendingInlineEditOverrides = $submitted;

        if (isset($this->pendingInlineEditOverrides['is_linked_to_path'])) {
            $this->pendingInlineEditOverrides['is_linked_to_path'] = (bool) $this->pendingInlineEditOverrides['is_linked_to_path'];

            if (! $this->pendingInlineEditOverrides['is_linked_to_path']) {
                $this->pendingInlineEditOverrides['learning_path_id'] = null;
            }
        }
    }

    protected function absorbLiveFormStateIntoPendingOverrides(string $field): void
    {
        $raw = $this->form->getRawState();

        if (! is_array($raw)) {
            return;
        }

        $keys = match ($field) {
            'schedule' => [
                'start_date',
                'end_date',
                'registration_start',
                'registration_end',
                'weekdays',
                'published_at',
                'publish_immediately',
            ],
            'overview' => [
                'title',
                'program_kind',
                'is_linked_to_path',
                'learning_path_id',
                'published_at',
                'publish_immediately',
            ],
            'enrollment' => [
                'capacity',
                'capacity_unlimited',
                'auto_accept_registrations',
                'notify_audience',
            ],
            'team' => [
                'owner_id',
                'assigned_to',
                'editors',
            ],
            'description' => [
                'description',
            ],
            'account' => [
                'name',
                'email',
                'phone',
                'password',
                'platform_role',
                'is_active',
                'notify_email',
            ],
            default => [],
        };

        foreach ($keys as $key) {
            if (! array_key_exists($key, $raw)) {
                continue;
            }

            $this->pendingInlineEditOverrides ??= [];

            if (
                array_key_exists($key, $this->pendingInlineEditOverrides)
                && filled($this->pendingInlineEditOverrides[$key])
                && blank($raw[$key])
            ) {
                continue;
            }

            $this->pendingInlineEditOverrides[$key] = $raw[$key];
        }

        if (isset($this->pendingInlineEditOverrides['is_linked_to_path'])) {
            $this->pendingInlineEditOverrides['is_linked_to_path'] = (bool) $this->pendingInlineEditOverrides['is_linked_to_path'];

            if (! $this->pendingInlineEditOverrides['is_linked_to_path']) {
                $this->pendingInlineEditOverrides['learning_path_id'] = null;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mergePendingInlineEditOverrides(array $data): array
    {
        if ($this->pendingInlineEditOverrides === null) {
            return $data;
        }

        $data = array_merge($data, $this->pendingInlineEditOverrides);
        $this->pendingInlineEditOverrides = null;

        return $data;
    }

    protected function afterInlineEntityFieldEdited(string $field): void
    {
        $this->getRecord()->refresh();
    }

    protected function getActions(): array
    {
        $actions = parent::getActions();

        if ($this->canInlineEditEntityView()) {
            array_unshift($actions, $this->editEntityFieldAction());
        }

        return $actions;
    }
}
