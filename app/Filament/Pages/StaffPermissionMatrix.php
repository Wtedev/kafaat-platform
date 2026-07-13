<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\Rbac\PermissionMatrixCatalog;
use App\Services\Rbac\RbacCatalog;
use App\Services\Rbac\StaffPermissionService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class StaffPermissionMatrix extends Page
{
    protected static ?string $slug = 'staff-permissions';

    protected static ?string $navigationLabel = 'مصفوفة صلاحيات الموظفين';

    protected static ?string $title = 'مصفوفة صلاحيات الموظفين';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'الأمان والامتثال';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.staff-permission-matrix';

    public ?int $activeStaffId = null;

    public string $staffSearch = '';

    public bool $isDirty = false;

    /**
     * @var array<string, array<string, bool>>
     */
    public array $matrix = [];

    /**
     * @var array<string, array<string, bool>>
     */
    public array $savedMatrix = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $first = $this->staffUsers()->first();
        if ($first !== null) {
            $this->selectStaff((int) $first->id);
        }
    }

    public function getTitle(): string|Htmlable
    {
        return 'مصفوفة صلاحيات الموظفين';
    }

    /**
     * @return Collection<int, User>
     */
    public function staffUsers(): Collection
    {
        $query = User::query()
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->where('role_type', RbacCatalog::ROLE_STAFF)
                    ->orWhereHas('roles', fn ($r) => $r->where('name', RbacCatalog::ROLE_STAFF));
            })
            ->whereDoesntHave('roles', fn ($r) => $r->where('name', RbacCatalog::ROLE_ADMIN))
            ->where('role_type', '!=', RbacCatalog::ROLE_ADMIN)
            ->orderBy('name')
            ->orderBy('email');

        $search = trim($this->staffSearch);
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->get(['id', 'name', 'email', 'role_type', 'staff_photo']);
    }

    public function updatedStaffSearch(): void
    {
        // البحث يعيد رسم القائمة فقط
    }

    public function selectStaff(int $staffId): void
    {
        if ($this->isDirty && $this->activeStaffId !== null && $this->activeStaffId !== $staffId) {
            Notification::make()
                ->warning()
                ->title('توجد تعديلات غير محفوظة')
                ->body('احفظ الصلاحيات أولاً أو ألغِ التعديلات قبل الانتقال لموظف آخر.')
                ->send();

            return;
        }

        $staff = $this->resolveStaff($staffId);
        $this->activeStaffId = $staff->id;
        $owned = $staff->getPermissionNames()->all();
        $this->matrix = PermissionMatrixCatalog::checkboxStateFromPermissions($owned);
        $this->savedMatrix = $this->matrix;
        $this->isDirty = false;
    }

    public function discardChanges(): void
    {
        $this->matrix = $this->savedMatrix;
        $this->isDirty = false;

        Notification::make()
            ->info()
            ->title('تم التراجع عن التعديلات')
            ->send();
    }

    public function toggleGroup(string $groupKey): void
    {
        if (! isset($this->matrix[$groupKey])) {
            return;
        }

        $enable = ! ($this->matrix[$groupKey]['all'] ?? false);
        $group = collect(PermissionMatrixCatalog::groups())->firstWhere('key', $groupKey);
        if ($group === null) {
            return;
        }

        foreach (PermissionMatrixCatalog::actionKeys() as $action) {
            $perms = $group['actions'][$action] ?? null;
            if (! is_array($perms) || $perms === []) {
                continue;
            }
            $this->matrix[$groupKey][$action] = $enable;
        }

        $this->refreshGroupAll($groupKey);
        $this->isDirty = true;
    }

    public function toggleAction(string $groupKey, string $action): void
    {
        if (! isset($this->matrix[$groupKey])) {
            return;
        }

        $enabledFlag = $action.'_enabled';
        if (! ($this->matrix[$groupKey][$enabledFlag] ?? false)) {
            return;
        }

        $this->matrix[$groupKey][$action] = ! ($this->matrix[$groupKey][$action] ?? false);
        $this->refreshGroupAll($groupKey);
        $this->isDirty = true;
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        if ($this->activeStaffId === null) {
            return;
        }

        $staff = $this->resolveStaff($this->activeStaffId);
        $permissions = PermissionMatrixCatalog::permissionsFromCheckboxState($this->matrix);

        app(StaffPermissionService::class)->syncAssignablePermissions(
            $staff,
            $permissions,
            auth()->user(),
        );

        $this->savedMatrix = $this->matrix;
        $this->isDirty = false;

        Notification::make()
            ->success()
            ->title('تم حفظ الصلاحيات')
            ->body('تم تحديث صلاحيات «'.$staff->name.'» بنجاح.')
            ->send();
    }

    public function grantAll(): void
    {
        abort_unless(static::canAccess(), 403);
        if ($this->activeStaffId === null) {
            return;
        }

        foreach (PermissionMatrixCatalog::groups() as $group) {
            foreach (PermissionMatrixCatalog::actionKeys() as $action) {
                $perms = $group['actions'][$action] ?? null;
                if (! is_array($perms) || $perms === []) {
                    continue;
                }
                $this->matrix[$group['key']][$action] = true;
            }
            $this->refreshGroupAll($group['key']);
        }

        $this->isDirty = true;
    }

    public function clearAll(): void
    {
        abort_unless(static::canAccess(), 403);
        if ($this->activeStaffId === null) {
            return;
        }

        foreach (PermissionMatrixCatalog::groups() as $group) {
            foreach (PermissionMatrixCatalog::actionKeys() as $action) {
                $this->matrix[$group['key']][$action] = false;
            }
            $this->refreshGroupAll($group['key']);
        }

        $this->isDirty = true;
    }

    /**
     * @return list<array{section: array{key: string, label: string, description: string}, groups: list<array>}>
     */
    public function sectionsWithGroups(): array
    {
        return PermissionMatrixCatalog::sectionsWithGroups();
    }

    /** @return array<string, string> */
    public function actionLabels(): array
    {
        return PermissionMatrixCatalog::actionLabelsAr();
    }

    public function activePermissionCount(): int
    {
        return count(PermissionMatrixCatalog::permissionsFromCheckboxState($this->matrix));
    }

    public function totalAssignableCount(): int
    {
        return count(PermissionMatrixCatalog::assignablePermissionNames());
    }

    public function activeStaff(): ?User
    {
        if ($this->activeStaffId === null) {
            return null;
        }

        return User::query()
            ->select(['id', 'name', 'email', 'role_type', 'staff_photo'])
            ->find($this->activeStaffId);
    }

    private function refreshGroupAll(string $groupKey): void
    {
        $group = collect(PermissionMatrixCatalog::groups())->firstWhere('key', $groupKey);
        if ($group === null) {
            return;
        }

        $available = 0;
        $checked = 0;
        foreach (PermissionMatrixCatalog::actionKeys() as $action) {
            $perms = $group['actions'][$action] ?? null;
            if (! is_array($perms) || $perms === []) {
                $this->matrix[$groupKey][$action.'_enabled'] = false;

                continue;
            }
            $this->matrix[$groupKey][$action.'_enabled'] = true;
            $available++;
            if ($this->matrix[$groupKey][$action] ?? false) {
                $checked++;
            }
        }

        $this->matrix[$groupKey]['all'] = $available > 0 && $checked === $available;
    }

    private function resolveStaff(int $staffId): User
    {
        $staff = User::query()->findOrFail($staffId);
        abort_if($staff->isAdmin() || $staff->isProtectedAdminUser(), 403);
        abort_unless(
            $staff->role_type === RbacCatalog::ROLE_STAFF || $staff->hasRole(RbacCatalog::ROLE_STAFF),
            404
        );

        return $staff;
    }
}
