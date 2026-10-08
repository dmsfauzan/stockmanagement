<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_type', 'severity', 'ip_address', 'country_code', 'country_name',
    'user_id', 'email', 'method', 'path', 'user_agent', 'referer', 'meta', 'created_at',
])]
class SecurityEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    public const UPDATED_AT = null;

    public const CREATED_AT = 'created_at';

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
