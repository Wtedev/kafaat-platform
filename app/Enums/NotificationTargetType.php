<?php

namespace App\Enums;

/**
 * جمهور الإرسال عند الإنشاء (للتصفية والتدقيق).
 */
enum NotificationTargetType: string
{
    case AllPortalUsers = 'all_portal';
    case Trainees = 'trainees';
    case Volunteers = 'volunteers';
    case Staff = 'staff';
    case SingleUser = 'single_user';
    case ProgramRegistrants = 'program_registrants';
    case DirectRecipients = 'direct_recipients';

    /** أعضاء فريق تطوعي (إرسال يدوي من لوحة الإدارة) */
    case VolunteerTeamMembers = 'volunteer_team_members';
}
