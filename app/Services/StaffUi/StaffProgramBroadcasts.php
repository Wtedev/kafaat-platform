<?php

namespace App\Services\StaffUi;

use App\Enums\RegistrationStatus;
use App\Enums\SurveyType;
use App\Models\ProgramBroadcast;
use App\Models\TrainingProgram;
use App\Services\ProgramBroadcasts\ProgramBroadcastService;

/**
 * Who a message can go to, in staff words. Each segment maps to the broadcast
 * service's audience mode, statuses and optional filter.
 */
final class StaffProgramBroadcasts
{
    private const ACCEPTED = [RegistrationStatus::Approved->value, RegistrationStatus::Completed->value];

    public function __construct(
        private readonly ProgramBroadcastService $broadcasts,
    ) {}

    /**
     * @return array<string, array{label: string, group: string, data: array{audience_mode: string, audience_statuses: ?list<string>, audience_filter: ?array<string, mixed>}, count: int}>
     */
    public function segments(TrainingProgram $program): array
    {
        $segments = [
            'approved' => ['المقبولون', 'التسجيل', ['statuses', self::ACCEPTED, null]],
            'pending' => ['بانتظار المراجعة', 'التسجيل', ['statuses', [RegistrationStatus::Pending->value], null]],
            'rejected' => ['المرفوضون', 'التسجيل', ['statuses', [RegistrationStatus::Rejected->value], null]],
            'all' => ['كل المسجّلين', 'التسجيل', ['all', null, null]],
        ];

        foreach ($program->surveys()->get() as $survey) {
            $segments['survey:'.$survey->type->value] = [
                'المقبولون اللي ما عبّوا الاستبيان '.$survey->type->label(),
                'الاستبيانات',
                ['statuses', self::ACCEPTED, ['survey_missing' => $survey->type->value]],
            ];
        }
        uksort($segments, fn (string $a, string $b): int => $this->order($a) <=> $this->order($b));

        foreach ($program->attendanceLinks()->whereNull('cancelled_at')->orderBy('id')->get(['id', 'name']) as $link) {
            $segments['absent:'.$link->id] = [
                'المقبولون الغائبون عن «'.$link->name.'»',
                'التحضير',
                ['statuses', self::ACCEPTED, ['absent_link' => (int) $link->id]],
            ];
        }

        $out = [];
        foreach ($segments as $key => [$label, $group, [$mode, $statuses, $filter]]) {
            $out[$key] = [
                'label' => $label,
                'group' => $group,
                'data' => ['audience_mode' => $mode, 'audience_statuses' => $statuses, 'audience_filter' => $filter],
                'count' => $this->broadcasts->countEligibleRecipients($program, $mode, $statuses, $filter),
            ];
        }

        return $out;
    }

    /** The segment key a saved draft was built from, so the form can show it again. */
    public function keyFor(ProgramBroadcast $broadcast): string
    {
        $filter = is_array($broadcast->audience_filter) ? $broadcast->audience_filter : [];
        if (isset($filter['survey_missing'])) {
            return 'survey:'.$filter['survey_missing'];
        }
        if (isset($filter['absent_link'])) {
            return 'absent:'.$filter['absent_link'];
        }
        if ($broadcast->audience_mode?->value === 'all') {
            return 'all';
        }
        $statuses = (array) $broadcast->audience_statuses;
        sort($statuses);

        return match ($statuses) {
            [RegistrationStatus::Pending->value] => 'pending',
            [RegistrationStatus::Rejected->value] => 'rejected',
            default => 'approved',
        };
    }

    private function order(string $key): int
    {
        return match (true) {
            $key === 'approved' => 0,
            str_starts_with($key, 'survey:') => 1 + array_search(SurveyType::from(substr($key, 7)), SurveyType::cases(), true),
            $key === 'pending' => 10,
            $key === 'rejected' => 11,
            default => 12,
        };
    }
}
