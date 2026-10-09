<?php

namespace App\Livewire\Admin;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\Support\AuditLogger;
use App\Services\Support\CurrencyService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Mata Uang')]
class CurrencyIndex extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $symbol = '';

    public bool $is_active = true;

    public string $rate = '';

    public string $effective_date = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('settings.manage'), 403);

        $this->effective_date = now()->format('Y-m-d');
    }

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->reset(['editingId', 'code', 'name', 'symbol', 'rate']);
        $this->is_active = true;
        $this->effective_date = now()->format('Y-m-d');
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $currency = Currency::findOrFail($id);

        $this->resetValidation();
        $this->editingId = $currency->id;
        $this->code = (string) $currency->code;
        $this->name = (string) $currency->name;
        $this->symbol = (string) ($currency->symbol ?? '');
        $this->is_active = (bool) $currency->is_active;
        $this->rate = (string) CurrencyService::rate((string) $currency->code);
        $this->effective_date = now()->format('Y-m-d');
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate([
            'code' => ['required', 'string', 'max:10', 'uppercase', Rule::unique('currencies', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'is_active' => ['boolean'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'effective_date' => ['required', 'date'],
        ]);

        if ($this->editingId) {
            $currency = Currency::findOrFail($this->editingId);
            $old = $currency->toArray();
            $currency->update([
                'name' => $data['name'],
                'symbol' => $data['symbol'] !== '' ? $data['symbol'] : null,
                'is_active' => (bool) $data['is_active'],
            ]);
            AuditLogger::logModel('update', $currency, $old, $currency->fresh()->toArray());
        } else {
            $currency = Currency::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'symbol' => $data['symbol'] !== '' ? $data['symbol'] : null,
                'is_base' => false,
                'is_active' => (bool) $data['is_active'],
            ]);
            AuditLogger::logModel('create', $currency, null, $currency->toArray());
        }

        if (! $currency->is_base && $data['rate'] !== null && (float) $data['rate'] > 0) {
            ExchangeRate::updateOrCreate(
                ['currency_id' => $currency->id, 'effective_date' => $data['effective_date']],
                ['rate' => (float) $data['rate']],
            );
        }

        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: __('Mata uang disimpan.'));
    }

    public function render()
    {
        $currencies = Currency::with(['exchangeRates' => fn ($q) => $q->orderByDesc('effective_date')->limit(1)])
            ->orderByDesc('is_base')
            ->orderBy('code')
            ->get();

        return view('livewire.admin.currency-index', [
            'currencies' => $currencies,
            'baseCode' => CurrencyService::baseCode(),
        ]);
    }
}
