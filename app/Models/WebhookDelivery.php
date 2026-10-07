<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['event', 'url', 'payload', 'response_status', 'success', 'attempts', 'error'])]
class WebhookDelivery extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response_status' => 'integer',
            'success' => 'boolean',
            'attempts' => 'integer',
        ];
    }
}
