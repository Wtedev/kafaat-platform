<?php

namespace Tests\Unit\Support;

use App\Enums\ProgramStatus;
use App\Models\TrainingProgram;
use App\Support\VolunteerLeadersProgramPeriod;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VolunteerLeadersProgramPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_formats_grouped_in_person_dates(): void
    {
        $this->assertSame(
            ['3–4', '8', '16–18'],
            VolunteerLeadersProgramPeriod::inPersonDayGroups(),
        );

        $this->assertSame(
            '3–4، 8، 16–18 أغسطس 2026',
            VolunteerLeadersProgramPeriod::formatInPersonDatesLabel(),
        );
    }

    public function test_sidebar_labels_for_volunteer_leaders_program(): void
    {
        $this->assertSame('3–8 أغسطس، 16–18 أغسطس', VolunteerLeadersProgramPeriod::inPersonDaysLabel());
        $this->assertSame('المتبقي من أيام الفترة', VolunteerLeadersProgramPeriod::remoteDaysLabel());
        $this->assertSame('images/programs/adeed-logo.png', VolunteerLeadersProgramPeriod::PARTNER_ADEED_LOGO);
        $this->assertSame('images/programs/partner-kafaat.svg', VolunteerLeadersProgramPeriod::PARTNER_KAFAAT_LOGO);
        $this->assertSame('images/programs/partner-associations-support-fund.png', VolunteerLeadersProgramPeriod::PARTNER_ASSOCIATIONS_SUPPORT_FUND_LOGO);
        $this->assertSame('images/programs/partner-hr-ministry.png', VolunteerLeadersProgramPeriod::PARTNER_HR_MINISTRY_LOGO);
        $this->assertSame('images/programs/partner-nonprofit-center.png', VolunteerLeadersProgramPeriod::PARTNER_NONPROFIT_CENTER_LOGO);
        $this->assertSame('images/programs/partner-masarat-raeda.png', VolunteerLeadersProgramPeriod::PARTNER_MASARAT_RAEDA_LOGO);
        $this->assertSame('images/programs/partner-bayt-al-thaqafa.png', VolunteerLeadersProgramPeriod::PARTNER_BAYT_AL_THAQAFA_LOGO);

        $groups = VolunteerLeadersProgramPeriod::programPartnerGroups();
        $this->assertSame(['مالك البرنامج', 'الشريك المنفذ', 'الشريك الداعم', 'الشريك الاستراتيجي', 'شركاء النجاح'], array_column($groups, 'heading'));
        $this->assertSame('جمعية عضيد للخدمات التطوعية', $groups[0]['partners'][0]['name']);
        $this->assertSame(VolunteerLeadersProgramPeriod::PARTNER_ADEED_LOGO, $groups[0]['partners'][0]['logo']);
        $this->assertSame('جمعية كفاءات', $groups[1]['partners'][0]['name']);
        $this->assertSame(VolunteerLeadersProgramPeriod::PARTNER_KAFAAT_LOGO, $groups[1]['partners'][0]['logo']);
        $this->assertSame('صندوق دعم الجمعيات', $groups[2]['partners'][0]['name']);
        $this->assertCount(2, $groups[3]['partners']);
        $this->assertCount(2, $groups[4]['partners']);
        $this->assertSame(7, array_sum(array_map(fn (array $g): int => count($g['partners']), $groups)));

        $program = TrainingProgram::query()->create([
            'title' => 'قادة التطوع',
            'slug' => 'period-test',
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'start_date' => Carbon::parse('2026-08-03'),
            'end_date' => Carbon::parse('2026-09-01'),
            'learning_path_id' => null,
        ]);

        $this->assertTrue(VolunteerLeadersProgramPeriod::applies($program));
    }

    public function test_does_not_apply_to_other_programs(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج آخر',
            'slug' => 'other-period-test',
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'start_date' => Carbon::parse('2026-08-03'),
            'end_date' => Carbon::parse('2026-09-01'),
            'learning_path_id' => null,
        ]);

        $this->assertFalse(VolunteerLeadersProgramPeriod::applies($program));
    }
}
