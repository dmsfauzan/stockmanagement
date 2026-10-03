<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Posted = 'posted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Posted => 'Posted',
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Draft => $to === self::Submitted,
            self::Submitted => in_array($to, [self::Approved, self::Rejected], true),
            self::Approved => $to === self::Posted,
            self::Rejected, self::Posted => false,
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Submitted], true);
    }

    public function isPosted(): bool
    {
        return $this === self::Posted;
    }
}
