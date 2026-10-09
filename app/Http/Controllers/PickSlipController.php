<?php

namespace App\Http\Controllers;

use App\Models\PickList;

class PickSlipController extends Controller
{
    public function slip(PickList $pickList)
    {
        abort_unless(auth()->user()?->hasPermission('picking.view'), 403);
        abort_unless(auth()->user()?->canAccessWarehouse((int) $pickList->warehouse_id), 403);

        $pickList->load(['warehouse', 'goodsIssue', 'salesOrder', 'items.item', 'items.unit', 'items.location.rack.zone.warehouse']);

        return view('picking.slip', ['pickList' => $pickList]);
    }
}
