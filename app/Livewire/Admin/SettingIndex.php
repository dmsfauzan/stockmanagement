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
