<?php

namespace App\Console\Commands;

use App\Enums\OpnameStatus;
use App\Models\Setting;
use App\Models\StockOpname;
use App\Models\Zone;
use App\Services\Support\DocumentNumberService;
use App\Services\Support\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateCycleCountsCommand extends Command
{
    protected $signature = 'inventory:cycle-count {--force : Run even if disabled in settings} {--limit=5 : Max zones to schedule per run}';

    protected $description = 'Generate draft cycle-count stock opnames per zone.';

    public function handle(): int
    {
        $enabled = (string) (Setting::get('inventory.cycle_count_enabled', '0') ?? '0');

        if ($enabled !== '1' && ! $this->option('force')) {
            $this->info('Cycle counting disabled. Skipped.');

            return 0;
        }

        $limit = max(1, (int) $this->option('limit'));

        $zones = Zone::query()
            ->with('warehouse')
            ->orderBy('warehouse_id')
            ->orderBy('code')
            ->get()
            ->filter(function (Zone $zone): bool {
                // Skip if there is already an open cycle opname for this zone.
                return ! StockOpname::where('type', 'cycle')
                    ->where('zone_id', $zone->id)
                    ->whereIn('status', [OpnameStatus::Draft->value, OpnameStatus::Counting->value, OpnameStatus::Submitted->value])
                    ->exists();
            })
            ->take($limit);

        if ($zones->isEmpty()) {
            $this->info('No zones to schedule.');

            return 0;
        }

        $created = 0;

        foreach ($zones as $zone) {
            DB::transaction(function () use ($zone): void {
                StockOpname::create([
                    'number' => DocumentNumberService::generate('OPN'),
                    'opname_date' => now()->toDateString(),
                    'scheduled_date' => now()->toDateString(),
                    'warehouse_id' => $zone->warehouse_id,
                    'location_id' => null,
                    'type' => 'cycle',
                    'zone_id' => $zone->id,
                    'rack_id' => null,
                    'status' => OpnameStatus::Draft->value,
                    'notes' => 'Auto cycle count — '.($zone->name ?? $zone->code),
                    'created_by' => null,
                ]);
            });

            $created++;
        }

        try {
            NotificationService::notifyApprovers(
                'approval.request',
                'Cycle Count Dibuat',
                $created.' cycle count draft dibuat untuk dijadwalkan.',
            );
        } catch (\Throwable $e) {
        }

        $this->info("Cycle-count opnames created: {$created}.");

        return 0;
    }
}
