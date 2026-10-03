<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['prefix', 'period', 'last_number'])]
class DocumentSequence extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'last_number' => 'integer',
        ];
    }
}
