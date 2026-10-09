<?php

namespace App\Enums;

enum PickStatus: string
{
    case Pending = 'pending';
    case Picking = 'picking';
    case Picked = 'picked';
    case Packed = 'packed';
    case Short = 'short';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Picking => 'Sedang Dipicking',
            self::Picked => 'Selesai Picking',
            self::Packed => 'Dikemas',
            self::Short => 'Kurang',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
