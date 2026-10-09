<?php

use App\Enums\ProfileGender;
use App\Enums\RegistrationStatus;
use App\Support\DataForumAcceptance;
use App\Support\ProgramApprovalMail;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table): void {
            $table->unsignedInteger('capacity_male')->nullable()->after('capacity');
            $table->unsignedInteger('capacity_female')->nullable()->after('capacity_male');
            $table->text('approval_message')->nullable()->after('capacity_female');
        });

        $programs = DB::table('training_programs')
            ->whereNotNull('acceptance_conditions')
            ->get(['id', 'acceptance_conditions']);

        foreach ($programs as $program) {
            $conditions = json_decode((string) $program->acceptance_conditions, true);
            $full = is_array($conditions) ? ($conditions['gender_capacity_full'] ?? []) : [];
            if (! is_array($full) || $full === []) {
                continue;
            }

            $updates = [];
            foreach ([ProfileGender::Male->value, ProfileGender::Female->value] as $gender) {
                if (! in_array($gender, $full, true)) {
                    continue;
                }

                $count = DB::table('program_registrations')
                    ->join('profiles', 'profiles.user_id', '=', 'program_registrations.user_id')
                    ->where('program_registrations.training_program_id', $program->id)
                    ->where('program_registrations.status', RegistrationStatus::Approved->value)
                    ->where('profiles.gender', $gender)
                    ->count();

                $updates[$gender === ProfileGender::Male->value ? 'capacity_male' : 'capacity_female'] = $count;
            }

            if ($updates !== []) {
                DB::table('training_programs')->where('id', $program->id)->update($updates);
            }
        }

        $male = env('DATA_FORUM_TELEGRAM_MALE', config('data_forum.telegram_male'));
        $female = env('DATA_FORUM_TELEGRAM_FEMALE', config('data_forum.telegram_female'));
        $forum = ['approval_message' => ProgramApprovalMail::FORUM_BODY];

        if (is_string($male) && trim($male) !== '') {
            $forum['whatsapp_group_male'] = trim($male);
        }

        if (is_string($female) && trim($female) !== '') {
            $forum['whatsapp_group_female'] = trim($female);
        }

        if (isset($forum['whatsapp_group_male']) || isset($forum['whatsapp_group_female'])) {
            $forum['whatsapp_groups_enabled'] = true;
        }

        DB::table('training_programs')->where('slug', DataForumAcceptance::SLUG)->update($forum);
    }

    public function down(): void
    {
        Schema::table('training_programs', function (Blueprint $table): void {
            $table->dropColumn(['capacity_male', 'capacity_female', 'approval_message']);
        });
    }
};
