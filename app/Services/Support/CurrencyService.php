<?php

namespace App\Services\Support;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;

/**
 * Currency conversion. Every rate is expressed as "1 unit of the foreign
 * currency = X base". The base currency always converts at 1.0.
 */
class CurrencyService
{
    public static function baseCode(): string
    {
        return Currency::where('is_base', true)->value('code') ?? 'IDR';
    }

    public static function rate(string $code, ?string $date = null): float
    {
        if ($code === static::baseCode()) {
            return 1.0;
        }

        $currency = Currency::where('code', $code)->first();

        if (! $currency) {
            return 1.0;
        }

        $target = $date ? Carbon::parse($date)->toDateString() : Carbon::today()->toDateString();

        $rate = ExchangeRate::where('currency_id', $currency->id)
            ->whereDate('effective_date', '<=', $target)
            ->orderByDesc('effective_date')
            ->value('rate');

        return (float) ($rate ?? 1.0);
    }

    public static function convert(float $amount, string $from, string $to, ?string $date = null): float
    {
        $fromRate = static::rate($from, $date);
        $toRate = static::rate($to, $date);

        if ($fromRate <= 0) {
            return 0.0;
        }

        return round($amount * ($fromRate / max(0.00000001, $toRate)), 2);
    }

    public static function toBase(float $amount, string $from, ?string $date = null): float
    {
        return static::convert($amount, $from, static::baseCode(), $date);
    }
}
