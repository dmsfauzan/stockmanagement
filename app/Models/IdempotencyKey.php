<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'key', 'user_id', 'method', 'path', 'request_hash',
    'status_code', 'response_body', 'response_headers', 'locked_at',
])]
class IdempotencyKey extends Model
{
    protected function casts(): array
    {
        return [
            'response_headers' => 'array',
            'locked_at' => 'datetime',
            'status_code' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
