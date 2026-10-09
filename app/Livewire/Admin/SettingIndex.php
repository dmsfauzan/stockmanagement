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
            'restrict_units' => ['key' => 'inventory.restrict_units', 'label' => 'Batasi Satuan ke Konversi Terdaftar', 'type' => 'select', 'options' => ['1' => 'Ya', '0' => 'Tidak (tampilkan semua)']],
            'slow_days' => ['key' => 'inventory.slow_days', 'label' => 'Slow-moving (hari idle tanpa keluar)', 'type' => 'number'],
            'forecast_service_level' => ['key' => 'forecast.service_level', 'label' => 'Prakiraan: service level (0.90–0.99)', 'type' => 'text'],
            'reservation_auto_release' => ['key' => 'inventory.reservation_auto_release', 'label' => 'Auto-release reservasi (stale)', 'type' => 'select', 'options' => ['1' => 'Aktif', '0' => 'Nonaktif']],
            'reservation_ttl_days' => ['key' => 'inventory.reservation_ttl_days', 'label' => 'TTL reservasi (hari)', 'type' => 'number'],
            'label_default_size' => ['key' => 'label.default_size', 'label' => 'Label: ukuran default', 'type' => 'select', 'options' => ['50x30' => '50x30', '70x40' => '70x40', '85x54' => '85x54', '100x50' => '100x50']],
            'label_show_brand' => ['key' => 'label.show_brand', 'label' => 'Label: tampilkan brand', 'type' => 'select', 'options' => ['1' => 'Ya', '0' => 'Tidak']],
            'label_show_price' => ['key' => 'label.show_price', 'label' => 'Label: tampilkan harga', 'type' => 'select', 'options' => ['1' => 'Ya', '0' => 'Tidak']],
            'label_company_text' => ['key' => 'label.company_text', 'label' => 'Label: teks perusahaan', 'type' => 'text'],
            'forecast_horizon_days' => ['key' => 'forecast.horizon_days', 'label' => 'Prakiraan: horizon (hari)', 'type' => 'number'],
            'forecast_lead_time_days' => ['key' => 'forecast.lead_time_days', 'label' => 'Prakiraan: lead time (hari)', 'type' => 'number'],
            'dead_days' => ['key' => 'inventory.dead_days', 'label' => 'Dead stock (hari idle)', 'type' => 'number'],
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
            'account_sales_return' => ['key' => 'account.sales_return', 'label' => 'Akun Retur Penjualan', 'type' => 'text'],
            'account_purchase_return' => ['key' => 'account.purchase_return', 'label' => 'Akun Retur Pembelian', 'type' => 'text'],
        ],
        'notifications' => [
            'notifications_email_enabled' => ['key' => 'notifications.email_enabled', 'label' => 'Email Notifications (master switch)', 'type' => 'select', 'options' => ['1' => 'Enabled', '0' => 'Disabled']],
            'notifications_digest_enabled' => ['key' => 'notifications.digest_enabled', 'label' => 'Daily Digest (email ringkasan)', 'type' => 'select', 'options' => ['1' => 'Enabled', '0' => 'Disabled']],
            'notifications_digest_time' => ['key' => 'notifications.digest_time', 'label' => 'Jam kirim Digest (HH:MM)', 'type' => 'text'],
        ],
        'security' => [
            'security_require_2fa_admin' => ['key' => 'security.require_2fa_admin', 'label' => 'Wajibkan 2FA untuk Admin', 'type' => 'select', 'options' => ['1' => 'Ya', '0' => 'Tidak']],
            'security_monitor_enabled' => ['key' => 'security.monitor_enabled', 'label' => 'Security Monitoring', 'type' => 'select', 'options' => ['1' => 'Enabled', '0' => 'Disabled']],
            'security_autoban_enabled' => ['key' => 'security.autoban_enabled', 'label' => 'Auto-ban IP', 'type' => 'select', 'options' => ['1' => 'Enabled', '0' => 'Disabled']],
            'security_autoban_threshold' => ['key' => 'security.autoban_threshold', 'label' => 'Auto-ban: jumlah event', 'type' => 'number'],
            'security_autoban_window' => ['key' => 'security.autoban_window', 'label' => 'Auto-ban: jendela (menit)', 'type' => 'number'],
            'security_autoban_duration' => ['key' => 'security.autoban_duration', 'label' => 'Auto-ban: durasi (menit)', 'type' => 'number'],
            'security_ban_allowlist' => ['key' => 'security.ban_allowlist', 'label' => 'Allowlist IP (comma separate, anti auto-ban)', 'type' => 'text'],
            'security_retention_days' => ['key' => 'security.retention_days', 'label' => 'Retensi event (hari)', 'type' => 'number'],
            'security_geoip_enabled' => ['key' => 'security.geoip_enabled', 'label' => 'Deteksi negara (Geo-IP)', 'type' => 'select', 'options' => ['1' => 'Enabled', '0' => 'Disabled']],
        ],
        'approval' => [
            'approval_enabled' => ['key' => 'approval.enabled', 'label' => 'Approval Multi-Level', 'type' => 'select', 'options' => ['1' => 'Enabled', '0' => 'Disabled']],
            'approval_maker_checker' => ['key' => 'approval.maker_checker', 'label' => 'Maker-Checker (ketat)', 'type' => 'select', 'options' => ['1' => 'Enabled', '0' => 'Disabled']],
            'approval_flows' => ['key' => 'approval.flows', 'label' => 'Flows JSON (tipe → levels, override config)', 'type' => 'text'],
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
            'restrict_units' => '1',
            'slow_days' => '60',
            'dead_days' => '180',
            'forecast_service_level' => '0.95',
            'reservation_auto_release' => '1',
            'reservation_ttl_days' => '7',
            'label_default_size' => '85x54',
            'label_show_brand' => '1',
            'label_show_price' => '0',
            'label_company_text' => '',
            'forecast_horizon_days' => '30',
            'forecast_lead_time_days' => '7',
            'archive_enabled' => '0',
            'archive_days' => '365',
            'account_inventory' => '1300',
            'account_cogs' => '5100',
            'account_adjustment_gain' => '4210',
            'account_adjustment_loss' => '5210',
            'account_transfer_clearing' => '1310',
            'account_goods_receipt_clearing' => '2000',
            'account_sales_return' => '4200',
            'account_purchase_return' => '5300',
            'notifications_email_enabled' => '1',
            'notifications_digest_enabled' => '1',
            'notifications_digest_time' => '07:05',
            'security_require_2fa_admin' => '0',
            'security_monitor_enabled' => '1',
            'security_autoban_enabled' => '1',
            'security_autoban_threshold' => '10',
            'security_autoban_window' => '5',
            'security_autoban_duration' => '60',
            'security_ban_allowlist' => '',
            'security_retention_days' => '90',
            'security_geoip_enabled' => '1',
            'approval_enabled' => '0',
            'approval_maker_checker' => '1',
            'approval_flows' => '',
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
            'values.account_sales_return' => ['nullable', 'string', 'max:20'],
            'values.account_purchase_return' => ['nullable', 'string', 'max:20'],
            'values.notifications_email_enabled' => ['required', 'in:0,1'],
            'values.notifications_digest_enabled' => ['required', 'in:0,1'],
            'values.notifications_digest_time' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'values.security_require_2fa_admin' => ['required', 'in:0,1'],
            'values.security_monitor_enabled' => ['required', 'in:0,1'],
            'values.security_autoban_enabled' => ['required', 'in:0,1'],
            'values.security_autoban_threshold' => ['required', 'integer', 'min:2', 'max:1000'],
            'values.security_autoban_window' => ['required', 'integer', 'min:1', 'max:1440'],
            'values.security_autoban_duration' => ['required', 'integer', 'min:1', 'max:525600'],
            'values.security_ban_allowlist' => ['nullable', 'string', 'max:500'],
            'values.security_retention_days' => ['required', 'integer', 'min:7', 'max:3650'],
            'values.security_geoip_enabled' => ['required', 'in:0,1'],
            'values.approval_enabled' => ['required', 'in:0,1'],
            'values.approval_maker_checker' => ['required', 'in:0,1'],
            'values.approval_flows' => ['nullable', 'string', 'max:10000'],
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
        $this->dispatch('toast', type: 'success', message: __('Pengaturan disimpan.'));
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
