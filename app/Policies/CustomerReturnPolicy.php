<?php

namespace App\Policies;

use App\Models\CustomerReturn;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CustomerReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('customer_return.view');
    }

    public function view(User $user, CustomerReturn $return): bool
    {
        return $user->hasPermission('customer_return.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('customer_return.create');
    }

    public function update(User $user, CustomerReturn $return): bool|Response
    {
        if (! $user->hasPermission('customer_return.update')) {
            return false;
        }

        if ($return->status !== 'draft') {
            return Response::deny('Only draft returns can be updated');
        }

        return true;
    }

    public function submit(User $user, CustomerReturn $return): bool|Response
    {
        if (! $user->hasPermission('customer_return.submit')) {
            return false;
        }

        if ($return->status !== 'draft') {
            return Response::deny('Only draft returns can be submitted');
        }

        return true;
    }

    public function approve(User $user, CustomerReturn $return): bool|Response
    {
        if (! $user->hasPermission('customer_return.approve')) {
            return false;
        }

        if ($return->status !== 'submitted') {
            return Response::deny('Only submitted returns can be approved');
        }

        return true;
    }

    public function reject(User $user, CustomerReturn $return): bool|Response
    {
        return $this->approve($user, $return);
    }

    public function post(User $user, CustomerReturn $return): bool|Response
    {
        if (! $user->hasPermission('customer_return.post')) {
            return false;
        }

        if ($return->status !== 'approved') {
            return Response::deny('Only approved returns can be posted');
        }

        return true;
    }

    public function reverse(User $user, CustomerReturn $return): bool|Response
    {
        if (! $user->hasPermission('customer_return.post')) {
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
