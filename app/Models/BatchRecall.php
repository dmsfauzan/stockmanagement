<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['batch_number', 'serial_number', 'type', 'reason', 'status', 'recalled_by', 'recalled_at', 'lifted_by', 'lifted_at'])]
class BatchRecall extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'recalled_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }

    public function recaller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recalled_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
