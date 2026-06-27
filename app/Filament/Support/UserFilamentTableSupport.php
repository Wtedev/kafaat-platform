<?php

namespace App\Filament\Support;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class UserFilamentTableSupport
{
    public static function beneficiaryViewUrl(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $viewer = auth()->user();

        if ($viewer === null || ! $viewer->can('users.view')) {
            return null;
        }

        if (! UserResource::hasPage('view')) {
            return null;
        }

        if (! $user->isPortalUser()) {
            return null;
        }

        return UserResource::getUrl('view', ['record' => $user]);
    }

    public static function recordUrlFromUserRelation(Model $record, string $relation = 'user'): ?string
    {
        if (! method_exists($record, $relation)) {
            return null;
        }

        /** @var User|null $user */
        $user = $record->{$relation};

        return self::beneficiaryViewUrl($user);
    }

    public static function configureBeneficiaryRowNavigation(Table $table, string $userRelation = 'user'): Table
    {
        return $table->recordUrl(
            fn (Model $record): ?string => self::recordUrlFromUserRelation($record, $userRelation),
        );
    }
}
