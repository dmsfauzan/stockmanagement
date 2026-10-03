<?php

namespace App\Policies;

use App\Models\StockOpname;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StockOpnamePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('stock_opname.view');
    }

    public function view(User $user, StockOpname $opname): bool
    {
        return $user->hasPermission('stock_opname.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('stock_opname.create');
    }

    public function update(User $user, StockOpname $opname): bool|Response
    {
        if (! $user->hasPermission('stock_opname.create')) {
            return false;
        }

        if ($opname->status !== 'draft') {
            return Response::deny('Only draft opnames can be updated');
        }

        return true;
    }

    public function submit(User $user, StockOpname $opname): bool|Response
    {
        if (! $user->hasPermission('stock_opname.submit')) {
            return false;
        }

        if (! in_array($opname->status, ['draft', 'counting'], true)) {
            return Response::deny('Only draft or counting opnames can be submitted');
        }

        return true;
    }

    public function approve(User $user, StockOpname $opname): bool|Response
    {
        if (! $user->hasPermission('stock_opname.approve')) {
            return false;
        }

        if ($opname->status !== 'submitted') {
            return Response::deny('Only submitted opnames can be approved');
        }

        return true;
    }

    public function reject(User $user, StockOpname $opname): bool|Response
    {
        if (! $user->hasPermission('stock_opname.approve')) {
            return false;
        }

        if ($opname->status !== 'submitted') {
            return Response::deny('Only submitted opnames can be rejected');
        }

        return true;
    }
}
