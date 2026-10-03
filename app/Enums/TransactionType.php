<?php

namespace App\Enums;

enum TransactionType: string
{
    case Opening = 'opening';
    case Incoming = 'incoming';
    case Outgoing = 'outgoing';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening',
            self::Incoming => 'Incoming',
            self::Outgoing => 'Outgoing',
            self::TransferIn => 'Transfer In',
            self::TransferOut => 'Transfer Out',
            self::AdjustmentIn => 'Adjustment In',
            self::AdjustmentOut => 'Adjustment Out',
        };
    }
}
