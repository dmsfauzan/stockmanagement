<?php

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StockTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('transfer.view');
    }

    public function view(User $user, StockTransfer $transfer): bool
    {
        return $user->hasPermission('transfer.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('transfer.create');
    }

    public function update(User $user, StockTransfer $transfer): bool|Response
    {
        if (! $user->hasPermission('transfer.create')) {
            return false;
        }

        if ($transfer->status !== 'draft') {
            return Response::deny('Only draft transfers can be updated');
        }

        return true;
    }

    public function request(User $user, StockTransfer $transfer): bool|Response
    {
        if (! $user->hasPermission('transfer.create')) {
            return false;
        }

        if ($transfer->status !== 'draft') {
            return Response::deny('Only draft transfers can be requested');
        }

        return true;
    }

    public function approve(User $user, StockTransfer $transfer): bool|Response
    {
        if (! $user->hasPermission('transfer.approve')) {
            return false;
        }

        if ($transfer->status !== 'requested') {
            return Response::deny('Only requested transfers can be approved');
        }

        return true;
    }

    public function reject(User $user, StockTransfer $transfer): bool|Response
    {
        if (! $user->hasPermission('transfer.approve')) {
            return false;
        }

        if ($transfer->status !== 'requested') {
            return Response::deny('Only requested transfers can be rejected');
        }

        return true;
    }

    public function dispatch(User $user, StockTransfer $transfer): bool|Response
    {
        if (! $user->hasPermission('transfer.approve')) {
            return false;
        }

        if ($transfer->status !== 'approved') {
            return Response::deny('Only approved transfers can be dispatched');
        }

        return true;
    }

    public function receive(User $user, StockTransfer $transfer): bool|Response
    {
        if (! $user->hasPermission('transfer.receive')) {
            return false;
        }

        if ($transfer->status !== 'in_transit') {
            return Response::deny('Only in-transit transfers can be received');
        }

        return true;
    }

    public function complete(User $user, StockTransfer $transfer): bool|Response
    {
        if (! $user->hasPermission('transfer.receive')) {
            return false;
        }

        if ($transfer->status !== 'received') {
            return Response::deny('Only received transfers can be completed');
        }

        return true;
    }
}
