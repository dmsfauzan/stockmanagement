<?php

namespace App\Policies;

use App\Models\SupplierReturn;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SupplierReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('supplier_return.view');
    }

    public function view(User $user, SupplierReturn $return): bool
    {
        return $user->hasPermission('supplier_return.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('supplier_return.create');
    }

    public function update(User $user, SupplierReturn $return): bool|Response
    {
        if (! $user->hasPermission('supplier_return.update')) {
            return false;
        }

        if ($return->status !== 'draft') {
            return Response::deny('Only draft returns can be updated');
        }

        return true;
    }

    public function submit(User $user, SupplierReturn $return): bool|Response
    {
        if (! $user->hasPermission('supplier_return.submit')) {
            return false;
        }

        if ($return->status !== 'draft') {
            return Response::deny('Only draft returns can be submitted');
        }

        return true;
    }

    public function approve(User $user, SupplierReturn $return): bool|Response
    {
        if (! $user->hasPermission('supplier_return.approve')) {
            return false;
        }

        if ($return->status !== 'submitted') {
            return Response::deny('Only submitted returns can be approved');
        }

        return true;
    }

    public function reject(User $user, SupplierReturn $return): bool|Response
    {
        return $this->approve($user, $return);
    }

    public function post(User $user, SupplierReturn $return): bool|Response
    {
        if (! $user->hasPermission('supplier_return.post')) {
            return false;
        }

        if ($return->status !== 'approved') {
            return Response::deny('Only approved returns can be posted');
        }

        return true;
    }

    public function reverse(User $user, SupplierReturn $return): bool|Response
    {
        if (! $user->hasPermission('supplier_return.post')) {
            return false;
        }

        if ($return->status !== 'posted') {
            return Response::deny('Only posted returns can be reversed');
        }

        if ($return->reversed_at !== null) {
            return Response::deny('Transaction already reversed');
        }

        return true;
    }
}
