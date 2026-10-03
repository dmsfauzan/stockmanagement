<?php

namespace App\Policies;

use App\Models\GoodsIssue;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GoodsIssuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('goods_issue.view');
    }

    public function view(User $user, GoodsIssue $issue): bool
    {
        return $user->hasPermission('goods_issue.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('goods_issue.create');
    }

    public function update(User $user, GoodsIssue $issue): bool|Response
    {
        if (! $user->hasPermission('goods_issue.update')) {
            return false;
        }

        if ($issue->status !== 'draft') {
            return Response::deny('Only draft issues can be updated');
        }

        return true;
    }

    public function submit(User $user, GoodsIssue $issue): bool|Response
    {
        if (! $user->hasPermission('goods_issue.submit')) {
            return false;
        }

        if ($issue->status !== 'draft') {
            return Response::deny('Only draft issues can be submitted');
        }

        return true;
    }

    public function approve(User $user, GoodsIssue $issue): bool|Response
    {
        if (! $user->hasPermission('goods_issue.approve')) {
            return false;
        }

        if ($issue->status !== 'submitted') {
            return Response::deny('Only submitted issues can be approved');
        }

        return true;
    }

    public function reject(User $user, GoodsIssue $issue): bool|Response
    {
        if (! $user->hasPermission('goods_issue.approve')) {
            return false;
        }

        if ($issue->status !== 'submitted') {
            return Response::deny('Only submitted issues can be rejected');
        }

        return true;
    }

    public function post(User $user, GoodsIssue $issue): bool|Response
    {
        if (! $user->hasPermission('goods_issue.post')) {
            return false;
        }

        if ($issue->status !== 'approved') {
            return Response::deny('Only approved issues can be posted');
        }

        return true;
    }

    public function reverse(User $user, GoodsIssue $issue): bool|Response
    {
        if (! $user->hasPermission('goods_issue.post')) {
            return false;
        }

        if ($issue->status !== 'posted') {
            return Response::deny('Only posted issues can be reversed');
        }

        if ($issue->isReversed()) {
            return Response::deny('Transaction already reversed');
        }

        return true;
    }
}
