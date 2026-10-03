<?php

namespace App\Enums;

enum CustomerType: string
{
    case Customer = 'customer';
    case Department = 'department';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Department => 'Department',
        };
    }
}
