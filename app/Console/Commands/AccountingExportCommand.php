<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Accounting\AccountingExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class AccountingExportCommand extends Command
{
    protected $signature = 'accounting:export {--from= : From date Y-m-d} {--to= : To date Y-m-d} {--period=daily : Period for default range}';

    protected $description = 'Export accounting journal for the configured period.';

    public function handle(): int
    {
        $enabled = (string) (Setting::get('accounting.export_enabled', '0') ?? '0');
        $fromOption = $this->option('from');
        $toOption = $this->option('to');

        if ($enabled !== '1' && $fromOption === null && $toOption === null) {
            $this->info('Accounting export disabled. Skipped.');

            return 0;
        }

        if ($fromOption !== null || $toOption !== null) {
            try {
                $from = $fromOption !== null ? Carbon::parse($fromOption)->startOfDay() : Carbon::today()->subMonth()->startOfDay();
                $to = $toOption !== null ? Carbon::parse($toOption)->endOfDay() : Carbon::today()->endOfDay();
            } catch (\Throwable $e) {
                $this->error('Invalid date format. Use Y-m-d.');

                return 1;
            }
        } else {
            $period = $this->option('period') ?? 'daily';

            if ($period === 'monthly') {
                $from = Carbon::now()->subMonth()->startOfMonth()->startOfDay();
                $to = Carbon::now()->subMonth()->endOfMonth()->endOfDay();
            } else {
                $from = Carbon::yesterday()->startOfDay();
                $to = Carbon::yesterday()->endOfDay();
            }
        }

        $format = (string) (Setting::get('accounting.export_format', 'csv') ?? 'csv');
        $disk = (string) (Setting::get('accounting.export_disk', 'local') ?? 'local');

        $path = AccountingExportService::generate($from, $to, $format === 'xlsx' ? 'xlsx' : 'csv', $disk);

        $this->info("Journal exported to {$path}.");

        $recipient = trim((string) (Setting::get('accounting.export_recipient', '') ?? ''));

        if ($recipient !== '') {
            try {
                Mail::raw('Accounting journal attached for '.$from->toDateString().'–'.$to->toDateString(), function ($message) use ($recipient, $path, $disk): void {
                    $message->to($recipient)->subject('Accounting journal '.$from->format('Y-m-d').' – '.$to->format('Y-m-d'));

                    try {
                        $message->attachData(
                            Storage::disk($disk)->get($path),
                            basename($path),
                        );
                    } catch (\Throwable $e) {
                    }
                });

                $this->info('Emailed to '.$recipient.'.');
            } catch (\Throwable $e) {
            }
        }

        return 0;
    }
}
