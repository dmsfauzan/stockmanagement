<?php

namespace App\Enums;

enum TransferStatus: string
{
    case Draft = 'draft';
    case Requested = 'requested';
    case Approved = 'approved';
    case InTransit = 'in_transit';
    case Received = 'received';
    case Rejected = 'rejected';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Requested => 'Requested',
            self::Approved => 'Approved',
            self::InTransit => 'In Transit',
            self::Received => 'Received',
            self::Rejected => 'Rejected',
            self::Completed => 'Completed',
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Draft => $to === self::Requested,
            self::Requested => in_array($to, [self::Approved, self::Rejected], true),
            self::Approved => $to === self::InTransit,
            self::InTransit => $to === self::Received,
            self::Received => $to === self::Completed,
            self::Rejected, self::Completed => false,
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Rejected, self::Completed], true);
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
