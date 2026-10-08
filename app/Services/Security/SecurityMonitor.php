<?php

namespace App\Services\Security;

use App\Models\BannedIp;
use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Services\Support\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SecurityMonitor
{
    public static function enabled(): bool
    {
        try {
            if ((string) Setting::get('security.monitor_enabled', config('security.enabled', true) ? '1' : '0') !== '1') {
                return false;
            }
        } catch (Throwable) {
            return (bool) config('security.enabled', true);
        }

        return true;
    }

    /**
     * Record a security event. Never throws; throttled by dedupe window.
     */
    public static function record(
        string $type,
        string $severity = 'warning',
        ?Request $request = null,
        array $meta = [],
        ?string $email = null,
        ?int $userId = null
    ): ?SecurityEvent {
        if (! static::enabled()) {
            return null;
        }

        try {
            $request = $request ?? request();
            $ip = static::ipFor($request);

            if ($ip === null) {
                return null;
            }

            $userId = $userId ?? ($request?->user()?->id ?? auth()->id());

            // Count every attempt for auto-ban before de-duplicating DB writes.
            static::maybeAutoBan($ip, $type, $userId);

            $dedupeSeconds = max(1, (int) config('security.dedupe_seconds', 5));

            if (! Cache::add('security_event_dedupe:'.md5($ip.'|'.$type), true, $dedupeSeconds)) {
                return null;
            }

            return SecurityEvent::create([
                'event_type' => $type,
                'severity' => $severity,
                'ip_address' => $ip,
                'user_id' => $userId,
                'email' => $email ?? (string) ($request?->input('email') ?? ''),
                'method' => $request?->method(),
                'path' => $request ? '/'.ltrim($request->path(), '/') : null,
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 65535) ?: null : null,
                'referer' => $request ? mb_substr((string) $request->headers->get('referer'), 0, 500) ?: null : null,
                'meta' => $meta === [] ? null : $meta,
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    public static function ipFor(?Request $request): ?string
    {
        try {
            $ip = $request?->ip();

            return is_string($ip) && $ip !== '' ? $ip : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function isBanned(string $ip): ?BannedIp
    {
        try {
            $banned = Cache::remember('security_banned_ips', 300, fn (): array => BannedIp::active()->pluck('id', 'ip_address')->all());

            $id = $banned[$ip] ?? null;

            if ($id === null) {
                return null;
            }

            $row = BannedIp::find($id);

            if ($row === null || $row->isExpired()) {
                static::forgetBanCache();

                return null;
            }

            return $row;
        } catch (Throwable) {
            return null;
        }
    }

    public static function forgetBanCache(): void
    {
        try {
            Cache::forget('security_banned_ips');
        } catch (Throwable) {
        }
    }

    public static function ban(string $ip, string $reason, string $source = 'manual', ?int $durationMinutes = null, ?int $bannedBy = null): BannedIp
    {
        $record = BannedIp::updateOrCreate(
            ['ip_address' => $ip],
            [
                'reason' => $reason,
                'source' => $source,
                'banned_by' => $bannedBy,
                'expires_at' => $durationMinutes !== null ? now()->addMinutes($durationMinutes) : null,
            ]
        );

        static::forgetBanCache();

        return $record;
    }

    public static function unban(string $ip): bool
    {
        $deleted = BannedIp::where('ip_address', $ip)->delete() > 0;

        if ($deleted) {
            static::forgetBanCache();
        }

        return $deleted;
    }

    public static function maybeAutoBan(string $ip, string $type, ?int $userId = null): void
    {
        if ($userId !== null || $type === 'banned_blocked') {
            return;
        }

        if (static::isAllowlisted($ip)) {
            return;
        }

        $enabled = (string) (Setting::get('security.autoban_enabled', config('security.autoban.enabled') ? '1' : '0') ?? '0');

        if ($enabled !== '1') {
            return;
        }

        $threshold = max(2, (int) (Setting::get('security.autoban_threshold', config('security.autoban.threshold')) ?? 10));
        $window = max(1, (int) (Setting::get('security.autoban_window', config('security.autoban.window_minutes')) ?? 5));
        $duration = max(1, (int) (Setting::get('security.autoban_duration', config('security.autoban.duration_minutes')) ?? 60));

        $key = 'security_autoban_hits:'.md5($ip);
        $hits = (int) (Cache::get($key, 0)) + 1;
        Cache::put($key, $hits, now()->addMinutes($window));

        if ($hits !== $threshold) {
            return;
        }

        $record = static::ban(
            $ip,
            "Auto-ban: {$threshold} peristiwa keamanan ({$type}) dalam {$window} menit",
            'auto',
            $duration,
        );

        $record->increment('hits');

        try {
            NotificationService::notifyRole(
                'admin',
                'security.autoban',
                'Auto-ban: '.$ip,
                $ip.' diblokir otomatis ('.$record->reason.'). Kedaluwarsa '.$duration.' menit.'
            );
        } catch (Throwable) {
        }
    }

    public static function isSuspiciousPath(string $path): bool
    {
        $path = strtolower(trim($path));

        if ($path === '') {
            return false;
        }

        $patterns = (array) config('security.scanner_patterns', []);

        if ($patterns === []) {
            return false;
        }

        return (bool) preg_match('#('.implode('|', $patterns).')#i', $path);
    }

    public static function isAllowlisted(string $ip): bool
    {
        $defaults = (array) config('security.autoban.allowlist', []);

        try {
            $configured = trim((string) (Setting::get('security.ban_allowlist', '') ?? ''));
            $extras = $configured !== '' ? array_map('trim', explode(',', $configured)) : [];
        } catch (Throwable) {
            $extras = [];
        }

        return in_array($ip, array_merge($defaults, $extras), true);
    }
}
