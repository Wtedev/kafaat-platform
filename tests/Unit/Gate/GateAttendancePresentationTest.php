<?php

namespace Tests\Unit\Gate;

use App\Enums\AttendanceStatus;
use App\Models\ProgramAttendance;
use App\Models\ProgramRegistration;
use App\Models\User;
use App\Services\Gate\GateAttendancePresentation;
use Illuminate\Support\Collection;
use Tests\TestCase;

class GateAttendancePresentationTest extends TestCase
{
    public function test_display_name_uses_the_structured_name_then_the_account_name(): void
    {
        $named = new User([
            'first_name' => 'لمى',
            'family_name' => 'المشيقح',
            'name' => 'حساب',
        ]);
        $fallback = new User(['name' => 'حساب فقط']);

        $this->assertSame('لمى المشيقح', GateAttendancePresentation::displayName($named));
        $this->assertSame('حساب فقط', GateAttendancePresentation::displayName($fallback));
        $this->assertSame('—', GateAttendancePresentation::displayName(null));
    }

    public function test_is_present_when_any_attendance_record_is_present(): void
    {
        $present = new ProgramAttendance;
        $present->status = AttendanceStatus::Present;
        $absent = new ProgramAttendance;
        $absent->status = AttendanceStatus::Absent;

        $registration = new ProgramRegistration;
        $registration->setRelation('attendanceRecords', new Collection([$absent, $present]));

        $this->assertTrue(GateAttendancePresentation::isPresent($registration));

        $registration->setRelation('attendanceRecords', new Collection([$absent]));
        $this->assertFalse(GateAttendancePresentation::isPresent($registration));
    }

    public function test_missing_live_session_uses_the_closed_defaults(): void
    {
        $state = GateAttendancePresentation::liveSessionState(null, 8);

        $this->assertFalse($state['can_open']);
        $this->assertFalse($state['active']);
        $this->assertSame(8, $state['session_minutes']);
        $this->assertSame([], $state['attendees']);

        $given = ['active' => true, 'present_count' => 2];
        $this->assertSame($given, GateAttendancePresentation::liveSessionState($given, 5));
    }
}
