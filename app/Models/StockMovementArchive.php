<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class StockMovementArchive extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'int';

    protected $table = 'stock_movement_archives';

    protected $guarded = [];

    protected $casts = [
        'expiry_date' => 'date',
        'created_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public static function activeCount(): int
    {
        return DB::table('stock_movements')->count();
    }

    public static function archivedCount(): int
    {
        return DB::table('stock_movement_archives')->count();
    }
}
