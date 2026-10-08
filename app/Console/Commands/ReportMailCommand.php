<?php

namespace App\Console\Commands;

use App\Mail\ReportMail;
use App\Models\Setting;
use App\Models\User;
use App\Services\Reports\ReportMailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ReportMailCommand extends Command
{
    protected $signature = 'reports:mail {--report= : stock|low|movement|valuation|expiry} {--period=daily : daily|weekly|monthly} {--to= : Override recipient(s), comma separated} {--force : Send even if disabled}';

    protected $description = 'Email a scheduled inventory report to configured recipients.';

    public function handle(): int
    {
        $period = (string) ($this->option('period') ?: 'daily');
        $report = (string) ($this->option('report') ?: (Setting::get('reports.mail_report', 'stock') ?? 'stock'));
        $toOverride = $this->option('to');

        if (! in_array($report, ReportMailService::REPORTS, true)) {
            $this->error("Unknown report [{$report}]. Allowed: ".implode(', ', ReportMailService::REPORTS));

            return 1;
        }

        if (! in_array($period, ReportMailService::PERIODS, true)) {
            $this->error("Unknown period [{$period}]. Allowed: ".implode(', ', ReportMailService::PERIODS));

            return 1;
        }

        $enabled = (string) (Setting::get('reports.mail_enabled', '0') ?? '0');
        $configuredPeriod = (string) (Setting::get('reports.mail_period', 'daily') ?? 'daily');

        if ($toOverride === null && ! $this->option('force')) {
            if ($enabled !== '1') {
                $this->info('Scheduled report email disabled. Skipped.');

                return 0;
            }

            if ($configuredPeriod !== $period) {
                $this->info("Period mismatch (configured: {$configuredPeriod}). Skipped.");

                return 0;
            }
        }

        [$from, $to] = ReportMailService::range($period);
        $payload = ReportMailService::build($report, $from, $to);

        if ($payload['total'] === 0 && ! $this->option('force')) {
            $this->info('No data for the selected period. Skipped.');

            return 0;
        }

        $recipients = $this->recipients($toOverride);

        if ($recipients === []) {
            $this->warn('No recipients configured.');

            return 0;
        }

        $periodLabel = match ($period) {
            'weekly' => 'Mingguan',
            'monthly' => 'Bulanan',
            default => 'Harian',
        };

        $mail = new ReportMail($report, $periodLabel, $from->format('d M Y'), $to->format('d M Y'), $payload);

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->send($mail);
        }

        $this->info("Report [{$report}] sent to ".count($recipients).' recipient(s).');

        return 0;
    }

    /** @return array<int, string> */
    private function recipients(?string $override): array
    {
        if ($override !== null && trim($override) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $override))));
        }

        $configured = trim((string) (Setting::get('reports.mail_recipients', '') ?? ''));

        if ($configured !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $configured))));
        }

        $roles = config('notifications.digest_default_roles', ['admin', 'supervisor']);

        return User::whereHas('roles', fn ($query) => $query->whereIn('slug', $roles))
            ->where('status', 'active')
            ->pluck('email')
            ->all();
    }
}
