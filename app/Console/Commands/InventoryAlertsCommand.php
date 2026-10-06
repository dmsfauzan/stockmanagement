<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Support\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryAlertsCommand extends Command
{
    protected $signature = 'inventory:alerts {--force : Bypass daily dedup}';

    protected $description = 'Scan low stock & expiring batches and create notifications (daily digest).';

    public function handle(): int
    {
        $today = Carbon::today();
        $warnDays = (int) (Setting::get('expiry.warn_days', 30) ?? 30);
        $criticalDays = (int) (Setting::get('expiry.critical_days', 7) ?? 7);

        $force = (bool) $this->option('force');

        $lowCreated = $this->digestLowStock($force);
        $expiryCreated = $this->digestExpiries($today, $warnDays, $criticalDays, $force);

        $this->info("Low/out alerts: {$lowCreated}. Expiry alerts: {$expiryCreated}.");

        return 0;
    }

    private function digestLowStock(bool $force): int
    {
        $rows = DB::table('stock_balances')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->whereNull('items.deleted_at')
            ->whereColumn('stock_balances.quantity_on_hand', '<=', 'items.minimum_stock')
            ->select([
                'stock_balances.item_id',
                'items.sku', 'items.name as item_name', 'items.minimum_stock',
                'stock_balances.quantity_on_hand as on_hand',
                'warehouses.name as warehouse_name',
            ])
            ->get();

        $created = 0;

        foreach ($rows as $row) {
            $type = ((int) $row->on_hand <= 0) ? 'stock.out' : 'stock.low';
            $title = ($type === 'stock.out') ? 'Out of stock' : 'Low stock';
            $message = $row->sku.' '.$row->item_name.' '
                .(($type === 'stock.out') ? 'habis' : 'tinggal '.(int) $row->on_hand.' (min '.(int) $row->minimum_stock.')')
                .' di '.$row->warehouse_name;

            if (! $force && $this->existsToday($type, 'item', (int) $row->item_id)) {
                continue;
            }

            $this->createForRoles($type, $title, $message, 'item', (int) $row->item_id);
            $created++;
        }

        return $created;
    }

    private function digestExpiries(Carbon $today, int $warnDays, int $criticalDays, bool $force): int
    {
        $cutoff = $today->copy()->addDays($warnDays)->toDateString();

        $rows = DB::table('stock_movements')
            ->join('items', 'stock_movements.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->where('stock_movements.transaction_type', 'incoming')
            ->whereNotNull('stock_movements.expiry_date')
            ->whereDate('stock_movements.expiry_date', '<=', $cutoff)
            ->select([
                'stock_movements.id', 'stock_movements.batch_number', 'stock_movements.expiry_date',
                'items.sku', 'items.name as item_name',
                'warehouses.name as warehouse_name',
            ])
            ->orderBy('stock_movements.expiry_date')
            ->limit(50)
            ->get();

        $created = 0;

        foreach ($rows as $row) {
            $days = (int) $today->copy()->startOfDay()->diffInDays(Carbon::parse($row->expiry_date)->startOfDay(), false);

            $batch = $row->batch_number ? "Batch {$row->batch_number} — " : '';
            $type = 'stock.expiring';

            if ($days < 0) {
                $title = 'Batch kedaluwarsa';
                $message = $batch."{$row->sku} {$row->item_name} kedaluwarsa ".abs($days)." hari lalu di {$row->warehouse_name}";
            } elseif ($days <= $criticalDays) {
                $title = 'Batch segera kedaluwarsa (≤'.((int) $criticalDays).' hari)';
                $message = $batch."{$row->sku} {$row->item_name} H-{$days} di {$row->warehouse_name}";
            } else {
                $title = 'Batch akan kedaluwarsa';
                $message = $batch."{$row->sku} {$row->item_name} H-{$days} di {$row->warehouse_name}";
            }

            if (! $force && $this->existsToday($type, 'stock_movement', (int) $row->id)) {
                continue;
            }

            $this->createForRoles($type, $title, $message, 'stock_movement', (int) $row->id);
            $created++;
        }

        return $created;
    }

    private function createForRoles(string $type, string $title, string $message, ?string $refType, ?int $refId): void
    {
        foreach (['supervisor', 'admin', 'warehouse_staff'] as $role) {
            try {
                NotificationService::notifyRole($role, $type, $title, $message, $refType, $refId);
            } catch (\Throwable $e) {
            }
        }
    }

    private function existsToday(string $type, string $refType, int $refId): bool
    {
        return DB::table('notifications')
            ->where('type', $type)
            ->where('reference_type', $refType)
            ->where('reference_id', $refId)
            ->whereDate('created_at', Carbon::today())
            ->exists();
    }
}
