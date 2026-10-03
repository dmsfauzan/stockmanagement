<?php

namespace App\Enums;

enum StockStatus: string
{
    case Normal = 'normal';
    case LowStock = 'low';
    case OutOfStock = 'out';
    case Overstock = 'over';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::LowStock => 'Low Stock',
            self::OutOfStock => 'Out of Stock',
            self::Overstock => 'Overstock',
        };
    }

    public static function evaluate(int $onHand, int $min, int $max): self
    {
        if ($onHand <= 0) {
            return self::OutOfStock;
        }

        if ($onHand <= $min) {
            return self::LowStock;
        }

        if ($max > 0 && $onHand > $max) {
            return self::Overstock;
        }

        return self::Normal;
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Normal => 'bg-emerald-100 text-emerald-700',
            self::LowStock => 'bg-amber-100 text-amber-700',
            self::OutOfStock => 'bg-rose-100 text-rose-700',
            self::Overstock => 'bg-sky-100 text-sky-700',
        };
    }
}
