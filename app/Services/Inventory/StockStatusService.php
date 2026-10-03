<?php

namespace App\Services\Inventory;

use App\Enums\StockStatus;
use App\Models\Item;

class StockStatusService
{
    public static function evaluate(Item $item, int $onHand): StockStatus
    {
        return StockStatus::evaluate($onHand, (int) $item->minimum_stock, (int) $item->maximum_stock);
    }
}
