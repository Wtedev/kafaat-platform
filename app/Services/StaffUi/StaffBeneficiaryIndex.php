<?php

namespace App\Services\StaffUi;

use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

final class StaffBeneficiaryIndex
{
    public function paginate(string $search, string $status, string $completeness, int $page, int $perPage = 15): LengthAwarePaginator
    {
        return $this->paginateCollection($this->matching($search, $status, $completeness), $page, $perPage);
    }

    /**
     * المستفيدون المطابقون للبحث والفلاتر، بلا ترقيم صفحات.
     *
     * @return Collection<int, User>
     */
    public function matching(string $search, string $status, string $completeness): Collection
    {
        return $this->query($search, $status)
            ->with('profile')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $this->matchesCompleteness($user, $completeness))
            ->values();
    }

    /**
     * @return Builder<User>
     */
    private function query(string $search, string $status): Builder
    {
        $query = User::query()
            ->operational()
            ->whereNull('privacy_deleted_at')
            ->whereNull('anonymized_at')
            ->where(function (Builder $portal): void {
                $portal->whereIn('role_type', ['beneficiary', 'volunteer', 'trainee'])
                    ->orWhereHas('roles', function (Builder $roles): void {
                        $roles->whereIn('name', [
                            RbacCatalog::ROLE_BENEFICIARY,
                            RbacCatalog::ROLE_VOLUNTEER,
                            'trainee',
                        ]);
                    });
            })
            ->whereNotIn('role_type', ['admin', 'staff'])
            ->whereDoesntHave('roles', function (Builder $roles): void {
                $roles->whereIn('name', [RbacCatalog::ROLE_ADMIN, RbacCatalog::ROLE_STAFF]);
            });

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $needle = trim($search);
        if ($needle !== '') {
            $like = '%'.addcslashes($needle, '%_\\').'%';
            $query->where(function (Builder $match) use ($like): void {
                $match->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('father_name', 'like', $like)
                    ->orWhere('grandfather_name', 'like', $like)
                    ->orWhere('family_name', 'like', $like);
            });
        }

        return $query;
    }

    private function matchesCompleteness(User $user, string $completeness): bool
    {
        if ($completeness === '') {
            return true;
        }

        $complete = $user->hasCompletedRequiredIdentityData();

        return $completeness === 'complete' ? $complete : ! $complete;
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function paginateCollection(Collection $users, int $page, int $perPage): LengthAwarePaginator
    {
        $page = max(1, $page);

        return new Paginator(
            $users->forPage($page, $perPage)->values(),
            $users->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }
}
