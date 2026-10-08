<?php

use Illuminate\Support\Carbon;

if (! function_exists('to_display_tz')) {
    /**
     * Convert a stored (UTC) datetime to the configured display timezone.
     * Storage stays UTC; only presentation is converted.
     */
    function to_display_tz(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $carbon = $value instanceof DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }

        return $carbon->timezone((string) config('app.display_timezone', 'Asia/Jakarta'));
    }
}
