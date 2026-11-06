<?php

namespace App\Services\Integration;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\Support\AuditLogger;
use App\Services\Support\CurrencyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mengambil kurs valas dari penyedia eksternal dan menyimpannya ke tabel
 * `exchange_rates` (rate = 1 unit valas = X mata uang dasar).
 *
 * Default provider: https://open.er-api.com (gratis, tanpa API key, update
 * harian). Provider mengembalikan `1 BASE = X valas`, sehingga nilainya
 * di-invers agar sesuai skema aplikasi.
 */
class ExchangeRateService
{
    /**
     * @return array{ok:bool, updated:int, skipped:int, source:?string, date:string, message:string}
     */
    public function sync(?Carbon $date = null): array
    {
        $date ??= today();
        $base = strtoupper(CurrencyService::baseCode());
        $endpoint = rtrim((string) config('services.fx.url'), '/').'/'.$base;
        $source = parse_url($endpoint, PHP_URL_HOST) ?: 'fx-provider';

        try {
            $verify = config('services.fx.verify', true);
            $caBundle = config('services.fx.ca_bundle');

            if (is_string($caBundle) && $caBundle !== '' && is_file($caBundle)) {
                $verify = $caBundle;
            }

            $request = Http::acceptJson()
                ->withOptions(['verify' => $verify])
                ->timeout((int) config('services.fx.timeout', 10))
                ->retry(2, 300);

            $key = trim((string) config('services.fx.key', ''));

            if ($key !== '') {
                $request = $request->withQueryParameters([
                    (string) config('services.fx.key_param', 'access_key') => $key,
                ]);
            }

            $response = $request->get($endpoint);
        } catch (\Throwable $e) {
            Log::warning('FX sync request failed', ['error' => $e->getMessage()]);

            return $this->result(false, 0, 0, $source, $date, 'Tidak dapat menghubungi penyedia kurs.');
        }

        if (! $response->successful()) {
            Log::warning('FX sync bad status', ['status' => $response->status()]);

            return $this->result(false, 0, 0, $source, $date, 'Penyedia kurs membalas HTTP '.$response->status().'.');
        }

        $data = $response->json();

        // open.er-api.com memakai `result: success`; provider lain mungkin
        // langsung mengirim `rates`.
        if (($data['result'] ?? 'success') !== 'success' || ! is_array($data['rates'] ?? null)) {
            Log::warning('FX sync invalid payload', ['provider' => $source]);

            return $this->result(false, 0, 0, $source, $date, 'Format respons penyedia kurs tidak dikenali.');
        }

        /** @var array<string, mixed> $rates */
        $rates = $data['rates'];
        $currencies = Currency::query()
            ->where('is_active', true)
            ->where('is_base', false)
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($currencies as $currency) {
            $code = strtoupper((string) $currency->code);
            $perBase = $rates[$code] ?? null;

            if (! is_numeric($perBase) || (float) $perBase <= 0) {
                $skipped++;

                continue;
            }

            ExchangeRate::updateOrCreate(
                [
                    'currency_id' => $currency->id,
                    'effective_date' => $date->toDateString(),
                ],
                [
                    'rate' => 1 / (float) $perBase,
                    'source' => $source,
                    'fetched_at' => now(),
                ],
            );

            $updated++;
        }

        AuditLogger::log('SYNC', 'currency', null, null, [
            'base' => $base,
            'date' => $date->toDateString(),
            'source' => $source,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);

        return $this->result(
            true,
            $updated,
            $skipped,
            $source,
            $date,
            "{$updated} kurs diperbarui, {$skipped} dilewati.",
        );
    }

    /**
     * @return array{ok:bool, updated:int, skipped:int, source:?string, date:string, message:string}
     */
    protected function result(bool $ok, int $updated, int $skipped, ?string $source, Carbon $date, string $message): array
    {
        return [
            'ok' => $ok,
            'updated' => $updated,
            'skipped' => $skipped,
            'source' => $source,
            'date' => $date->toDateString(),
            'message' => $message,
        ];
    }
}
