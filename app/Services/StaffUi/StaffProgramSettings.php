<?php

namespace App\Services\StaffUi;

use App\Enums\ProgramActivityAction;
use App\Enums\ProgramStatus;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\ProgramActivity\ProgramActivityLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * The program «settings» tab: edit one section after creation, and move the
 * program between draft, published and archived.
 */
final class StaffProgramSettings
{
    /** Section key => wizard step that owns the same fields. */
    public const SECTIONS = [
        'basics' => 1,
        'schedule' => 2,
        'acceptance' => 3,
        'message' => 4,
        'notifications' => 5,
    ];

    public const SECTION_LABELS = [
        'basics' => 'البيانات الأساسية',
        'schedule' => 'الجدول',
        'acceptance' => 'القبول والسعة',
        'message' => 'رسالة القبول والمجموعات',
        'notifications' => 'الإشعارات',
    ];

    /** Columns that carry no meaning in the activity log. */
    private const IGNORED_FIELDS = ['updated_at', 'updated_by', 'slug'];

    public function __construct(
        private readonly StaffProgramWizard $wizard,
        private readonly ProgramActivityLog $activity,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return list<string> Changed column names.
     */
    public function updateSection(User $actor, TrainingProgram $program, string $section, array $input, ?UploadedFile $cover = null): array
    {
        $step = self::SECTIONS[$section] ?? null;
        abort_if($step === null, 404);

        $before = $program->getAttributes();
        $this->wizard->saveStep($program, $step, $input, $cover);
        $program->refresh();

        $changed = $this->changedFields($before, $program->getAttributes());

        if ($changed !== []) {
            $this->activity->record(
                $program,
                ProgramActivityAction::SettingsUpdated,
                description: self::SECTION_LABELS[$section],
                properties: ['section' => $section, 'fields' => $changed],
                actor: $actor,
            );
        }

        return $changed;
    }

    public function publish(User $actor, TrainingProgram $program): void
    {
        abort_unless($actor->can('publish', $program), 403);

        if ($program->status === ProgramStatus::Published) {
            return;
        }

        $invalid = $this->wizard->firstInvalidStoredStep($program);
        if ($invalid !== null) {
            $section = array_search($invalid, self::SECTIONS, true);

            throw ValidationException::withMessages([
                'status' => 'أكمل قسم «'.(self::SECTION_LABELS[$section] ?? 'البيانات الأساسية').'» قبل النشر.',
            ]);
        }

        if ($program->start_date === null || $program->registration_start === null || $program->registration_end === null) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن النشر قبل إدخال تاريخ بداية البرنامج وبداية التسجيل ونهايته.',
            ]);
        }

        $wasArchived = $program->status === ProgramStatus::Archived;
        $program->status = ProgramStatus::Published;
        $program->published_at ??= now();
        $program->save();

        $this->auditLogger->record($actor, 'training_program.published', resource: $program, metadata: ['source' => 'staff-ui-settings']);
        $this->activity->record(
            $program,
            $wasArchived ? ProgramActivityAction::Restored : ProgramActivityAction::Published,
            actor: $actor,
        );
    }

    public function unpublish(User $actor, TrainingProgram $program): void
    {
        abort_unless($actor->can('publish', $program), 403);

        if ($program->status !== ProgramStatus::Published) {
            return;
        }

        $program->status = ProgramStatus::Draft;
        $program->save();

        $this->auditLogger->record($actor, 'training_program.unpublished', resource: $program, metadata: ['source' => 'staff-ui-settings']);
        $this->activity->record($program, ProgramActivityAction::Unpublished, actor: $actor);
    }

    public function archive(User $actor, TrainingProgram $program): void
    {
        abort_unless($actor->can('archive', $program), 403);

        if ($program->status === ProgramStatus::Archived) {
            return;
        }

        $program->status = ProgramStatus::Archived;
        $program->save();

        $this->auditLogger->record($actor, 'training_program.archived', resource: $program, metadata: ['source' => 'staff-ui-settings']);
        $this->activity->record($program, ProgramActivityAction::Archived, actor: $actor);
    }

    /**
     * Archived programs come back as drafts, so nothing reappears publicly by surprise.
     */
    public function restore(User $actor, TrainingProgram $program): void
    {
        abort_unless($actor->can('archive', $program), 403);

        if ($program->status !== ProgramStatus::Archived) {
            return;
        }

        $program->status = ProgramStatus::Draft;
        $program->save();

        $this->auditLogger->record($actor, 'training_program.restored', resource: $program, metadata: ['source' => 'staff-ui-settings']);
        $this->activity->record($program, ProgramActivityAction::Restored, actor: $actor);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    private function changedFields(array $before, array $after): array
    {
        $changed = [];
        foreach ($after as $key => $value) {
            if (in_array($key, self::IGNORED_FIELDS, true)) {
                continue;
            }
            if (! array_key_exists($key, $before) || (string) json_encode($before[$key]) !== (string) json_encode($value)) {
                $changed[] = $key;
            }
        }

        return $changed;
    }
}
