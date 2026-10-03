<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\StockBalance;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('location.view');
    }

    public function view(User $user, Location $location): bool
    {
        return $user->hasPermission('location.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('location.create');
    }

    public function update(User $user, Location $location): bool
    {
        return $user->hasPermission('location.update');
    }

    public function delete(User $user, Location $location): bool|Response
    {
        if (! $user->hasPermission('location.delete')) {
            return false;
        }

        if (StockBalance::where('location_id', $location->id)->exists()) {
            return Response::deny('Cannot delete location that still has stock balances');
        }

        return true;
    }
}
