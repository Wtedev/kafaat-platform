<?php

namespace App\Models;

use App\Enums\ProgramBroadcastAudienceMode;
use App\Enums\ProgramBroadcastStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SurveyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramBroadcast extends Model
{
    protected $fillable = [
        'training_program_id',
        'created_by',
        'subject',
        'content',
        'audience_mode',
        'audience_statuses',
        'audience_filter',
        'status',
        'recipients_count',
        'sent_count',
        'failed_count',
        'skipped_count',
        'sending_started_at',
        'sending_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'audience_mode' => ProgramBroadcastAudienceMode::class,
            'audience_statuses' => 'array',
            'audience_filter' => 'array',
            'status' => ProgramBroadcastStatus::class,
            'recipients_count' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'skipped_count' => 'integer',
            'sending_started_at' => 'datetime',
            'sending_completed_at' => 'datetime',
        ];
    }

    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(ProgramBroadcastRecipient::class);
    }

    public function isDraft(): bool
    {
        return $this->status === ProgramBroadcastStatus::Draft;
    }

    public function contentIsMutable(): bool
    {
        return $this->status?->isMutableContent() ?? false;
    }

    public function canBeDeleted(): bool
    {
        return $this->isDraft();
    }

    public function canRetryFailed(): bool
    {
        return in_array($this->status, [
            ProgramBroadcastStatus::CompletedWithErrors,
            ProgramBroadcastStatus::Failed,
            ProgramBroadcastStatus::Completed,
        ], true) && $this->failed_count > 0;
    }

    /**
     * @return list<RegistrationStatus>
     */
    public function resolvedAudienceStatuses(): array
    {
        if ($this->audience_mode === ProgramBroadcastAudienceMode::All) {
            return RegistrationStatus::cases();
        }

        $raw = is_array($this->audience_statuses) ? $this->audience_statuses : [];
        $statuses = [];

        foreach ($raw as $value) {
            if ($value instanceof RegistrationStatus) {
                $statuses[] = $value;

                continue;
            }

            $status = RegistrationStatus::tryFrom((string) $value);
            if ($status !== null) {
                $statuses[] = $status;
            }
        }

        return array_values(array_unique($statuses, SORT_REGULAR));
    }

    public function audienceLabel(): string
    {
        $filter = is_array($this->audience_filter) ? $this->audience_filter : [];
        if (isset($filter['survey_missing'])) {
            $type = SurveyType::tryFrom((string) $filter['survey_missing']);

            return 'المقبولون اللي ما عبّوا الاستبيان '.($type?->label() ?? '');
        }
        if (isset($filter['absent_link'])) {
            $name = ProgramAttendanceLink::query()->whereKey((int) $filter['absent_link'])->value('name');

            return 'المقبولون الغائبون عن «'.($name ?? 'يوم محذوف').'»';
        }

        if ($this->audience_mode === ProgramBroadcastAudienceMode::All) {
            return ProgramBroadcastAudienceMode::All->label();
        }

        $statuses = $this->resolvedAudienceStatuses();
        $accepted = [RegistrationStatus::Approved, RegistrationStatus::Completed];
        if (count($statuses) === 2 && array_diff(array_map(fn ($s) => $s->value, $accepted), array_map(fn ($s) => $s->value, $statuses)) === []) {
            return 'المقبولون';
        }

        $labels = array_map(
            static fn (RegistrationStatus $status): string => $status->label(),
            $statuses,
        );

        return $labels === [] ? '—' : implode('، ', $labels);
    }
}
