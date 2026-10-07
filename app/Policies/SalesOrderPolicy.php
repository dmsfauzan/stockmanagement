<?php

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sales_order.view');
    }

    public function view(User $user, SalesOrder $order): bool
    {
        return $user->hasPermission('sales_order.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sales_order.create');
    }

    public function update(User $user, SalesOrder $order): bool|Response
    {
        if (! $user->hasPermission('sales_order.update')) {
            return false;
        }

        if ($order->status !== 'draft') {
            return Response::deny('Only draft sales orders can be updated');
        }

        return true;
    }

    public function submit(User $user, SalesOrder $order): bool|Response
    {
        if (! $user->hasPermission('sales_order.submit')) {
            return false;
        }

        if ($order->status !== 'draft') {
            return Response::deny('Only draft sales orders can be submitted');
        }

        return true;
    }

    public function approve(User $user, SalesOrder $order): bool|Response
    {
        if (! $user->hasPermission('sales_order.approve')) {
            return false;
        }

        if ($order->status !== 'submitted') {
            return Response::deny('Only submitted sales orders can be approved');
        }

        return true;
    }

    public function reject(User $user, SalesOrder $order): bool|Response
    {
        if (! $user->hasPermission('sales_order.approve')) {
            return false;
        }

        if ($order->status !== 'submitted') {
            return Response::deny('Only submitted sales orders can be rejected');
        }

        return true;
    }

    public function fulfill(User $user, SalesOrder $order): bool|Response
    {
        if (! $user->hasPermission('sales_order.fulfill')) {
            return false;
        }

        if (! in_array($order->status, ['approved', 'partial'], true)) {
            return Response::deny('Only approved or partial sales orders can be fulfilled');
        }

        return true;
    }

    public function close(User $user, SalesOrder $order): bool|Response
    {
        if (! $user->hasPermission('sales_order.approve')) {
            return false;
        }

        if ($order->status !== 'fulfilled') {
            return Response::deny('Only fulfilled sales orders can be closed');
        }

        return true;
    }
}
