<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

class TrainingScheduleCalendar extends Field
{
    protected string $view = 'filament.forms.components.training-schedule-calendar';

    protected bool | Closure $showRegistrationRange = true;

    protected bool | Closure $programHasEndDate = true;

    protected bool | Closure $showWeekdayPicker = true;

    protected bool | Closure $showPublishSchedule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('تقويم المواعيد');
        $this->hiddenLabel();
        $this->dehydrated(false);
        $this->columnSpanFull();
        $this->extraFieldWrapperAttributes(['class' => 'fi-training-schedule-field']);
    }

    public function showWeekdayPicker(bool | Closure $condition = true): static
    {
        $this->showWeekdayPicker = $condition;

        return $this;
    }

    public function getShowWeekdayPicker(): bool
    {
        return (bool) $this->evaluate($this->showWeekdayPicker);
    }

    public function showRegistrationRange(bool | Closure $condition = true): static
    {
        $this->showRegistrationRange = $condition;

        return $this;
    }

    public function programHasEndDate(bool | Closure $condition = true): static
    {
        $this->programHasEndDate = $condition;

        return $this;
    }

    public function getShowRegistrationRange(): bool
    {
        return (bool) $this->evaluate($this->showRegistrationRange);
    }

    public function getProgramHasEndDate(): bool
    {
        return (bool) $this->evaluate($this->programHasEndDate);
    }

    public function getRegistrationStartPath(): string
    {
        return $this->resolveRelativeStatePath('registration_start');
    }

    public function getRegistrationEndPath(): string
    {
        return $this->resolveRelativeStatePath('registration_end');
    }

    public function getProgramStartPath(): string
    {
        return $this->resolveRelativeStatePath('start_date');
    }

    public function getProgramEndPath(): string
    {
        return $this->resolveRelativeStatePath('end_date');
    }

    public function getWeekdaysPath(): string
    {
        return $this->resolveRelativeStatePath('weekdays');
    }

    public function showPublishSchedule(bool | Closure $condition = true): static
    {
        $this->showPublishSchedule = $condition;

        return $this;
    }

    public function getShowPublishSchedule(): bool
    {
        return (bool) $this->evaluate($this->showPublishSchedule);
    }

    public function getPublishImmediatelyPath(): string
    {
        return $this->resolveRelativeStatePath('publish_immediately');
    }

    public function getPublishedAtPath(): string
    {
        return $this->resolveRelativeStatePath('published_at');
    }
}
