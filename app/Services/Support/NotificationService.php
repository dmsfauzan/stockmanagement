<?php

namespace App\Services\Support;

use App\Models\Item;
use App\Models\Notification;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;

class NotificationService
{
    public static function notify(?int $userId, string $type, string $title, string $message, ?string $referenceType = null, ?int $referenceId = null): ?Notification
    {
        if ($userId === null) {
            return null;
        }

        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    public static function notifyRole(string $roleSlug, string $type, string $title, string $message, ?string $referenceType = null, ?int $referenceId = null): void
    {
        $ids = User::whereHas('roles', fn ($q) => $q->where('slug', $roleSlug))->pluck('id');

        foreach ($ids as $userId) {
            static::notify((int) $userId, $type, $title, $message, $referenceType, $referenceId);
        }
    }

    public static function notifyApprovers(string $type, string $title, string $message, ?string $referenceType = null, ?int $referenceId = null): void
    {
        static::notifyRole('supervisor', $type, $title, $message, $referenceType, $referenceId);
        static::notifyRole('admin', $type, $title, $message, $referenceType, $referenceId);
    }

    public static function notifyLowStock(int $itemId, int $warehouseId): void
    {
        $item = Item::find($itemId);
        $warehouse = Warehouse::find($warehouseId);

        if (! $item || ! $warehouse) {
            return;
        }

        $onHand = (int) StockBalance::where('item_id', $itemId)->where('warehouse_id', $warehouseId)->sum('quantity_on_hand');
        $min = (int) ($item->minimum_stock ?? 0);

        if ($onHand <= 0) {
            $type = 'stock.out';
            $title = 'Out of stock';
            $message = $item->sku.' '.$item->name.' habis di '.$warehouse->name;
        } elseif ($onHand <= $min) {
            $type = 'stock.low';
            $title = 'Low stock';
            $message = $item->sku.' '.$item->name.' tinggal '.$onHand.' (min '.$min.') di '.$warehouse->name;
        } else {
            return;
        }

        static::notifyApprovers($type, $title, $message, Item::class, $itemId);
        static::notifyRole('warehouse_staff', $type, $title, $message, Item::class, $itemId);
    }
}
