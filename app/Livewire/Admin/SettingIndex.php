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
