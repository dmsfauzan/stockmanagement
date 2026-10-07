<?php

namespace App\Enums;

enum TrackingType: string
{
    case None = 'none';
    case Batch = 'batch';
    case Serial = 'serial';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Tanpa Lot',
            self::Batch => 'Batch / Lot',
            self::Serial => 'Serial Number',
        };
    }

    public function requiresBatch(): bool
    {
        return $this === self::Batch;
    }

    public function requiresSerial(): bool
    {
        return $this === self::Serial;
    }
}
