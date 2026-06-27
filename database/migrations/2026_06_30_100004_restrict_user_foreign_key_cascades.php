<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prevent accidental hard-delete cascades when a users row is removed.
     *
     * @var list<array{table: string, column: string}>
     */
    private array $userForeignKeys = [
        ['table' => 'profiles', 'column' => 'user_id'],
        ['table' => 'path_registrations', 'column' => 'user_id'],
        ['table' => 'user_course_progress', 'column' => 'user_id'],
        ['table' => 'program_registrations', 'column' => 'user_id'],
        ['table' => 'volunteer_registrations', 'column' => 'user_id'],
        ['table' => 'volunteer_hours', 'column' => 'user_id'],
        ['table' => 'certificates', 'column' => 'user_id'],
        ['table' => 'profile_recommendations', 'column' => 'user_id'],
        ['table' => 'team_members', 'column' => 'user_id'],
        ['table' => 'training_program_editors', 'column' => 'user_id'],
        ['table' => 'learning_path_editors', 'column' => 'user_id'],
        ['table' => 'in_app_notifications', 'column' => 'user_id'],
        ['table' => 'email_verification_codes', 'column' => 'user_id'],
        ['table' => 'user_activity_logs', 'column' => 'user_id'],
        ['table' => 'privacy_policy_acknowledgements', 'column' => 'user_id'],
        ['table' => 'user_documents', 'column' => 'user_id'],
        ['table' => 'candidate_pool_preferences', 'column' => 'user_id'],
        ['table' => 'candidate_pool_consent_events', 'column' => 'user_id'],
    ];

    public function up(): void
    {
        foreach ($this->userForeignKeys as $definition) {
            $this->replaceForeignKey(
                $definition['table'],
                $definition['column'],
                'restrict',
            );
        }
    }

    public function down(): void
    {
        foreach ($this->userForeignKeys as $definition) {
            $this->replaceForeignKey(
                $definition['table'],
                $definition['column'],
                'cascade',
            );
        }
    }

    private function replaceForeignKey(string $table, string $column, string $onDelete): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $onDelete): void {
            $blueprint->dropForeign([$column]);
            $foreign = $blueprint->foreign($column)->references('id')->on('users');

            if ($onDelete === 'restrict') {
                $foreign->restrictOnDelete();
            } else {
                $foreign->cascadeOnDelete();
            }
        });
    }
};
