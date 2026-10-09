<?php

namespace App\Services\StaffUi;

use App\Enums\CompetencyTrack;
use App\Enums\ProfileGender;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Filament\Support\TrainingEntityFormSupport;
use App\Mail\ProgramAcceptancePreviewMail;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\ProgramAcceptanceConditions;
use App\Support\ProgramApprovalMail;
use App\Support\TrainingProgramExtrasSupport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class StaffProgramWizard
{
    public const STEPS = [1, 2, 3, 4, 5, 6];

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function canContinue(User $user, TrainingProgram $program): bool
    {
        if ($program->status !== ProgramStatus::Draft) {
            return false;
        }

        if ($user->can('update', $program)) {
            return true;
        }

        return $user->can('create', TrainingProgram::class)
            && (int) $program->created_by === (int) $user->id;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createDraft(User $actor, array $input, ?UploadedFile $cover): TrainingProgram
    {
        $data = $this->validatedBasics($input, null);
        $program = new TrainingProgram;
        $program->allowCoverUpdate = true;
        $program->fill([
            ...$data,
            'status' => ProgramStatus::Draft->value,
            'published_at' => null,
            'notify_on_publish' => true,
            'notify_milestones' => true,
            'notify_registrants_on_update' => true,
            'auto_accept_registrations' => false,
            'learning_path_id' => null,
            'created_by' => $actor->id,
        ]);
        $this->storeCover($program, $cover);
        $program->save();

        $this->auditLogger->record(
            $actor,
            'training_program.created',
            resource: $program,
            metadata: ['source' => 'staff-ui-wizard'],
        );

        return $program;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function saveStep(TrainingProgram $program, int $step, array $input, ?UploadedFile $cover = null): void
    {
        $program->allowCoverUpdate = true;

        match ($step) {
            1 => $this->saveBasics($program, $input, $cover),
            2 => $this->saveSchedule($program, $input),
            3 => $this->saveAcceptance($program, $input),
            4 => $this->saveMessage($program, $input),
            5 => $this->savePublishIntent($program, $input),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function finish(User $actor, TrainingProgram $program, array $input): TrainingProgram
    {
        $invalid = $this->firstInvalidStoredStep($program);
        if ($invalid !== null) {
            throw ValidationException::withMessages([
                'wizard' => 'أكمل الخطوة '.$invalid.' قبل حفظ البرنامج.',
            ]);
        }

        $publish = (bool) ($input['publish'] ?? false);
        if ($publish && ! $actor->can('publish', $program)) {
            throw ValidationException::withMessages([
                'publish' => 'لا تملك صلاحية نشر هذا البرنامج.',
            ]);
        }

        $program->status = $publish ? ProgramStatus::Published : ProgramStatus::Draft;
        $program->published_at = $publish ? now() : null;
        $program->save();

        if ($publish) {
            $this->auditLogger->record(
                $actor,
                'training_program.published',
                resource: $program,
                metadata: ['source' => 'staff-ui-wizard'],
            );
        }

        return $program;
    }

    public function firstInvalidStoredStep(TrainingProgram $program): ?int
    {
        foreach ([1, 2, 3, 4, 5] as $step) {
            try {
                $this->assertStoredStep($program, $step);
            } catch (ValidationException) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function sendPreview(User $actor, TrainingProgram $program, array $input): void
    {
        $this->saveMessage($program, $input);
        $program->refresh();

        $subject = ProgramApprovalMail::subjectFor($program);
        $groupUrl = ProgramApprovalMail::groupUrl($program, $actor);
        $custom = filled($program->approval_message);
        $logo = rtrim((string) config('site.website_url'), '/').'/'.ltrim((string) config('brand.logos.kafaat_mail'), '/');

        Mail::to($actor->email)->send(new ProgramAcceptancePreviewMail(
            subjectLine: $subject,
            bodyHtml: $custom
                ? (string) $program->approval_message
                : '<p style="margin:0 0 16px;text-align:center;">تم قبول طلبك في البرنامج التدريبي «'.e($program->title).'».</p>',
            groupUrl: $groupUrl,
            showPendingLine: $program->whatsapp_groups_enabled
                && $groupUrl === null
                && ProgramApprovalMail::genderUnspecified($actor),
            logoUrl: $logo,
        ));
    }

    /**
     * @return list<string>
     */
    public function acceptancePreview(TrainingProgram $program): array
    {
        $lines = ProgramAcceptanceConditions::summarize(
            is_array($program->acceptance_conditions) ? $program->acceptance_conditions : null,
        );

        $lines = array_values(array_filter(
            $lines,
            static fn (string $line): bool => ! str_contains($line, 'مدينة الإقامة')
                && ! str_contains($line, 'اكتمال بيانات الملف'),
        ));

        if ($program->capacity_male !== null || $program->capacity_female !== null) {
            if ($program->capacity_male !== null) {
                $lines[] = 'سعة الرجال: '.$program->capacity_male;
            }
            if ($program->capacity_female !== null) {
                $lines[] = 'سعة النساء: '.$program->capacity_female;
            }
        } elseif ($program->capacity !== null) {
            $lines[] = 'السعة: '.$program->capacity;
        } else {
            $lines[] = 'السعة: بلا حد';
        }

        $lines[] = $program->auto_accept_registrations
            ? 'طريقة القبول: تلقائي لمن يستوفي الشروط حتى امتلاء السعة'
            : 'طريقة القبول: يدوي';

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validatedBasics(array $input, ?TrainingProgram $program): array
    {
        $validator = validator($input, [
            'title' => ['required', 'string', 'max:255'],
            'program_kind' => ['required', Rule::enum(TrainingProgramKind::class)],
            'competency_track' => ['required', Rule::enum(CompetencyTrack::class)],
            'description' => ['nullable', 'string', 'max:20000'],
            'delivery_mode' => ['required', Rule::enum(ProgramDeliveryMode::class)],
            'venue' => ['nullable', 'string', 'max:255'],
            'program_presenters' => ['nullable', 'array', 'max:20'],
            'program_presenters.*.name' => ['nullable', 'string', 'max:255'],
            'program_presenters.*.role' => ['nullable', 'string', 'max:255'],
            'session_topics_enabled' => ['nullable', 'boolean'],
            'session_topics' => ['nullable', 'array', 'max:30'],
            'session_topics.*.title' => ['nullable', 'string', 'max:255'],
            'session_topics.*.facilitators' => ['nullable', 'string', 'max:255'],
        ]);

        $validator->after(function ($validator) use ($input): void {
            $mode = ProgramDeliveryMode::tryFrom((string) ($input['delivery_mode'] ?? ''));
            if ($mode?->hasPhysicalComponent() && blank($input['venue'] ?? null)) {
                $validator->errors()->add('venue', 'مكان الانعقاد مطلوب للحضوري والهايبرد.');
            }

            foreach (is_array($input['program_presenters'] ?? null) ? $input['program_presenters'] : [] as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $name = trim((string) ($row['name'] ?? ''));
                $role = trim((string) ($row['role'] ?? ''));
                if ($name === '' && $role !== '') {
                    $validator->errors()->add('program_presenters.'.$index.'.name', 'اسم المقدم مطلوب.');
                }
            }
        });

        $data = $validator->validate();
        $data['description'] = TrainingProgramExtrasSupport::normalizeDescriptionForForm($data['description'] ?? null);
        $data = TrainingEntityFormSupport::applyDeliveryModeFields($data);
        $data['session_topics_enabled'] = (bool) ($input['session_topics_enabled'] ?? false);
        $data = TrainingProgramExtrasSupport::applyProgramPresenters($data);
        $data = TrainingProgramExtrasSupport::applySessionTopics($data);

        if (($data['program_kind'] ?? null) === TrainingProgramKind::Session->value && $program !== null) {
            $program->end_date = null;
            $program->weekdays = null;
        }

        unset($data['image']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function saveBasics(TrainingProgram $program, array $input, ?UploadedFile $cover): void
    {
        $data = $this->validatedBasics($input, $program);
        if (($data['program_kind'] ?? null) === TrainingProgramKind::Session->value) {
            $data['end_date'] = null;
            $data['weekdays'] = null;
        }
        $program->fill($data);
        $this->storeCover($program, $cover);
        $program->save();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function saveSchedule(TrainingProgram $program, array $input): void
    {
        $isSession = $program->program_kind === TrainingProgramKind::Session;
        $validator = validator($input, [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'registration_start' => ['nullable', 'date'],
            'registration_end' => ['nullable', 'date'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', Rule::in([0, 1, 2, 3, 4, 5, 6])],
        ]);
        $data = $validator->validate();
        if ($isSession) {
            $data['end_date'] = null;
            $data['weekdays'] = null;
        }
        $errors = TrainingEntityFormSupport::validateProgramScheduleDates($data);
        if ($errors !== []) {
            throw ValidationException::withMessages([
                'start_date' => $errors,
            ]);
        }
        $program->fill([
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'registration_start' => $data['registration_start'] ?? null,
            'registration_end' => $data['registration_end'] ?? null,
            'weekdays' => $isSession ? null : array_values(array_unique(array_map('intval', $data['weekdays'] ?? []))),
        ]);
        $program->save();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function saveAcceptance(TrainingProgram $program, array $input): void
    {
        $validator = validator($input, [
            'acceptance_genders' => ['nullable', 'array'],
            'acceptance_genders.*' => [Rule::enum(ProfileGender::class)],
            'acceptance_min_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'acceptance_max_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'capacity_mode' => ['required', Rule::in(['unlimited', 'shared', 'per_gender'])],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'capacity_male' => ['nullable', 'integer', 'min:1'],
            'capacity_female' => ['nullable', 'integer', 'min:1'],
        ]);
        $validator->after(function ($validator) use ($input): void {
            $min = $input['acceptance_min_age'] ?? null;
            $max = $input['acceptance_max_age'] ?? null;
            if ($min !== null && $min !== '' && $max !== null && $max !== '' && (int) $min > (int) $max) {
                $validator->errors()->add('acceptance_max_age', 'الحد الأقصى للعمر يجب أن يكون أكبر من الحد الأدنى أو يساويه.');
            }
            $mode = (string) ($input['capacity_mode'] ?? '');
            $genders = is_array($input['acceptance_genders'] ?? null) ? $input['acceptance_genders'] : [];
            if ($mode === 'shared' && blank($input['capacity'] ?? null)) {
                $validator->errors()->add('capacity', 'سعة البرنامج مطلوبة.');
            }
            if ($mode === 'per_gender') {
                $needsMale = $genders === [] || in_array(ProfileGender::Male->value, $genders, true);
                $needsFemale = $genders === [] || in_array(ProfileGender::Female->value, $genders, true);
                if ($needsMale && blank($input['capacity_male'] ?? null)) {
                    $validator->errors()->add('capacity_male', 'سعة الرجال مطلوبة.');
                }
                if ($needsFemale && blank($input['capacity_female'] ?? null)) {
                    $validator->errors()->add('capacity_female', 'سعة النساء مطلوبة.');
                }
            }
        });
        $data = $validator->validate();
        $existing = is_array($program->acceptance_conditions) ? $program->acceptance_conditions : [];
        $packed = ProgramAcceptanceConditions::applyFormData([
            'acceptance_require_saudi_national' => (bool) ($input['acceptance_require_saudi_national'] ?? false),
            'acceptance_genders' => $data['acceptance_genders'] ?? [],
            'acceptance_min_age' => $data['acceptance_min_age'] ?? null,
            'acceptance_max_age' => $data['acceptance_max_age'] ?? null,
            'acceptance_conditions' => $existing,
        ]);
        $capacity = TrainingEntityFormSupport::applyCapacityUnlimited([
            'capacity_mode' => $data['capacity_mode'],
            'capacity' => $data['capacity'] ?? null,
            'capacity_male' => $data['capacity_male'] ?? null,
            'capacity_female' => $data['capacity_female'] ?? null,
        ]);
        $program->fill([
            'auto_accept_registrations' => (bool) ($input['auto_accept_registrations'] ?? false),
            'acceptance_conditions' => $packed['acceptance_conditions'] ?? null,
            'capacity' => $capacity['capacity'] ?? null,
            'capacity_male' => $capacity['capacity_male'] ?? null,
            'capacity_female' => $capacity['capacity_female'] ?? null,
        ]);
        $program->save();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function saveMessage(TrainingProgram $program, array $input): void
    {
        $genders = is_array($program->acceptance_conditions['genders'] ?? null)
            ? $program->acceptance_conditions['genders']
            : [];
        $enabled = (bool) ($input['whatsapp_groups_enabled'] ?? false);
        $validator = validator($input, [
            'approval_message' => ['nullable', 'string', 'max:10000'],
            'whatsapp_group_male' => ['nullable', 'string', 'max:2048', TrainingProgramExtrasSupport::httpsUrlRule()],
            'whatsapp_group_female' => ['nullable', 'string', 'max:2048', TrainingProgramExtrasSupport::httpsUrlRule()],
        ]);
        $validator->after(function ($validator) use ($input, $enabled, $genders): void {
            if (! $enabled) {
                return;
            }
            $needsMale = $genders === [] || in_array(ProfileGender::Male->value, $genders, true);
            $needsFemale = $genders === [] || in_array(ProfileGender::Female->value, $genders, true);
            if ($needsMale && blank($input['whatsapp_group_male'] ?? null)) {
                $validator->errors()->add('whatsapp_group_male', 'رابط مجموعة الرجال مطلوب.');
            }
            if ($needsFemale && blank($input['whatsapp_group_female'] ?? null)) {
                $validator->errors()->add('whatsapp_group_female', 'رابط مجموعة النساء مطلوب.');
            }
        });
        $data = $validator->validate();
        $applied = TrainingProgramExtrasSupport::applyWhatsappGroups([
            'whatsapp_groups_enabled' => $enabled,
            'whatsapp_group_male' => $data['whatsapp_group_male'] ?? null,
            'whatsapp_group_female' => $data['whatsapp_group_female'] ?? null,
        ]);
        $program->fill([
            'approval_message' => filled($data['approval_message'] ?? null) ? trim((string) $data['approval_message']) : null,
            'whatsapp_groups_enabled' => $enabled,
            'whatsapp_group_male' => $applied['whatsapp_group_male'] ?? null,
            'whatsapp_group_female' => $applied['whatsapp_group_female'] ?? null,
        ]);
        $program->save();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function savePublishIntent(TrainingProgram $program, array $input): void
    {
        $notify = (bool) ($input['notify_audience'] ?? false);
        $program->fill([
            'notify_on_publish' => $notify,
            'notify_milestones' => $notify,
            'notify_registrants_on_update' => $notify,
        ]);
        $program->save();
    }

    private function assertStoredStep(TrainingProgram $program, int $step): void
    {
        match ($step) {
            1 => $this->validatedBasics([
                'title' => $program->title,
                'program_kind' => $program->program_kind?->value,
                'competency_track' => $program->competency_track?->value,
                'description' => $program->description,
                'delivery_mode' => $program->delivery_mode?->value,
                'venue' => $program->venue,
                'program_presenters' => $program->program_presenters,
                'session_topics_enabled' => $program->session_topics_enabled,
                'session_topics' => $program->session_topics,
            ], $program),
            2 => $this->saveSchedule($program, [
                'start_date' => $program->start_date?->toDateString(),
                'end_date' => $program->end_date?->toDateString(),
                'registration_start' => $program->registration_start?->toDateString(),
                'registration_end' => $program->registration_end?->toDateString(),
                'weekdays' => $program->weekdays ?? [],
            ]),
            3, 4, 5 => null,
            default => null,
        };

        if ($step === 2) {
            return;
        }

        if ($step === 1 && blank($program->title)) {
            throw ValidationException::withMessages(['title' => 'العنوان مطلوب.']);
        }
    }

    private function storeCover(TrainingProgram $program, ?UploadedFile $cover): void
    {
        if (! $cover instanceof UploadedFile) {
            return;
        }

        validator(
            ['image' => $cover],
            ['image' => ['file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120', 'dimensions:max_width=4000,max_height=4000']],
            [
                'image.image' => 'يجب أن يكون الملف صورة حقيقية (JPEG أو PNG أو WebP).',
                'image.mimes' => 'الصيغ المسموحة فقط: JPEG و PNG و WebP.',
                'image.max' => 'حجم الصورة يجب ألا يتجاوز 5 ميجابايت.',
                'image.dimensions' => 'أبعاد الصورة كبيرة جداً. الحد الأقصى 4000×4000 بكسل.',
            ],
        )->validate();

        $ext = strtolower($cover->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }

        $program->allowCoverUpdate = true;
        $program->image = $cover->storeAs('programs/covers', (string) Str::uuid().'.'.$ext, 'public');
    }
}
