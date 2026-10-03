<?php

namespace App\Policies;

use App\Models\GoodsReceipt;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GoodsReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('goods_receipt.view');
    }

    public function view(User $user, GoodsReceipt $receipt): bool
    {
        return $user->hasPermission('goods_receipt.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('goods_receipt.create');
    }

    public function update(User $user, GoodsReceipt $receipt): bool|Response
    {
        if (! $user->hasPermission('goods_receipt.update')) {
            return false;
        }

        if ($receipt->status !== 'draft') {
            return Response::deny('Only draft receipts can be updated');
        }

        return true;
    }

    public function submit(User $user, GoodsReceipt $receipt): bool|Response
    {
        if (! $user->hasPermission('goods_receipt.submit')) {
            return false;
        }

        if ($receipt->status !== 'draft') {
            return Response::deny('Only draft receipts can be submitted');
        }

        return true;
    }

    public function approve(User $user, GoodsReceipt $receipt): bool|Response
    {
        if (! $user->hasPermission('goods_receipt.approve')) {
            return false;
        }

        if ($receipt->status !== 'submitted') {
            return Response::deny('Only submitted receipts can be approved');
        }

        return true;
    }

    public function reject(User $user, GoodsReceipt $receipt): bool|Response
    {
        if (! $user->hasPermission('goods_receipt.approve')) {
            return false;
        }

        if ($receipt->status !== 'submitted') {
            return Response::deny('Only submitted receipts can be rejected');
        }

        return true;
    }

    public function post(User $user, GoodsReceipt $receipt): bool|Response
    {
        if (! $user->hasPermission('goods_receipt.post')) {
            return false;
        }

        if ($receipt->status !== 'approved') {
            return Response::deny('Only approved receipts can be posted');
        }

        return true;
    }

    public function reverse(User $user, GoodsReceipt $receipt): bool|Response
    {
        if (! $user->hasPermission('goods_receipt.post')) {
            return false;
        }

        if ($receipt->status !== 'posted') {
            return Response::deny('Only posted receipts can be reversed');
        }

        if ($receipt->isReversed()) {
            return Response::deny('Transaction already reversed');
        }

        return true;
    }
}
