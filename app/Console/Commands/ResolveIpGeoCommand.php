<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Security\GeoLocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ResolveIpGeoCommand extends Command
{
    protected $signature = 'security:resolve-geo {--limit=40 : Max distinct IPs per run}';

    protected $description = 'Resolve country info for security event IPs missing geo data.';

    public function handle(): int
    {
        if ((string) (Setting::get('security.geoip_enabled', '1') ?? '1') !== '1') {
            $this->info('Geo-IP disabled. Skipped.');

            return 0;
        }

        $limit = max(1, min(200, (int) $this->option('limit')));
        $ips = DB::table('security_events')
            ->select('ip_address')
            ->whereNull('country_code')
            ->groupBy('ip_address')
            ->orderByDesc(DB::raw('MAX(created_at)'))
            ->limit($limit)
            ->pluck('ip_address')
            ->all();

        $resolved = 0;

        foreach ($ips as $ip) {
            try {
                $geo = GeoLocationService::forIp((string) $ip);

                if ($geo === null) {
                    continue;
                }

                DB::table('security_events')->where('ip_address', $ip)->whereNull('country_code')->update([
                    'country_code' => $geo['country_code'],
                    'country_name' => $geo['country_name'],
                ]);

                $resolved++;

                // Stay well under ip-api free limits (~45/min).
                usleep(150000);
            } catch (Throwable) {
                continue;
            }
        }

        $this->info("Resolved geo for {$resolved} IP(s).");

        return 0;
    }
}
