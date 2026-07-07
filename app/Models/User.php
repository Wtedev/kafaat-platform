<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\IdentityType;
use App\Enums\ProfileGender;
use App\Enums\VolunteerHoursStatus;
use App\Services\Identity\IdentityNumberService;
use App\Services\Identity\PersonNameService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\Rbac\RbacCatalog;
use App\Services\Rbac\RbacService;
use App\Services\Privacy\AccountDeactivationService;
use App\Support\Privacy\UserDeletionGuard;
use App\Support\PublicDiskPath;
use App\Models\Builders\UserQueryBuilder;
use App\Models\Concerns\HasEntityNotes;
use Database\Factories\UserFactory;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasEntityNotes, HasFactory, HasRoles, Notifiable;

    protected static function booted(): void
    {
        static::deleting(function (User $user): void {
            UserDeletionGuard::assertAuthorized();
        });

        static::updated(function (User $user): void {
            if ($user->wasChanged('is_active') && ! $user->is_active) {
                app(AccountDeactivationService::class)->invalidateSessions($user);
            }
        });
    }

    protected $fillable = [
        'name',
        'first_name',
        'father_name',
        'grandfather_name',
        'family_name',
        'email',
        'password',
        'role_type',
        'phone',
        'staff_photo',
        'is_active',
        'account_status',
        'privacy_deleted_at',
        'anonymized_at',
        'deletion_request_id',
        'notify_email',
        'notification_prefs_set_at',
        'notification_settings',
        'last_login_at',
        'profile_completed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'identity_number_ciphertext',
        'identity_number_lookup_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'account_status' => AccountStatus::class,
            'privacy_deleted_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'notify_email' => 'boolean',
            'notification_prefs_set_at' => 'datetime',
            'notification_settings' => 'array',
            'identity_type' => IdentityType::class,
            'identity_confirmed_at' => 'datetime',
            'profile_completed_at' => 'datetime',
        ];
    }

    /**
     * Compatibility field: kept in sync from structured name parts via services.
     */
    public function fullName(): string
    {
        $structured = PersonNameService::buildFullName([
            'first_name' => $this->first_name,
            'father_name' => $this->father_name,
            'grandfather_name' => $this->grandfather_name,
            'family_name' => $this->family_name,
        ]);

        if ($structured !== '') {
            return $structured;
        }

        return trim((string) $this->name);
    }

    public function hasStructuredName(): bool
    {
        return PersonNameService::hasAllRequiredParts([
            'first_name' => $this->first_name,
            'father_name' => $this->father_name,
            'grandfather_name' => $this->grandfather_name,
            'family_name' => $this->family_name,
        ]);
    }

    public function certificateName(): string
    {
        if ($this->hasStructuredName()) {
            return $this->fullName();
        }

        $legacy = trim((string) $this->name);

        return $legacy !== '' ? $legacy : '—';
    }

    public function hasIdentityOnRecord(): bool
    {
        return filled($this->identity_number_lookup_hash)
            && filled($this->identity_number_last4)
            && $this->identity_type instanceof IdentityType;
    }

    public function maskedIdentityNumber(): ?string
    {
        return IdentityNumberService::mask($this->identity_number_last4);
    }

    public function hasCompletedRequiredIdentityData(?string $birthDate = null): bool
    {
        if (! $this->hasStructuredName() || ! $this->hasIdentityOnRecord()) {
            return false;
        }

        if (! filled($this->phone)) {
            return false;
        }

        $this->loadMissing('profile');

        $resolvedBirthDate = $birthDate ?? $this->profile?->birth_date?->toDateString();

        return filled($resolvedBirthDate) && $this->profile?->gender instanceof ProfileGender;
    }

    /**
     * يرسل رمز التحقق الرقمي (OTP) بدل رابط Laravel الافتراضي.
     */
    public function sendEmailVerificationNotification(): void
    {
        app(\App\Services\Auth\EmailVerificationCodeService::class)->sendCode($this);
    }

    /**
     * هل يرغب المستخدم باستقبال نسخة بريدية من التنبيهات؟ (داخل الموقع دائماً مفعّل).
     */
    public function wantsEmailNotifications(): bool
    {
        return (bool) $this->notify_email && filled($this->email);
    }

    /**
     * هل ضبط المستخدم تفضيلات التنبيهات؟ (لإظهار النافذة العائمة مرة واحدة).
     */
    public function hasConfiguredNotificationPrefs(): bool
    {
        return $this->notification_prefs_set_at !== null;
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function activeDeletionRequest(): BelongsTo
    {
        return $this->belongsTo(PrivacyRequest::class, 'deletion_request_id');
    }

    public function privacyRequests(): HasMany
    {
        return $this->hasMany(PrivacyRequest::class);
    }

    public function isAnonymized(): bool
    {
        return $this->account_status === AccountStatus::Anonymized;
    }

    public function allowsOperationalAccess(): bool
    {
        return ! in_array($this->account_status, [
            AccountStatus::Anonymized,
            AccountStatus::DeletionProcessing,
        ], true);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeOperational(Builder $query): Builder
    {
        return $query->whereNotIn('account_status', [
            AccountStatus::Anonymized->value,
            AccountStatus::DeletionProcessing->value,
        ]);
    }

    public function newEloquentBuilder($query): UserQueryBuilder
    {
        return new UserQueryBuilder($query);
    }

    public function delete(): ?bool
    {
        UserDeletionGuard::assertAuthorized();

        return parent::delete();
    }

    public function forceDelete(): ?bool
    {
        UserDeletionGuard::assertAuthorized();

        return parent::forceDelete();
    }

    public function privacyPolicyAcknowledgements(): HasMany
    {
        return $this->hasMany(PrivacyPolicyAcknowledgement::class);
    }

    public function candidatePoolPreference(): HasOne
    {
        return $this->hasOne(CandidatePoolPreference::class);
    }

    public function candidatePoolConsentEvents(): HasMany
    {
        return $this->hasMany(CandidatePoolConsentEvent::class);
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
        return $this->role_type === 'admin' || $this->hasRole('admin');
    }

    /**
     * حساب مدير النظام المحمي من الحذف (دور admin أو نوع admin أو بريد المسؤول من البيئة).
     */
    public function isProtectedAdminUser(): bool
    {
        if ($this->role_type === 'admin' || $this->hasRole('admin')) {
            return true;
        }

        $adminEmail = config('app.admin_email');

        return filled($adminEmail) && strcasecmp((string) $this->email, (string) $adminEmail) === 0;
    }

    public function isStaff(): bool
    {
        if ($this->role_type === 'staff') {
            return true;
        }

        return $this->hasAnyRole(RbacCatalog::staffRoleNames());
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
     * رابط صورة الموظف/المسؤول في لوحة Filament (قرص public).
     */
    public function staffPhotoUrl(): ?string
    {
        return PublicDiskPath::url($this->staff_photo);
    }

    /**
     * عرض أدوار Spatie للواجهة العربية (لوحة الإدارة).
     */
    public function filamentStaffRoleLabelsAr(): string
    {
        $this->loadMissing('roles');

        $names = $this->roles->pluck('name')->unique()->filter()->values();
        if ($names->isEmpty()) {
            return match ($this->role_type) {
                'admin' => RbacCatalog::roleArabicLabel('admin'),
                'staff' => RbacCatalog::roleArabicLabel('staff'),
                default => (string) $this->role_type,
            };
        }

        return $names->map(fn (string $n): string => RbacCatalog::roleArabicLabel($n))->implode('، ');
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
        if ($this->isAnonymized() || $this->account_status === AccountStatus::DeletionProcessing) {
            return false;
        }

        if (! $this->is_active) {
            return false;
        }

        // role_type يكفي وحده لمنح الوصول، دون الحاجة لـ Spatie role
        if (in_array($this->role_type, ['admin', 'staff'], true)) {
            return true;
        }

        return $this->hasAnyRole([
            'admin',
            ...RbacCatalog::staffRoleNames(),
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
                        ...RbacCatalog::staffRoleNames(),
                    ]));
            });
    }
}
