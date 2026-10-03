<?php

namespace App\Enums;

enum OpnameStatus: string
{
    case Draft = 'draft';
    case Counting = 'counting';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Counting => 'Counting',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Completed => 'Completed',
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Draft => in_array($to, [self::Counting, self::Submitted], true),
            self::Counting => $to === self::Submitted,
            self::Submitted => in_array($to, [self::Approved, self::Rejected], true),
            self::Approved => $to === self::Completed,
            self::Rejected, self::Completed => false,
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Counting, self::Submitted, self::Approved], true);
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Counting], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Rejected, self::Completed], true);
    }
}
