<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

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
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
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

    public function courseProgress(): HasMany
    {
        return $this->hasMany(UserCourseProgress::class);
    }

    public function programRegistrations(): HasMany
    {
        return $this->hasMany(ProgramRegistration::class);
    }

    public function volunteerRegistrations(): HasMany
    {
        return $this->hasMany(VolunteerRegistration::class);
    }

    public function volunteerHours(): HasMany
    {
        return $this->hasMany(VolunteerHour::class);
    }

    public function totalApprovedVolunteerHours(): float
    {
        return (float) $this->volunteerHours()
            ->where('status', \App\Enums\VolunteerHoursStatus::Approved->value)
            ->sum('hours');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    // ─── Role helpers ─────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role_type === 'admin';
    }

    public function isStaff(): bool
    {
        return $this->role_type === 'staff';
    }

    public function isBeneficiary(): bool
    {
        return $this->role_type === 'beneficiary';
    }

    public function isAdminOrStaff(): bool
    {
        return $this->isAdmin() || $this->isStaff();
    }

    // ─── Filament access ──────────────────────────────────────────────────────

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdminOrStaff() && $this->is_active;
    }
}
