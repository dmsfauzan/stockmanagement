<?php

namespace App\Console\Commands;

use App\Services\Integration\ExchangeRateService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncCurrencyRatesCommand extends Command
{
    protected $signature = 'currency:sync-rates {--date= : Tanggal berlaku (Y-m-d, default hari ini)}';

    protected $description = 'Sinkronkan kurs mata uang dari penyedia eksternal';

    public function handle(ExchangeRateService $service): int
    {
        $date = $this->option('date');

        try {
            $target = $date ? Carbon::parse((string) $date) : today();
        } catch (\Throwable) {
            $this->error('Format --date tidak valid. Gunakan Y-m-d.');

            return self::INVALID;
        }

        $result = $service->sync($target);

        if (! $result['ok']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        $this->info($result['message']);
        $this->line('Sumber: '.($result['source'] ?? '-').' · tanggal: '.$result['date']);

        return self::SUCCESS;
    }
}
