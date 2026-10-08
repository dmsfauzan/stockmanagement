<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Settings')]
class SettingIndex extends Component
{
    /**
     * Grouped schema of editable settings. Each field maps a safe alias to a
     * Setting key so the UI never uses dotted property paths.
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    protected array $schema = [
        'general' => [
            'app_name' => ['key' => 'app.name', 'label' => 'Application Name', 'type' => 'text'],
            'company_name' => ['key' => 'company.name', 'label' => 'Company Name', 'type' => 'text'],
            'currency' => ['key' => 'general.currency', 'label' => 'Currency', 'type' => 'text'],
        ],
        'inventory' => [
            'low_stock_notification' => ['key' => 'low_stock.notification', 'label' => 'Low Stock Notification', 'type' => 'select', 'options' => ['enabled' => 'Enabled', 'disabled' => 'Disabled']],
            'default_minimum_stock' => ['key' => 'inventory.default_minimum', 'label' => 'Default Minimum Stock', 'type' => 'number'],
            'default_maximum_stock' => ['key' => 'inventory.default_maximum', 'label' => 'Default Maximum Stock', 'type' => 'number'],
            'cycle_count_enabled' => ['key' => 'inventory.cycle_count_enabled', 'label' => 'Cycle Counting (mingguan)', 'type' => 'select', 'options' => ['1' => 'Enabled', '0' => 'Disabled']],
            'archive_enabled' => ['key' => 'inventory.archive_enabled', 'label' => 'Arsip Movements (scheduled)', 'type' => 'select', 'options' => ['1' => 'Enabled', '0' => 'Disabled']],
            'archive_days' => ['key' => 'inventory.archive_days', 'label' => 'Arsipkan movements lebih tua dari (hari)', 'type' => 'number'],
        ],
        'accounting' => [
            'account_inventory' => ['key' => 'account.inventory', 'label' => 'Akun Persediaan', 'type' => 'text'],
            'account_cogs' => ['key' => 'account.cogs', 'label' => 'Akun COGS / HPP', 'type' => 'text'],
            'account_adjustment_gain' => ['key' => 'account.adjustment_gain', 'label' => 'Akun Gain Adjustment', 'type' => 'text'],
            'account_adjustment_loss' => ['key' => 'account.adjustment_loss', 'label' => 'Akun Loss Adjustment', 'type' => 'text'],
            'account_transfer_clearing' => ['key' => 'account.transfer_clearing', 'label' => 'Akun Transfer Clearing', 'type' => 'text'],
            'account_goods_receipt_clearing' => ['key' => 'account.goods_receipt_clearing', 'label' => 'Akun GR Clearing', 'type' => 'text'],
        ],
        'notifications' => [
            'notifications_email_enabled' => ['key' => 'notifications.email_enabled', 'label' => 'Email Notifications (master switch)', 'type' => 'select', 'options' => ['1' => 'Enabled', '0' => 'Disabled']],
            'notifications_digest_enabled' => ['key' => 'notifications.digest_enabled', 'label' => 'Daily Digest (email ringkasan)', 'type' => 'select', 'options' => ['1' => 'Enabled', '0' => 'Disabled']],
            'notifications_digest_time' => ['key' => 'notifications.digest_time', 'label' => 'Jam kirim Digest (HH:MM)', 'type' => 'text'],
        ],
        'security' => [
            'security_require_2fa_admin' => ['key' => 'security.require_2fa_admin', 'label' => 'Wajibkan 2FA untuk Admin', 'type' => 'select', 'options' => ['1' => 'Ya', '0' => 'Tidak']],
        ],
        'reports' => [
            'reports_mail_enabled' => ['key' => 'reports.mail_enabled', 'label' => 'Report Email (scheduled)', 'type' => 'select', 'options' => ['1' => 'Enabled', '0' => 'Disabled']],
            'reports_mail_report' => ['key' => 'reports.mail_report', 'label' => 'Report', 'type' => 'select', 'options' => ['stock' => 'Stok Saat Ini', 'low' => 'Low Stock', 'movement' => 'Pergerakan', 'valuation' => 'Valuation', 'expiry' => 'Batch Expired']],
            'reports_mail_period' => ['key' => 'reports.mail_period', 'label' => 'Period', 'type' => 'select', 'options' => ['daily' => 'Harian', 'weekly' => 'Mingguan', 'monthly' => 'Bulanan']],
            'reports_mail_recipients' => ['key' => 'reports.mail_recipients', 'label' => 'Penerima (comma separated)', 'type' => 'text'],
            'reports_mail_limit' => ['key' => 'reports.mail_limit', 'label' => 'Batas baris per laporan', 'type' => 'number'],
        ],
        'integration' => [
            'integration_webhook_enabled' => ['key' => 'integration.webhook_enabled', 'label' => 'Webhook (enabled)', 'type' => 'select', 'options' => ['1' => 'Enabled', '0' => 'Disabled']],
            'integration_webhook_url' => ['key' => 'integration.webhook_url', 'label' => 'Webhook URL', 'type' => 'text'],
            'integration_webhook_events' => ['key' => 'integration.webhook_events', 'label' => 'Event (comma separated, kosong = semua)', 'type' => 'text'],
            'accounting_export_enabled' => ['key' => 'accounting.export_enabled', 'label' => 'Accounting Export (scheduled)', 'type' => 'select', 'options' => ['1' => 'Enabled', '0' => 'Disabled']],
            'accounting_export_period' => ['key' => 'accounting.export_period', 'label' => 'Export Period', 'type' => 'select', 'options' => ['daily' => 'Daily', 'monthly' => 'Monthly']],
            'accounting_export_format' => ['key' => 'accounting.export_format', 'label' => 'Format', 'type' => 'select', 'options' => ['csv' => 'CSV', 'xlsx' => 'Excel']],
            'accounting_export_recipient' => ['key' => 'accounting.export_recipient', 'label' => 'Penerima Email (opsional)', 'type' => 'text'],
            'accounting_export_disk' => ['key' => 'accounting.export_disk', 'label' => 'Disk Export', 'type' => 'select', 'options' => ['local' => 'Local', 'public' => 'Public']],
        ],
    ];

    /** @var array<string, string> */
    public array $values = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('settings.manage'), 403);

        $this->loadValues();
    }

    protected function loadValues(): void
    {
        $stored = Setting::pluck('value', 'key')->all();

        foreach ($this->schema as $group => $fields) {
            foreach ($fields as $alias => $field) {
                $this->values[$alias] = (string) ($stored[$field['key']] ?? $this->defaultFor($alias));
            }
        }
    }

    protected function defaultFor(string $alias): string
    {
        return match ($alias) {
            'app_name' => (string) config('app.name', 'Stock Management'),
            'currency' => 'IDR',
            'low_stock_notification' => 'enabled',
            'default_minimum_stock' => '0',
            'default_maximum_stock' => '0',
            'cycle_count_enabled' => '0',
            'archive_enabled' => '0',
            'archive_days' => '365',
            'account_inventory' => '1300',
            'account_cogs' => '5100',
            'account_adjustment_gain' => '4210',
            'account_adjustment_loss' => '5210',
            'account_transfer_clearing' => '1310',
            'account_goods_receipt_clearing' => '2000',
            'notifications_email_enabled' => '1',
            'notifications_digest_enabled' => '1',
            'notifications_digest_time' => '07:05',
            'security_require_2fa_admin' => '0',
            'integration_webhook_enabled' => '0',
            'integration_webhook_url' => '',
            'integration_webhook_secret' => '',
            'integration_webhook_events' => '',
            'accounting_export_enabled' => '0',
            'accounting_export_period' => 'daily',
            'accounting_export_format' => 'csv',
            'accounting_export_recipient' => '',
            'accounting_export_disk' => 'local',
            'reports_mail_enabled' => '0',
            'reports_mail_report' => 'stock',
            'reports_mail_period' => 'daily',
            'reports_mail_recipients' => '',
            'reports_mail_limit' => '50',
            default => '',
        };
    }

    public function save(): void
    {
        $this->authorize('manage', Setting::class);

        $rules = [
            'values.app_name' => ['required', 'string', 'max:255'],
            'values.company_name' => ['nullable', 'string', 'max:255'],
            'values.currency' => ['required', 'string', 'max:10'],
            'values.low_stock_notification' => ['required', 'in:enabled,disabled'],
            'values.default_minimum_stock' => ['required', 'integer', 'min:0'],
            'values.default_maximum_stock' => ['required', 'integer', 'min:0'],
            'values.cycle_count_enabled' => ['required', 'in:0,1'],
            'values.archive_enabled' => ['required', 'in:0,1'],
            'values.archive_days' => ['required', 'integer', 'min:30', 'max:3650'],
            'values.account_inventory' => ['nullable', 'string', 'max:20'],
            'values.account_cogs' => ['nullable', 'string', 'max:20'],
            'values.account_adjustment_gain' => ['nullable', 'string', 'max:20'],
            'values.account_adjustment_loss' => ['nullable', 'string', 'max:20'],
            'values.account_transfer_clearing' => ['nullable', 'string', 'max:20'],
            'values.account_goods_receipt_clearing' => ['nullable', 'string', 'max:20'],
            'values.notifications_email_enabled' => ['required', 'in:0,1'],
            'values.notifications_digest_enabled' => ['required', 'in:0,1'],
            'values.notifications_digest_time' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'values.security_require_2fa_admin' => ['required', 'in:0,1'],
            'values.integration_webhook_enabled' => ['required', 'in:0,1'],
            'values.integration_webhook_url' => ['nullable', 'string', 'max:500'],
            'values.integration_webhook_events' => ['nullable', 'string', 'max:1000'],
            'values.accounting_export_enabled' => ['required', 'in:0,1'],
            'values.accounting_export_period' => ['required', 'in:daily,monthly'],
            'values.accounting_export_format' => ['required', 'in:csv,xlsx'],
            'values.accounting_export_recipient' => ['nullable', 'email', 'max:255'],
            'values.accounting_export_disk' => ['required', 'in:local,public'],
            'values.reports_mail_enabled' => ['required', 'in:0,1'],
            'values.reports_mail_report' => ['required', 'in:stock,low,movement,valuation,expiry'],
            'values.reports_mail_period' => ['required', 'in:daily,weekly,monthly'],
            'values.reports_mail_recipients' => ['nullable', 'string', 'max:500'],
            'values.reports_mail_limit' => ['required', 'integer', 'min:5', 'max:500'],
        ];

        $validated = $this->validate($rules)['values'];

        $before = Setting::pluck('value', 'key')->all();

        foreach ($this->schema as $group => $fields) {
            foreach ($fields as $alias => $field) {
                Setting::set($field['key'], (string) ($validated[$alias] ?? ''), $group);
            }
        }

        AuditLogger::log('update', 'settings', null, ['before' => $before], $validated);

        $this->loadValues();
        $this->dispatch('toast', type: 'success', message: 'Pengaturan disimpan.');
    }

    public function render()
    {
        try {
            $dbName = DB::connection()->getDatabaseName();
        } catch (\Throwable) {
            $dbName = 'unknown';
        }

        return view('livewire.admin.setting-index', [
            'schema' => $this->schema,
            'systemInfo' => [
                'App Name' => config('app.name', 'Stock Management'),
                'App Version' => config('app.version', '1.0.0'),
                'Environment' => config('app.env', 'unknown'),
                'Laravel' => app()->version(),
                'PHP' => PHP_VERSION,
                'Database' => $dbName,
                'Driver' => config('database.default', 'unknown'),
            ],
        ]);
    }
}
