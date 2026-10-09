<?php

namespace App\Enums;

enum QualityStatus: string
{
    case Good = 'good';
    case Quarantine = 'quarantine';
    case Rejected = 'rejected';
    case Recalled = 'recalled';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Baik',
            self::Quarantine => 'Karantina',
            self::Rejected => 'Ditolak',
            self::Recalled => 'Ditarik',
        };
    }
}
