<?php

namespace App\Models;

use App\Enums\OpportunityStatus;
use App\Services\Inbox\InboxNotificationService;
use App\Services\Media\PublicMediaLifecycleService;
use App\Support\CurrentPublishMoment;
use App\Support\FilamentAssignmentVisibility;
use App\Support\PublicDiskPath;
use App\Support\StaffFilamentRoles;
use App\Support\UniqueModelSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

class VolunteerOpportunity extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'image',
        'external_url',
        'capacity',
        'hours_expected',
        'start_date',
        'end_date',
        'status',
        'published_at',
        'notify_on_publish',
        'created_by',
        'updated_by',
        'assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'status' => OpportunityStatus::class,
            'published_at' => 'datetime',
            'notify_on_publish' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'capacity' => 'integer',
            'hours_expected' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $opportunity) {
            if (empty($opportunity->slug)) {
                $opportunity->slug = UniqueModelSlug::fromTitle(
                    self::class,
                    (string) $opportunity->title,
                    fallbackPrefix: 'opportunity',
                );
            }

            if ($opportunity->assigned_to === null && Auth::check()) {
                $user = Auth::user();
                if ($user->hasAnyRole(StaffFilamentRoles::VOLUNTEERING_COORDINATOR) && ! FilamentAssignmentVisibility::bypasses($user)) {
                    $opportunity->assigned_to = $user->id;
                }
            }
        });

        static::created(function (self $opportunity): void {
            if ($opportunity->status !== OpportunityStatus::Published || ! $opportunity->notify_on_publish) {
                return;
            }

            if (! CurrentPublishMoment::includes($opportunity->published_at)) {
                return;
            }

            $editor = Auth::user();
            app(InboxNotificationService::class)->volunteerOpportunityFirstPublished(
                $opportunity,
                $editor instanceof User ? $editor : null,
            );
        });

        static::updated(function (self $opportunity): void {
            if (! $opportunity->wasChanged('status') || $opportunity->status !== OpportunityStatus::Published) {
                return;
            }

            if ($opportunity->notify_on_publish && CurrentPublishMoment::includes($opportunity->published_at)) {
                $editor = Auth::user();
                app(InboxNotificationService::class)->volunteerOpportunityFirstPublished(
                    $opportunity,
                    $editor instanceof User ? $editor : null,
                );
            }
        });

        static::deleting(function (self $opportunity): void {
            app(PublicMediaLifecycleService::class)->deleteOwnedPath($opportunity->image);
        });
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePublished(Builder $query): void
    {
        $query->where('status', OpportunityStatus::Published);
    }

    public function scopeDraft(Builder $query): void
    {
        $query->where('status', OpportunityStatus::Draft);
    }

    public function scopeArchived(Builder $query): void
    {
        $query->where('status', OpportunityStatus::Archived);
    }

    public function scopeForFilamentAssignmentAccess(Builder $query, ?User $viewer): void
    {
        FilamentAssignmentVisibility::constrainVolunteerOpportunities($query, $viewer);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    // ─── Relationships ────────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Certificates issued before volunteering moved to the national platform stay verifiable. */
    public function certificates(): MorphMany
    {
        return $this->morphMany(Certificate::class, 'certificateable');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** هل للفرصة رابط تسجيل في المنصة الوطنية للتطوع؟ */
    public function hasExternalUrl(): bool
    {
        return filled($this->external_url);
    }

    /** رابط الصورة للموقع العام أو لوحة الإدارة (أو صورة افتراضية). */
    public function imagePublicUrl(): string
    {
        return PublicDiskPath::urlOrPlaceholder($this->image ?? null, PublicDiskPath::PLACEHOLDER_VOLUNTEER_OPPORTUNITY);
    }
}
