<?php

namespace App\Models;

use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Services\Inbox\InboxNotificationService;
use App\Support\FilamentAssignmentVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TrainingProgram extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'program_kind',
        'description',
        'image',
        'capacity',
        'start_date',
        'end_date',
        'weekdays',
        'registration_start',
        'registration_end',
        'status',
        'published_at',
        'created_by',
        'owner_id',
        'updated_by',
        'learning_path_id',
        'path_sort_order',
        'assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProgramStatus::class,
            'program_kind' => TrainingProgramKind::class,
            'published_at' => 'datetime',
            'start_date' => 'date',
            'end_date' => 'date',
            'registration_start' => 'date',
            'registration_end' => 'date',
            'capacity' => 'integer',
            'weekdays' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $program) {
            if (empty($program->slug)) {
                $program->slug = Str::slug($program->title);
            }

            // Default operational coordinator (assigned_to) for training_manager creating a program — not the same as owner_id.
            if ($program->assigned_to === null && Auth::check()) {
                $user = Auth::user();
                if ($user->hasRole('training_manager') && ! FilamentAssignmentVisibility::bypasses($user)) {
                    $program->assigned_to = $user->id;
                }
            }

            if ($program->owner_id === null && filled($program->created_by)) {
                $program->owner_id = $program->created_by;
            }
        });

        static::created(function (self $program): void {
            if ($program->status !== ProgramStatus::Published) {
                return;
            }

            $actor = Auth::user();
            app(InboxNotificationService::class)->programLaunched(
                $program,
                $actor instanceof User ? $actor : null,
            );
        });

        static::updated(function (self $program): void {
            $inbox = app(InboxNotificationService::class);
            $editor = Auth::user();

            if ($program->wasChanged('status') && $program->status === ProgramStatus::Published) {
                $inbox->programLaunched($program, $editor instanceof User ? $editor : null);

                return;
            }

            if ($program->status !== ProgramStatus::Published) {
                return;
            }

            $watched = [
                'title', 'slug', 'description', 'start_date', 'end_date',
                'registration_start', 'registration_end', 'capacity', 'weekdays', 'learning_path_id',
            ];

            if ($program->wasChanged($watched)) {
                $inbox->programUpdatedForRegistrants(
                    $program,
                    $editor instanceof User ? $editor : null,
                );
            }
        });
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePublished(Builder $query): void
    {
        $query->where('status', ProgramStatus::Published);
    }

    /**
     * Programs not embedded in a learning path (public training catalog).
     */
    public function scopeStandaloneCatalog(Builder $query): void
    {
        $query->whereNull('learning_path_id');
    }

    public function scopeDraft(Builder $query): void
    {
        $query->where('status', ProgramStatus::Draft);
    }

    public function scopeArchived(Builder $query): void
    {
        $query->where('status', ProgramStatus::Archived);
    }

    public function scopeRegistrationOpen(Builder $query): void
    {
        $today = Carbon::today();

        $query->where(function (Builder $q) use ($today) {
            $q->whereNull('registration_start')
                ->orWhere('registration_start', '<=', $today);
        })->where(function (Builder $q) use ($today) {
            $q->whereNull('registration_end')
                ->orWhere('registration_end', '>=', $today);
        });
    }

    /**
     * Historical hook for Filament queries; list filtering is no longer tied to assigned_to (see FilamentAssignmentVisibility).
     * Authorization uses owner/editor/coordinator via policies.
     */
    public function scopeForFilamentAssignmentAccess(Builder $query, ?User $viewer): void
    {
        FilamentAssignmentVisibility::constrainTrainingPrograms($query, $viewer);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isRegistrationOpen(): bool
    {
        $today = Carbon::today();

        $afterStart = $this->registration_start === null
            || $this->registration_start->lte($today);

        $beforeEnd = $this->registration_end === null
            || $this->registration_end->gte($today);

        return $afterStart && $beforeEnd;
    }

    public function approvedRegistrationsCount(): int
    {
        return $this->registrations()
            ->where('status', RegistrationStatus::Approved->value)
            ->count();
    }

    public function totalRegistrationsCount(): int
    {
        return $this->registrations()->count();
    }

    public function completedRegistrationsCount(): int
    {
        return $this->registrations()
            ->where('status', RegistrationStatus::Completed->value)
            ->count();
    }

    /**
     * واجهة المستخدم لحالة نافذة التسجيل (ليست حالة مسودة/منشور).
     */
    public function registrationWindowStatusLabel(): string
    {
        $today = Carbon::today();

        if ($this->end_date !== null && $this->end_date->lt($today)) {
            return 'منتهي';
        }

        if ($this->registration_end !== null && $this->registration_end->lt($today)) {
            return 'منتهي';
        }

        if ($this->registration_start !== null && $this->registration_start->gt($today)) {
            return 'لم يبدأ';
        }

        if ($this->start_date !== null && $this->start_date->gt($today) && ! $this->isRegistrationOpen()) {
            return 'لم يبدأ';
        }

        if ($this->isRegistrationOpen()) {
            return 'مفتوح';
        }

        return 'منتهي';
    }

    /**
     * مدة البرنامج من تاريخ البداية والنهاية (لا يُعرض تاريخ النهاية في واجهة العرض).
     */
    public function programDurationDescription(): string
    {
        if ($this->start_date === null || $this->end_date === null) {
            return 'غير محدد';
        }

        $days = max(1, (int) $this->start_date->diffInDays($this->end_date) + 1);

        return sprintf('%d يوماً', $days);
    }

    public function hasCapacity(): bool
    {
        if ($this->capacity === null) {
            return true;
        }

        return $this->approvedRegistrationsCount() < $this->capacity;
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function registrations(): HasMany
    {
        return $this->hasMany(ProgramRegistration::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function editors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'training_program_editors')->withTimestamps();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function certificates(): MorphMany
    {
        return $this->morphMany(Certificate::class, 'certificateable');
    }

    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
