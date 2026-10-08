<?php

namespace App\Console\Commands;

use App\Models\IdempotencyKey;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PurgeIdempotencyKeysCommand extends Command
{
    protected $signature = 'idempotency:purge {--hours= : Override TTL in hours} {--dry-run : Only report}';

    protected $description = 'Delete expired idempotency keys.';

    public function handle(): int
    {
        $hours = $this->option('hours') !== null
            ? max(1, (int) $this->option('hours'))
            : max(1, (int) config('idempotency.ttl_hours', 24));

        $cutoff = Carbon::now()->subHours($hours);
        $count = IdempotencyKey::where('created_at', '<', $cutoff)->count();

        if ($this->option('dry-run')) {
            $this->info("Would remove {$count} key(s) older than {$hours} hour(s).");

            return 0;
        }

        $deleted = IdempotencyKey::where('created_at', '<', $cutoff)->delete();

        $this->info("Removed {$deleted} expired key(s).");

        return 0;
    }
}
