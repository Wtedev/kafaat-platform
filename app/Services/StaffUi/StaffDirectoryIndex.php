<?php

namespace App\Services\StaffUi;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class StaffDirectoryIndex
{
    public function paginate(string $search, string $role, string $status, int $page, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($search, $role, $status)
            ->with('roles')
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', max(1, $page))
            ->withQueryString();
    }

    public function count(): int
    {
        return $this->query('', '', '')->count();
    }

    /**
     * @return Builder<User>
     */
    private function query(string $search, string $role, string $status): Builder
    {
        $query = User::query()
            ->whereNotIn('account_status', [
                AccountStatus::Anonymized->value,
                AccountStatus::DeletionPending->value,
                AccountStatus::DeletionProcessing->value,
            ])
            ->where(function (Builder $staff): void {
                $staff->whereIn('role_type', StaffInvitationService::ROLES)
                    ->orWhereHas('roles', function (Builder $roles): void {
                        $roles->whereIn('name', StaffInvitationService::ROLES);
                    });
            });

        if ($role === RbacCatalog::ROLE_ADMIN || $role === RbacCatalog::ROLE_STAFF) {
            $query->where(function (Builder $match) use ($role): void {
                $match->where('role_type', $role)
                    ->orWhereHas('roles', fn (Builder $roles) => $roles->where('name', $role));
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'invited') {
            $query->where('is_active', false)->whereNotNull('invited_at');
        } elseif ($status === 'inactive') {
            $query->where('is_active', false)->whereNull('invited_at');
        }

        $needle = trim($search);
        if ($needle !== '') {
            $like = '%'.addcslashes($needle, '%_\\').'%';
            $query->where(function (Builder $match) use ($like): void {
                $match->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        return $query;
    }
}
