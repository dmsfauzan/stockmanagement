<?php

namespace App\Livewire\MasterData;

use App\Imports\SupplierImport;
use App\Livewire\Concerns\ImportsMasterData;
use App\Models\Supplier;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Supplier')]
class SupplierIndex extends Component
{
    use ImportsMasterData, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $trashedFilter = '';

    public int $perPage = 10;

    public array $selectedIds = [];

    public bool $selectAll = false;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $contact_person = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $status = 'active';

    public int $lead_time_days = 7;

    public string $payment_terms = 'NET 30';

    public string $region = '';

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

        $this->selectedIds = Supplier::query()
            ->when($this->trashedFilter === 'trashed', fn ($q) => $q->onlyTrashed())
            ->when($this->trashedFilter === 'all', fn ($q) => $q->withTrashed())
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(fn ($inner) => $inner->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('contact_person', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderBy('name')
            ->paginate($this->perPage)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()->hasPermission('items.create'), 403);

        $this->resetValidation();
        $this->reset(['editingId', 'code', 'name', 'contact_person', 'phone', 'email', 'address', 'region']);
        $this->status = 'active';
        $this->lead_time_days = 7;
        $this->payment_terms = 'NET 30';
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('items.update'), 403);

        $supplier = Supplier::findOrFail($id);
        $this->resetValidation();

        $this->editingId = $supplier->id;
        $this->code = (string) $supplier->code;
        $this->name = (string) $supplier->name;
        $this->contact_person = (string) ($supplier->contact_person ?? '');
        $this->phone = (string) ($supplier->phone ?? '');
        $this->email = (string) ($supplier->email ?? '');
        $this->address = (string) ($supplier->address ?? '');
        $this->status = (string) $supplier->status;
        $this->lead_time_days = (int) ($supplier->lead_time_days ?? 7);
        $this->payment_terms = (string) ($supplier->payment_terms ?? 'NET 30');
        $this->region = (string) ($supplier->region ?? '');
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

        if (! $this->editingId && $this->trashedConflict('suppliers', $this->code)) {
            $this->dispatch('toast', type: 'error', message: 'Kode sudah dipakai data terhapus. Pulihkan dari filter Terhapus.');

            return;
        }

        $data = $this->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('suppliers', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
            'lead_time_days' => ['required', 'integer', 'min:1', 'max:365'],
            'payment_terms' => ['required', 'string', 'max:40'],
            'region' => ['nullable', 'string', 'max:80'],
        ]);

        foreach (['contact_person', 'phone', 'email', 'address', 'region'] as $field) {
            $data[$field] = $data[$field] !== '' ? $data[$field] : null;
        }

        if ($this->editingId) {
            $supplier = Supplier::findOrFail($this->editingId);
            $old = $supplier->toArray();
            $supplier->update($data);
            AuditLogger::logModel('update', $supplier, $old, $supplier->fresh()->toArray());
        } else {
            $supplier = Supplier::create($data);
            AuditLogger::logModel('create', $supplier, null, $supplier->toArray());
        }

        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: 'Tersimpan');
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('items.delete'), 403);

        $supplier = Supplier::findOrFail($id);

        if ($supplier->primaryItems()->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Supplier masih menjadi supplier utama barang.');

            return;
        }

        $old = $supplier->toArray();
        $supplier->delete();

        AuditLogger::logModel('delete', $supplier, $old);

        $this->dispatch('toast', type: 'success', message: 'Supplier dihapus.');
    }

    public function bulkDelete(): void
    {
        abort_unless(auth()->user()->hasPermission('items.delete'), 403);

        if ($this->selectedIds === []) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data terpilih.');

            return;
        }

        $deleted = 0;
        $skipped = 0;

        foreach (Supplier::whereIn('id', $this->selectedIds)->get() as $supplier) {
            if ($supplier->primaryItems()->exists()) {
                $skipped++;

                continue;
            }

            $old = $supplier->toArray();
            $supplier->delete();
            AuditLogger::logModel('delete', $supplier, $old);
            $deleted++;
        }

        $this->reset('selectedIds', 'selectAll');

        $this->dispatch('toast', type: $deleted > 0 ? 'success' : 'error', message: "Hapus {$deleted} supplier, {$skipped} dilewati.");
    }

    public function restore(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('items.update'), 403);

        $supplier = Supplier::withTrashed()->findOrFail($id);
        $supplier->restore();

        AuditLogger::logModel('restore', $supplier, null, $supplier->fresh()->toArray());

        $this->dispatch('toast', type: 'success', message: 'Supplier dipulihkan.');
    }

    public function bulkRestore(): void
    {
        abort_unless(auth()->user()->hasPermission('items.update'), 403);

        if ($this->selectedIds === []) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data terpilih.');

            return;
        }

        $count = 0;

        foreach (Supplier::withTrashed()->whereIn('id', $this->selectedIds)->get() as $supplier) {
            $supplier->restore();
            AuditLogger::logModel('restore', $supplier, null, $supplier->fresh()->toArray());
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

        foreach (Supplier::whereIn('id', $this->selectedIds)->get() as $supplier) {
            $old = $supplier->toArray();
            $supplier->update(['status' => $status]);
            AuditLogger::logModel('update', $supplier, $old, $supplier->fresh()->toArray());
            $count++;
        }

        $this->reset('selectedIds', 'selectAll');

        $this->dispatch('toast', type: 'success', message: "{$count} supplier diperbarui menjadi {$status}.");
    }

    public function export(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('items.view'), 403);

        $rows = Supplier::query()
            ->withCount('primaryItems')
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(fn ($inner) => $inner->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('contact_person', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderBy('name')
            ->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Code', 'Name', 'Contact Person', 'Phone', 'Email', 'Address', 'Status', 'Lead Time Days', 'Payment Terms', 'Region', 'Primary Items Count']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->code,
                    $row->name,
                    $row->contact_person,
                    $row->phone,
                    $row->email,
                    $row->address,
                    $row->status,
                    $row->lead_time_days,
                    $row->payment_terms,
                    $row->region,
                    $row->primary_items_count,
                ]);
            }

            fclose($handle);
        }, 'suppliers-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    protected function importClass(): string
    {
        return SupplierImport::class;
    }

    protected function importLabelText(): string
    {
        return 'Supplier';
    }

    protected function importModule(): string
    {
        return 'suppliers';
    }

    protected function importPermission(): string
    {
        return 'items.create';
    }

    protected function importSampleRow(): array
    {
        return ['SUP001', 'PT Contoh', 'Budi', '08123456789', 'budi@contoh.com', 'Jakarta', 'active', 7, 'NET 30', 'Jakarta'];
    }

    public function render()
    {
        return view('livewire.master-data.supplier-index', [
            'suppliers' => Supplier::query()
                ->withCount('primaryItems')
                ->when($this->trashedFilter === 'trashed', fn ($q) => $q->onlyTrashed())
                ->when($this->trashedFilter === 'all', fn ($q) => $q->withTrashed())
                ->when($this->search !== '', function ($query): void {
                    $term = '%'.$this->search.'%';
                    $query->where(fn ($inner) => $inner->where('code', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('contact_person', 'like', $term)
                        ->orWhere('phone', 'like', $term));
                })
                ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
                ->orderBy('name')
                ->paginate($this->perPage),
        ]);
    }
}
