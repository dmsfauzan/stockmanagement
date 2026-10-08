<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PurgeSecurityEventsCommand extends Command
{
    protected $signature = 'security:purge {--days= : Purge events older than N days} {--dry-run : Only report}';

    protected $description = 'Delete old security_events and expired banned IPs.';

    public function handle(): int
    {
        $daysOption = $this->option('days');
        $days = $daysOption !== null ? max(1, (int) $daysOption) : max(1, (int) (Setting::get('security.retention_days', config('security.retention_days', 90)) ?? 90));
        $cutoff = Carbon::now()->subDays($days);

        $eventsCount = DB::table('security_events')->where('created_at', '<', $cutoff)->count();
        $bannedCount = DB::table('banned_ips')->whereNotNull('expires_at')->where('expires_at', '<', now())->count();

        if ($this->option('dry-run')) {
            $this->info("Would remove {$eventsCount} security event(s) and {$bannedCount} expired ban(s) older than {$days} days.");

            return 0;
        }

        $removedEvents = DB::table('security_events')->where('created_at', '<', $cutoff)->delete();
        $removedBans = DB::table('banned_ips')->whereNotNull('expires_at')->where('expires_at', '<', now())->delete();

        $this->info("Purged {$removedEvents} event(s) and {$removedBans} expired ban(s).");

        return 0;
    }
}
