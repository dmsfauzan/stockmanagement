<?php

namespace App\Livewire\MasterData;

use App\Models\Customer;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Customer / Department')]
class CustomerIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public string $statusFilter = '';

    public string $trashedFilter = '';

    public int $perPage = 10;

    public array $selectedIds = [];

    public bool $selectAll = false;

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

    public function updatedTrashedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedIds(): void
    {
        if ($this->selectedIds === []) {
            $this->selectAll = false;
        }
    }

    public function updatedSelectAll(): void
    {
        $this->toggleSelectAll();
    }

    public function toggleSelectAll(): void
    {
        if (! $this->selectAll) {
            $this->selectedIds = [];

            return;
        }

        $this->selectedIds = Customer::query()
            ->when($this->trashedFilter === 'trashed', fn ($q) => $q->onlyTrashed())
            ->when($this->trashedFilter === 'all', fn ($q) => $q->withTrashed())
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(fn ($inner) => $inner->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('contact_person', 'like', $term));
            })
            ->when($this->typeFilter !== '', fn ($query) => $query->where('type', $this->typeFilter))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderBy('name')
            ->paginate($this->perPage)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
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

        if (! $this->editingId && $this->trashedConflict('customers', $this->code)) {
            $this->dispatch('toast', type: 'error', message: 'Kode sudah dipakai data terhapus. Pulihkan dari filter Terhapus.');

            return;
        }

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

    public function bulkDelete(): void
    {
        abort_unless(auth()->user()->hasPermission('items.delete'), 403);

        if ($this->selectedIds === []) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data terpilih.');

            return;
        }

        $deleted = 0;

        foreach (Customer::whereIn('id', $this->selectedIds)->get() as $customer) {
            $old = $customer->toArray();
            $customer->delete();
            AuditLogger::logModel('delete', $customer, $old);
            $deleted++;
        }

        $this->reset('selectedIds', 'selectAll');

        $this->dispatch('toast', type: 'success', message: "Hapus {$deleted} customer.");
    }

    public function restore(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('items.update'), 403);

        $customer = Customer::withTrashed()->findOrFail($id);
        $customer->restore();

        AuditLogger::logModel('restore', $customer, null, $customer->fresh()->toArray());

        $this->dispatch('toast', type: 'success', message: 'Customer dipulihkan.');
    }

    public function bulkRestore(): void
    {
        abort_unless(auth()->user()->hasPermission('items.update'), 403);

        if ($this->selectedIds === []) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data terpilih.');

            return;
        }

        $count = 0;

        foreach (Customer::withTrashed()->whereIn('id', $this->selectedIds)->get() as $customer) {
            $customer->restore();
            AuditLogger::logModel('restore', $customer, null, $customer->fresh()->toArray());
            $count++;
        }

        $this->reset('selectedIds', 'selectAll');

        $this->dispatch('toast', type: 'success', message: "Pulihkan {$count} data.");
    }

    private function trashedConflict(string $table, string $code): bool
    {
        return DB::table($table)->where('code', trim($code))->whereNotNull('deleted_at')->exists();
    }

    public function bulkActivate(): void
    {
        $this->bulkSetStatus('active');
    }

    public function bulkDeactivate(): void
    {
        $this->bulkSetStatus('inactive');
    }

    protected function bulkSetStatus(string $status): void
    {
        abort_unless(auth()->user()->hasPermission('items.update'), 403);

        if ($this->selectedIds === []) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data terpilih.');

            return;
        }

        $count = 0;

        foreach (Customer::whereIn('id', $this->selectedIds)->get() as $customer) {
            $old = $customer->toArray();
            $customer->update(['status' => $status]);
            AuditLogger::logModel('update', $customer, $old, $customer->fresh()->toArray());
            $count++;
        }

        $this->reset('selectedIds', 'selectAll');

        $this->dispatch('toast', type: 'success', message: "{$count} customer diperbarui menjadi {$status}.");
    }

    public function export(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('items.view'), 403);

        $rows = Customer::query()
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(fn ($inner) => $inner->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('contact_person', 'like', $term));
            })
            ->when($this->typeFilter !== '', fn ($query) => $query->where('type', $this->typeFilter))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderBy('name')
            ->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Code', 'Name', 'Type', 'Contact Person', 'Phone', 'Email', 'Address', 'Status']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->code,
                    $row->name,
                    $row->type,
                    $row->contact_person,
                    $row->phone,
                    $row->email,
                    $row->address,
                    $row->status,
                ]);
            }

            fclose($handle);
        }, 'customers-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        return view('livewire.master-data.customer-index', [
            'customers' => Customer::query()
                ->when($this->trashedFilter === 'trashed', fn ($q) => $q->onlyTrashed())
                ->when($this->trashedFilter === 'all', fn ($q) => $q->withTrashed())
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
