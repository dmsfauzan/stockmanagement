<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchase_order.view');
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $user->hasPermission('purchase_order.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchase_order.create');
    }

    public function update(User $user, PurchaseOrder $order): bool|Response
    {
        if (! $user->hasPermission('purchase_order.update')) {
            return false;
        }

        if ($order->status !== 'draft') {
            return Response::deny('Only draft purchase orders can be updated');
        }

        return true;
    }

    public function submit(User $user, PurchaseOrder $order): bool|Response
    {
        if (! $user->hasPermission('purchase_order.submit')) {
            return false;
        }

        if ($order->status !== 'draft') {
            return Response::deny('Only draft purchase orders can be submitted');
        }

        return true;
    }

    public function approve(User $user, PurchaseOrder $order): bool|Response
    {
        if (! $user->hasPermission('purchase_order.approve')) {
            return false;
        }

        if ($order->status !== 'submitted') {
            return Response::deny('Only submitted purchase orders can be approved');
        }

        return true;
    }

    public function reject(User $user, PurchaseOrder $order): bool|Response
    {
        if (! $user->hasPermission('purchase_order.approve')) {
            return false;
        }

        if ($order->status !== 'submitted') {
            return Response::deny('Only submitted purchase orders can be rejected');
        }

        return true;
    }

    public function receive(User $user, PurchaseOrder $order): bool|Response
    {
        if (! $user->hasPermission('purchase_order.receive')) {
            return false;
        }

        if (! in_array($order->status, ['approved', 'partial'], true)) {
            return Response::deny('Only approved or partial purchase orders can be received');
        }

        return true;
    }

    public function close(User $user, PurchaseOrder $order): bool|Response
    {
        if (! $user->hasPermission('purchase_order.approve')) {
            return false;
        }

        if ($order->status !== 'received') {
            return Response::deny('Only received purchase orders can be closed');
        }

        return true;
    }
}
