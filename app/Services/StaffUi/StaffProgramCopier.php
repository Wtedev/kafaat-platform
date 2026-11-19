<?php

namespace App\Services\StaffUi;

use App\Enums\ProgramActivityAction;
use App\Enums\ProgramStatus;
use App\Models\ProgramSurvey;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramActivity\ProgramActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Copy a program as a new draft: its content, registration settings, survey
 * questions and certificate design. Dates, registrations, attendance, messages
 * and certificates are not copied — the copy is a fresh run.
 */
final class StaffProgramCopier
{
    /** Fields that describe the program itself and carry over to the copy. */
    private const COPIED = [
        'program_kind', 'competency_track', 'delivery_mode', 'venue', 'description',
        'session_topics_enabled', 'session_topics', 'program_presenters',
        'whatsapp_groups_enabled', 'whatsapp_group_male', 'whatsapp_group_female',
        'capacity', 'capacity_male', 'capacity_female',
        'approval_message', 'auto_accept_registrations', 'acceptance_conditions',
        'registration_questions', 'notify_on_publish', 'notify_milestones', 'notify_registrants_on_update',
    ];

    public function __construct(
        private readonly ProgramActivityLog $activity,
    ) {}

    public function copy(TrainingProgram $source, User $actor): TrainingProgram
    {
        return DB::transaction(function () use ($source, $actor): TrainingProgram {
            $copy = new TrainingProgram([
                ...collect(self::COPIED)->mapWithKeys(fn (string $field): array => [$field => $source->getAttribute($field)])->all(),
                'title' => mb_substr($source->title.' (نسخة)', 0, 255),
                'status' => ProgramStatus::Draft,
                'created_by' => $actor->id,
                'owner_id' => $actor->id,
            ]);
            $copy->allowCoverUpdate = true;
            $copy->image = $this->copyFile($source->image, 'programs/covers');
            $copy->save();

            $this->copySurveys($source, $copy);
            $this->copyCertificateDesign($source, $copy, $actor);

            $this->activity->record($copy, ProgramActivityAction::Created, null, 'نسخة من «'.$source->title.'»', ['copied_from' => $source->id], $actor);

            return $copy;
        });
    }

    private function copySurveys(TrainingProgram $source, TrainingProgram $copy): void
    {
        foreach ($source->surveys()->with('questions')->get() as $survey) {
            /** @var ProgramSurvey $new */
            $new = $copy->surveys()->create([
                'type' => $survey->type,
                'is_anonymous' => $survey->is_anonymous,
                'source_template_id' => $survey->source_template_id,
            ]);
            foreach ($survey->questions->sortBy('position') as $question) {
                $new->questions()->create($question->only([
                    'position', 'type', 'prompt', 'scale_min_label', 'scale_max_label', 'options', 'required',
                ]));
            }
        }
    }

    private function copyCertificateDesign(TrainingProgram $source, TrainingProgram $copy, User $actor): void
    {
        $template = $source->certificateTemplate()->first();
        if ($template === null) {
            return;
        }

        $copy->certificateTemplate()->create([
            'background_path' => $this->copyFile($template->background_path, dirname((string) $template->background_path) ?: 'certificates'),
            'elements' => $template->elements,
            'hours' => $template->hours,
            'updated_by' => $actor->id,
        ]);
    }

    /** Each program owns its files, so deleting one never removes the other's. */
    private function copyFile(?string $path, string $directory): ?string
    {
        if (blank($path) || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $target = trim($directory, '/').'/'.Str::uuid().'.'.(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg');
        Storage::disk('public')->copy($path, $target);

        return $target;
    }
}
