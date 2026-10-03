<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('items.view');
    }

    public function view(User $user, Item $item): bool
    {
        return $user->hasPermission('items.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('items.create');
    }

    public function update(User $user, Item $item): bool
    {
        return $user->hasPermission('items.update');
    }

    public function delete(User $user, Item $item): bool|Response
    {
        if (! $user->hasPermission('items.delete')) {
            return false;
        }

        if ($this->hasStockMovements($item)) {
            return Response::deny('Cannot delete item with existing transactions');
        }

        return true;
    }

    public function hasStockMovements(Item $item): bool
    {
        return StockMovement::where('item_id', $item->id)->exists();
    }
}
