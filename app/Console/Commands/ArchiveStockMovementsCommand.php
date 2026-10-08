<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\StockMovementArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ArchiveStockMovementsCommand extends Command
{
    protected $signature = 'inventory:archive {--days= : Archive movements older than N days} {--dry-run : Only report how many would be archived} {--chunk=1000 : Rows per batch} {--force : Run even if disabled in settings}';

    protected $description = 'Move old stock movements into the archive table to keep the active ledger lean.';

    public function handle(): int
    {
        $enabled = (string) (Setting::get('inventory.archive_enabled', '0') ?? '0');
        $daysOption = $this->option('days');

        if ($enabled !== '1' && $daysOption === null && ! $this->option('force')) {
            $this->info('Archive disabled. Skipped.');

            return 0;
        }

        $days = (int) ($daysOption ?? Setting::get('inventory.archive_days', 365) ?? 365);
        $days = max(1, $days);
        $cutoff = Carbon::now()->subDays($days);
        $chunk = max(100, (int) $this->option('chunk'));

        $query = DB::table('stock_movements')->where('created_at', '<', $cutoff);
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info("No movements older than {$days} days.");

            return 0;
        }

        if ($this->option('dry-run')) {
            $this->info("Would archive {$total} movement(s) older than {$days} days.");

            return 0;
        }

        $moved = 0;

        (clone $query)->orderBy('id')->chunkById($chunk, function ($rows) use (&$moved): void {
            DB::transaction(function () use ($rows, &$moved): void {
                $now = now();
                $data = $rows->map(fn ($row) => array_merge((array) $row, ['archived_at' => $now]))->all();

                DB::table('stock_movement_archives')->insert($data);
                DB::table('stock_movements')->whereIn('id', $rows->pluck('id')->all())->delete();

                $moved += count($rows);
            });
        });

        $this->info("Archived {$moved} movement(s) older than {$days} days.");
        $this->line('Active: '.StockMovementArchive::activeCount().' · Archived: '.StockMovementArchive::archivedCount());

        return 0;
    }
}
