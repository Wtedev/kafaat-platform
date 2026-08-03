<?php

namespace App\Auth;

use App\Support\Auth\EmailNormalizer;
use Closure;
use Illuminate\Auth\EloquentUserProvider as BaseEloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Case-insensitive email credential lookup for Auth::attempt, Filament login,
 * and password-reset brokers (all use the user provider).
 *
 * Password validation is unchanged (Hash::check / timing-safe compare).
 */
class EloquentUserProvider extends BaseEloquentUserProvider
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        $credentials = array_filter(
            $credentials,
            fn ($key) => ! str_contains((string) $key, 'password'),
            ARRAY_FILTER_USE_KEY
        );

        if ($credentials === []) {
            return null;
        }

        $query = $this->newModelQuery();

        foreach ($credentials as $key => $value) {
            if (is_array($value) || $value instanceof Arrayable) {
                $query->whereIn($key, $value);
            } elseif ($value instanceof Closure) {
                $value($query);
            } elseif ($key === 'email' && is_string($value)) {
                $normalized = EmailNormalizer::normalize($value);
                $query->whereRaw('lower(email) = ?', [$normalized]);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }
}
