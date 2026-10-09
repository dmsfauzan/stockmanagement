<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\StockBalance;
use App\Models\StockReservation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReleaseStaleReservationsCommand extends Command
{
    protected $signature = 'inventory:release-stale-reservations
        {--days= : Release reservations older than N days}
        {--dry-run : Only report how many would be released}
        {--force : Run even if disabled in settings}';

    protected $description = 'Release stock reservations that have been active longer than the TTL (abandoned drafts).';

    public function handle(): int
    {
        $enabled = (string) (Setting::get('inventory.reservation_auto_release', '1') ?? '1');

        if ($enabled !== '1' && ! $this->option('force') && $this->option('days') === null) {
            $this->info('Auto-release disabled. Skipped.');

            return 0;
        }

        $days = max(1, (int) ($this->option('days') ?? Setting::get('inventory.reservation_ttl_days', 7) ?? 7));
        $cutoff = Carbon::now()->subDays($days);

        $query = StockReservation::where('status', 'active')->where('created_at', '<', $cutoff);
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info("No stale reservations older than {$days} days.");

            return 0;
        }

        if ($this->option('dry-run')) {
            $this->info("Would release {$total} reservation(s) older than {$days} days.");

            return 0;
        }

        $released = 0;

        $query->chunkById(500, function ($reservations) use (&$released): void {
            DB::transaction(function () use ($reservations, &$released): void {
                foreach ($reservations as $reservation) {
                    $balance = StockBalance::where('item_id', $reservation->item_id)
                        ->where('warehouse_id', $reservation->warehouse_id)
                        ->where('location_id', $reservation->location_id)
                        ->lockForUpdate()
                        ->first();

                    if ($balance) {
                        $balance->decrement('quantity_reserved', min((int) $balance->quantity_reserved, (int) $reservation->quantity));
                        $balance->touch();
                    }

                    $reservation->update([
                        'status' => 'expired',
                        'released_at' => now(),
                    ]);

                    $released++;
                }
            });
        });

        $this->info("Released {$released} stale reservation(s) older than {$days} days.");

        return 0;
    }
}
