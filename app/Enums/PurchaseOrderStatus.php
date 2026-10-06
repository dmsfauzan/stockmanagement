<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Partial = 'partial';
    case Received = 'received';
    case Closed = 'closed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Partial => 'Partial',
            self::Received => 'Received',
            self::Closed => 'Closed',
            self::Rejected => 'Rejected',
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Draft => $to === self::Submitted,
            self::Submitted => in_array($to, [self::Approved, self::Rejected], true),
            self::Approved => in_array($to, [self::Partial, self::Received], true),
            self::Partial => $to === self::Received,
            self::Received => $to === self::Closed,
            self::Rejected, self::Closed => false,
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Submitted, self::Approved, self::Partial, self::Received], true);
    }

    public function isPostedLike(): bool
    {
        return in_array($this, [self::Approved, self::Partial, self::Received, self::Closed], true);
    }
}
