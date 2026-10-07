<?php

namespace App\Services\Support;

use App\Models\NotificationPreference;
use App\Models\Setting;
use App\Models\User;

class NotificationPreferenceService
{
    public static function types(): array
    {
        return config('notifications.types', []);
    }

    public static function digestType(): string
    {
        return (string) config('notifications.digest_type', 'digest');
    }

    public static function emailGloballyEnabled(): bool
    {
        return (bool) (int) (Setting::get('notifications.email_enabled', '1') ?? '1');
    }

    public static function digestGloballyEnabled(): bool
    {
        return (bool) (int) (Setting::get('notifications.digest_enabled', '1') ?? '1');
    }

    public static function defaultFor(string $type): bool
    {
        $types = config('notifications.types', []);

        return (bool) ($types[$type]['email_default'] ?? false);
    }

    public static function isEmailEnabled(User $user, string $type): bool
    {
        if (! static::emailGloballyEnabled()) {
            return false;
        }

        if (! array_key_exists($type, static::types())) {
            return false;
        }

        $override = NotificationPreference::where('user_id', $user->id)
            ->where('type', $type)
            ->value('email_enabled');

        return $override === null ? static::defaultFor($type) : (bool) $override;
    }

    public static function isDigestEnabled(User $user): bool
    {
        if (! static::digestGloballyEnabled()) {
            return false;
        }

        $override = NotificationPreference::where('user_id', $user->id)
            ->where('type', static::digestType())
            ->value('email_enabled');

        if ($override === null) {
            $roles = config('notifications.digest_default_roles', []);

            return (bool) array_intersect($roles, $user->roleSlugs());
        }

        return (bool) $override;
    }

    public static function setEmailEnabled(User $user, string $type, bool $enabled): void
    {
        NotificationPreference::updateOrCreate(
            ['user_id' => $user->id, 'type' => $type],
            ['email_enabled' => $enabled],
        );
    }
}
