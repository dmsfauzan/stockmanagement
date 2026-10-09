<?php

namespace App\Services\Support;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;

class WarehouseAccess
{
    /**
     * Warehouse ids the given (or current) user may access.
     *
     * @return array<int, int>
     */
    public static function ids(?User $user = null): array
    {
        $user ??= auth()->user();

        if (! $user) {
            return [];
        }

        return $user->accessibleWarehouseIds();
    }

    /**
     * Constrain a query to the user's accessible warehouses.
     */
    public static function apply(Builder $query, string $column = 'warehouse_id', ?User $user = null): Builder
    {
        $ids = static::ids($user);

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $ids);
    }

    /**
     * The active warehouse from session, only if the user may access it.
     */
    public static function activeId(?User $user = null): ?int
    {
        $user ??= auth()->user();

        $session = session('active_warehouse_id');

        if ($session === null || $session === '' || $session === 0 || $session === '0') {
            return null;
        }

        if ($user && ! $user->canAccessWarehouse((int) $session)) {
            return null;
        }

        return (int) $session;
    }

    public static function defaultId(?User $user = null): ?int
    {
        $user ??= auth()->user();

        if (! $user) {
            return null;
        }

        return Warehouse::query()->whereIn('id', static::ids($user))->orderBy('name')->value('id');
    }
}
