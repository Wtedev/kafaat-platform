<?php

namespace App\Models;

use App\Support\FilamentAssignmentVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class VolunteerTeam extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'assigned_to',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $team): void {
            if (empty($team->slug)) {
                $team->slug = Str::slug($team->name) ?: 'team-'.Str::lower(Str::random(8));
            }

            if ($team->assigned_to === null && Auth::check()) {
                $user = Auth::user();
                if ($user->hasRole('volunteering_manager') && ! FilamentAssignmentVisibility::bypasses($user)) {
                    $team->assigned_to = $user->id;
                }
            }

            if ($team->created_by === null && Auth::check()) {
                $team->created_by = Auth::id();
            }
        });
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeForFilamentAssignmentAccess(Builder $query, ?User $viewer): void
    {
        FilamentAssignmentVisibility::constrainVolunteerTeams($query, $viewer);
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'volunteer_team_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withTimestamps();
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(TeamNotification::class, 'volunteer_team_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
