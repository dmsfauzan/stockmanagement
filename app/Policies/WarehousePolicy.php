<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Auth\Access\Response;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('warehouse.view');
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->hasPermission('warehouse.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('warehouse.create');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->hasPermission('warehouse.update');
    }

    public function delete(User $user, Warehouse $warehouse): bool|Response
    {
        if (! $user->hasPermission('warehouse.delete')) {
            return false;
        }

        if ($warehouse->zones()->exists()) {
            return Response::deny('Cannot delete warehouse that still has zones');
        }

        return true;
    }
}
