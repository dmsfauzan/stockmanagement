<?php

namespace App\Policies;

use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StockAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('stock.adjustment');
    }

    public function view(User $user, StockAdjustment $adjustment): bool
    {
        return $user->hasPermission('stock.adjustment');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('stock.adjustment');
    }

    public function update(User $user, StockAdjustment $adjustment): bool|Response
    {
        if (! $user->hasPermission('stock.adjustment')) {
            return false;
        }

        if ($adjustment->status !== 'draft') {
            return Response::deny('Only draft adjustments can be updated');
        }

        return true;
    }

    public function submit(User $user, StockAdjustment $adjustment): bool|Response
    {
        if (! $user->hasPermission('stock.adjustment')) {
            return false;
        }

        if ($adjustment->status !== 'draft') {
            return Response::deny('Only draft adjustments can be submitted');
        }

        return true;
    }

    public function approve(User $user, StockAdjustment $adjustment): bool|Response
    {
        if (! $user->hasPermission('stock.adjustment.approve')) {
            return false;
        }

        if ($adjustment->status !== 'submitted') {
            return Response::deny('Only submitted adjustments can be approved');
        }

        return true;
    }

    public function reject(User $user, StockAdjustment $adjustment): bool|Response
    {
        if (! $user->hasPermission('stock.adjustment.approve')) {
            return false;
        }

        if ($adjustment->status !== 'submitted') {
            return Response::deny('Only submitted adjustments can be rejected');
        }

        return true;
    }

    public function post(User $user, StockAdjustment $adjustment): bool|Response
    {
        if (! $user->hasPermission('stock.adjustment.approve')) {
            return false;
        }

        if ($adjustment->status !== 'approved') {
            return Response::deny('Only approved adjustments can be posted');
        }

        return true;
    }

    public function reverse(User $user, StockAdjustment $adjustment): bool|Response
    {
        if (! $user->hasPermission('stock.adjustment.approve')) {
            return false;
        }

        if ($adjustment->status !== 'posted') {
            return Response::deny('Only posted adjustments can be reversed');
        }

        if ($adjustment->isReversed()) {
            return Response::deny('Transaction already reversed');
        }

        return true;
    }
}
