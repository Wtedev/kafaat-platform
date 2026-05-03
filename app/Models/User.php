<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\VolunteerHoursStatus;
use App\Services\Rbac\RbacService;
use Database\Factories\UserFactory;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_type',
        'phone',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function learningPathRegistrations(): HasMany
    {
        return $this->hasMany(PathRegistration::class);
    }

    public function programRegistrations(): HasMany
    {
        return $this->hasMany(ProgramRegistration::class);
    }

    public function assignedTrainingPrograms(): HasMany
    {
        return $this->hasMany(TrainingProgram::class, 'assigned_to');
    }

    public function assignedVolunteerOpportunities(): HasMany
    {
        return $this->hasMany(VolunteerOpportunity::class, 'assigned_to');
    }

    public function volunteerRegistrations(): HasMany
    {
        return $this->hasMany(VolunteerRegistration::class);
    }

    public function teamMemberships(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function volunteerTeams(): BelongsToMany
    {
        return $this->belongsToMany(VolunteerTeam::class, 'team_members')
            ->withTimestamps();
    }

    public function inboxNotifications(): HasMany
    {
        return $this->hasMany(InboxNotification::class, 'user_id');
    }

    public function volunteerHours(): HasMany
    {
        return $this->hasMany(VolunteerHour::class);
    }

    public function totalApprovedVolunteerHours(): float
    {
        return (float) $this->volunteerHours()
            ->where('status', VolunteerHoursStatus::Approved->value)
            ->sum('hours');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function profileRecommendations(): HasMany
    {
        return $this->hasMany(ProfileRecommendation::class)->orderBy('sort_order');
    }

    // ─── Role helpers ─────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role_type === 'admin';
    }

    /**
     * حساب مدير النظام المحمي من الحذف (دور admin أو نوع admin أو بريد المسؤول من البيئة).
     */
    public function isProtectedAdminUser(): bool
    {
        if ($this->role_type === 'admin' || $this->hasRole('admin')) {
            return true;
        }

        $adminEmail = env('ADMIN_EMAIL');

        return filled($adminEmail) && strcasecmp((string) $this->email, (string) $adminEmail) === 0;
    }

    public function isStaff(): bool
    {
        if ($this->role_type === 'staff') {
            return true;
        }

        return $this->hasAnyRole([
            'media_pr',
            'public_relations',
            'media',
            'media_employee',
            'pr_employee',
            'training_enablement_manager',
            'training_manager',
            'programs_activities_manager',
            'volunteering_manager',
            'volunteer_manager',
            'staff',
        ]);
    }

    /**
     * مستخدمو البوابة (متدرب / متطوع / قيمة role_type القديمة beneficiary).
     */
    public function isPortalUser(): bool
    {
        if ($this->hasAnyRole(['trainee', 'volunteer'])) {
            return true;
        }

        return in_array($this->role_type, ['beneficiary', 'trainee', 'volunteer'], true);
    }

    public function isBeneficiary(): bool
    {
        return $this->isPortalUser();
    }

    public function isAdminOrStaff(): bool
    {
        return $this->isAdmin() || $this->isStaff();
    }

    /**
     * RBAC: يتحقق من صلاحية محددة (Spatie + قاعدة البيانات).
     */
    public function hasPermission(string $permission, ?string $guardName = null): bool
    {
        return app(RbacService::class)->hasPermission($this, $permission, $guardName);
    }

    /**
     * RBAC: يتحقق من دور Spatie (اسم الدور في جدول roles).
     *
     * @param  string|array<int, string|\BackedEnum>  $roles
     */
    public function hasRoleName(string|array $roles, ?string $guardName = null): bool
    {
        return app(RbacService::class)->hasRole($this, $roles, $guardName);
    }

    // ─── Filament access ──────────────────────────────────────────────────────

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->hasAnyRole([
            'admin',
            'media_pr',
            'public_relations',
            'media',
            'media_employee',
            'pr_employee',
            'training_enablement_manager',
            'training_manager',
            'programs_activities_manager',
            'volunteering_manager',
            'volunteer_manager',
            'staff',
        ]);
    }

    /**
     * وصول لوحة Filament الإدارية (نفس شرط canAccessPanel للوحة admin).
     */
    public function canAccessFilamentAdmin(): bool
    {
        return $this->canAccessPanel(Filament::getPanel('admin'));
    }

    /**
     * Active admin/staff-style users eligible to become training entity owners (Filament ownership transfer).
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeEligibleForTrainingOwnershipTransfer(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q): void {
                $q->whereIn('role_type', ['admin', 'staff'])
                    ->orWhereHas('roles', fn ($r) => $r->whereIn('name', [
                        'admin',
                        'media_pr',
                        'public_relations',
                        'media',
                        'media_employee',
                        'pr_employee',
                        'training_enablement_manager',
                        'training_manager',
                        'programs_activities_manager',
                        'volunteering_manager',
                        'volunteer_manager',
                        'staff',
                    ]));
            });
    }
}
