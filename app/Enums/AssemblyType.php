<?php

namespace App\Enums;

enum AssemblyType: string
{
    case Assembly = 'assembly';
    case Disassembly = 'disassembly';

    public function label(): string
    {
        return match ($this) {
            self::Assembly => 'Perakitan',
            self::Disassembly => 'Pembongkaran',
        };
    }
}
