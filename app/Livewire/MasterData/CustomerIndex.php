<?php

namespace App\Livewire\MasterData;

use App\Models\Customer;
use App\Services\Support\AuditLogger;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Customer / Department')]
class CustomerIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public string $statusFilter = '';

    public int $perPage = 10;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $type = 'customer';

    public string $contact_person = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $status = 'active';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('items.view'), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()->hasPermission('items.create'), 403);

        $this->resetValidation();
        $this->reset(['editingId', 'code', 'name', 'contact_person', 'phone', 'email', 'address']);
        $this->type = 'customer';
        $this->status = 'active';
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('items.update'), 403);

        $customer = Customer::findOrFail($id);
        $this->resetValidation();

        $this->editingId = $customer->id;
        $this->code = (string) $customer->code;
        $this->name = (string) $customer->name;
        $this->type = (string) $customer->type;
        $this->contact_person = (string) ($customer->contact_person ?? '');
        $this->phone = (string) ($customer->phone ?? '');
        $this->email = (string) ($customer->email ?? '');
        $this->address = (string) ($customer->address ?? '');
        $this->status = (string) $customer->status;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $permission = $this->editingId ? 'items.update' : 'items.create';
        abort_unless(auth()->user()->hasPermission($permission), 403);

        $data = $this->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('customers', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:customer,department'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        foreach (['contact_person', 'phone', 'email', 'address'] as $field) {
            $data[$field] = $data[$field] !== '' ? $data[$field] : null;
        }

        if ($this->editingId) {
            $customer = Customer::findOrFail($this->editingId);
            $old = $customer->toArray();
            $customer->update($data);
            AuditLogger::logModel('update', $customer, $old, $customer->fresh()->toArray());
        } else {
            $customer = Customer::create($data);
            AuditLogger::logModel('create', $customer, null, $customer->toArray());
        }

        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: 'Tersimpan');
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('items.delete'), 403);

        $customer = Customer::findOrFail($id);
        $old = $customer->toArray();
        $customer->delete();

        AuditLogger::logModel('delete', $customer, $old);

        $this->dispatch('toast', type: 'success', message: 'Customer dihapus.');
    }

    public function render()
    {
        return view('livewire.master-data.customer-index', [
            'customers' => Customer::query()
                ->when($this->search !== '', function ($query): void {
                    $term = '%'.$this->search.'%';
                    $query->where(fn ($inner) => $inner->where('code', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('contact_person', 'like', $term));
                })
                ->when($this->typeFilter !== '', fn ($query) => $query->where('type', $this->typeFilter))
                ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
                ->orderBy('name')
                ->paginate($this->perPage),
        ]);
    }
}
