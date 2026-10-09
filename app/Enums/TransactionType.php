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
    case ReturnIn = 'return_in';
    case ReturnOut = 'return_out';

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
            self::ReturnIn => 'Return In',
            self::ReturnOut => 'Return Out',
        };
    }
}
