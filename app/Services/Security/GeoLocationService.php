<?php

namespace App\Services\Security;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use MaxMind\Db\Reader;
use Throwable;

class GeoLocationService
{
    public static function forIp(string $ip): ?array
    {
        $ip = trim($ip);

        if ($ip === '' || static::isPrivateOrReserved($ip)) {
            return null;
        }

        if (! config('security.geoip.enabled', true) || ! static::enabledInSettings()) {
            return null;
        }

        $cacheKey = 'security_geoip_'.md5($ip);

        try {
            return Cache::remember($cacheKey, now()->addDays((int) config('security.geoip.cache_days', 7)), function () use ($ip): ?array {
                $local = static::fromLocalMmdb($ip);

                if ($local !== null) {
                    return $local;
                }

                return static::fromIpApi($ip);
            });
        } catch (Throwable) {
            return null;
        }
    }

    public static function fromIpApi(string $ip): ?array
    {
        $endpoint = rtrim((string) config('security.geoip.endpoint', 'http://ip-api.com/json'), '/');
        $timeout = (int) config('security.geoip.timeout', 2);

        try {
            $response = Http::timeout($timeout)->retry(0, 0)->get("{$endpoint}/{$ip}", [
                'fields' => 'countryCode,country,status',
            ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            if (! is_array($data) || ($data['status'] ?? null) !== 'success') {
                return null;
            }

            $code = trim((string) ($data['countryCode'] ?? ''));
            $name = trim((string) ($data['country'] ?? ''));

            if ($code === '' || $name === '') {
                return null;
            }

            return ['country_code' => strtoupper($code), 'country_name' => $name];
        } catch (Throwable) {
            return null;
        }
    }

    public static function fromLocalMmdb(string $ip): ?array
    {
        $paths = glob(storage_path('app/geoip/*.mmdb'));
        $files = $paths !== false ? $paths : [];

        if ($files === []) {
            return null;
        }

        if (! class_exists(Reader::class)) {
            return null;
        }

        foreach ($files as $file) {
            try {
                $reader = new Reader($file);
                $record = $reader->get($ip);

                if (! is_array($record)) {
                    continue;
                }

                $country = is_array($record['country'] ?? null) ? $record['country'] : null;

                if ($country === null) {
                    continue;
                }

                $names = is_array($country['names'] ?? null) ? $country['names'] : [];
                $code = trim((string) ($country['iso_code'] ?? ''));
                $name = trim((string) ($names['en'] ?? ''));

                if ($code === '' || $name === '') {
                    continue;
                }

                return ['country_code' => strtoupper($code), 'country_name' => $name];
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    public static function enabledInSettings(): bool
    {
        try {
            return (string) Setting::get('security.geoip_enabled', '1') === '1';
        } catch (Throwable) {
            return config('security.geoip.enabled', true);
        }
    }

    public static function isPrivateOrReserved(string $ip): bool
    {
        return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
