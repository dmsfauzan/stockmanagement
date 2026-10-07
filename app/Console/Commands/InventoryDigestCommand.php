<?php

namespace App\Console\Commands;

use App\Jobs\SendDailyDigestJob;
use App\Models\Setting;
use App\Models\User;
use App\Services\Support\NotificationPreferenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryDigestCommand extends Command
{
    protected $signature = 'inventory:digest {--force : Bypass digest preference check}';

    protected $description = 'Build and queue daily digest emails for inventory alerts.';

    public function handle(): int
    {
        if (! NotificationPreferenceService::digestGloballyEnabled() && ! $this->option('force')) {
            $this->info('Digest globally disabled. Skipped.');

            return 0;
        }

        $today = Carbon::today();
        $warnDays = (int) (Setting::get('expiry.warn_days', 30) ?? 30);
        $criticalDays = (int) (Setting::get('expiry.critical_days', 7) ?? 7);
        $dateLabel = $today->format('d M Y');

        $items = array_merge(
            $this->lowStockItems(),
            $this->expiringItems($today, $warnDays, $criticalDays),
        );

        if ($items === []) {
            $this->info('No digest items today.');

            return 0;
        }

        $recipients = $this->recipients();
        $queued = 0;

        foreach ($recipients as $user) {
            if (! $this->option('force') && ! NotificationPreferenceService::isDigestEnabled($user)) {
                continue;
            }

            dispatch(new SendDailyDigestJob((int) $user->id, $items, $dateLabel));
            $queued++;
        }

        $this->info("Digest queued for {$queued} user(s) with ".count($items).' item(s).');

        return 0;
    }

    /** @return array<int, User> */
    private function recipients(): array
    {
        $roles = config('notifications.digest_default_roles', ['admin', 'supervisor']);

        return User::whereHas('roles', fn ($query) => $query->whereIn('slug', $roles))
            ->where('status', 'active')
            ->get()
            ->all();
    }

    /** @return array<int, array{title:string, message:string, type:string}> */
    private function lowStockItems(): array
    {
        $rows = DB::table('stock_balances')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->whereNull('items.deleted_at')
            ->whereColumn('stock_balances.quantity_on_hand', '<=', 'items.minimum_stock')
            ->select([
                'items.sku', 'items.name as item_name', 'items.minimum_stock',
                'stock_balances.quantity_on_hand as on_hand',
                'warehouses.name as warehouse_name',
            ])
            ->limit(15)
            ->get();

        $items = [];

        foreach ($rows as $row) {
            $type = ((int) $row->on_hand <= 0) ? 'stock.out' : 'stock.low';
            $title = ($type === 'stock.out') ? 'Out of stock' : 'Low stock';
            $message = $row->sku.' '.$row->item_name.' '
                .(($type === 'stock.out') ? 'habis' : 'tinggal '.(int) $row->on_hand.' (min '.(int) $row->minimum_stock.')')
                .' di '.$row->warehouse_name;

            $items[] = ['title' => $title, 'message' => $message, 'type' => $type];
        }

        return $items;
    }

    /** @return array<int, array{title:string, message:string, type:string}> */
    private function expiringItems(Carbon $today, int $warnDays, int $criticalDays): array
    {
        $cutoff = $today->copy()->addDays($warnDays)->toDateString();

        $rows = DB::table('stock_movements')
            ->join('items', 'stock_movements.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->where('stock_movements.transaction_type', 'incoming')
            ->whereNotNull('stock_movements.expiry_date')
            ->whereDate('stock_movements.expiry_date', '<=', $cutoff)
            ->select([
                'stock_movements.batch_number', 'stock_movements.expiry_date',
                'items.sku', 'items.name as item_name',
                'warehouses.name as warehouse_name',
            ])
            ->orderBy('stock_movements.expiry_date')
            ->limit(15)
            ->get();

        $items = [];

        foreach ($rows as $row) {
            $days = (int) $today->copy()->startOfDay()->diffInDays(Carbon::parse($row->expiry_date)->startOfDay(), false);
            $batch = $row->batch_number ? "Batch {$row->batch_number} — " : '';
            $type = 'stock.expiring';

            if ($days < 0) {
                $title = 'Batch kedaluwarsa';
                $message = $batch."{$row->sku} {$row->item_name} kedaluwarsa ".abs($days).' hari lalu di '.$row->warehouse_name;
            } elseif ($days <= $criticalDays) {
                $title = 'Batch segera kedaluwarsa (≤'.((int) $criticalDays).' hari)';
                $message = $batch."{$row->sku} {$row->item_name} H-{$days} di {$row->warehouse_name}";
            } else {
                $title = 'Batch akan kedaluwarsa';
                $message = $batch."{$row->sku} {$row->item_name} H-{$days} di {$row->warehouse_name}";
            }

            $items[] = ['title' => $title, 'message' => $message, 'type' => $type];
        }

        return $items;
    }
}
