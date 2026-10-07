<?php

namespace App\Livewire\MasterData;

use App\Imports\ItemsImport;
use App\Jobs\ImportItemsJob;
use App\Models\Category;
use App\Models\Item;
use App\Services\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Barang')]
class ItemIndex extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';

    public string $categoryFilter = '';

    public string $statusFilter = '';

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public int $perPage = 10;

    public array $selectedIds = [];

    public bool $selectAll = false;

    public $importFile = null;

    public bool $showImportModal = false;

    /** @var array<int, string> */
    public array $importErrors = [];

    public int $importedCount = 0;

    public int $updatedCount = 0;

    public function mount(): void
    {
        $this->authorize('viewAny', Item::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
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

        $this->selectedIds = $this->baseQuery()->orderBy($this->sortField, $this->sortDirection)->paginate($this->perPage)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['sku', 'name', 'created_at'], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function deleteItem(int $id): void
    {
        $item = Item::findOrFail($id);

        $this->authorize('delete', $item);

        $old = $item->toArray();
        $item->delete();

        AuditLogger::logModel('delete', $item, $old);

        $this->dispatch('toast', type: 'success', message: 'Barang dihapus.');
    }

    public function bulkDelete(): void
    {
        if ($this->selectedIds === []) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data terpilih.');

            return;
        }

        $deleted = 0;
        $skipped = 0;

        foreach (Item::whereIn('id', $this->selectedIds)->get() as $item) {
            try {
                $this->authorize('delete', $item);
            } catch (\Illuminate\Auth\Access\AuthorizationException) {
                $skipped++;

                continue;
            }

            $old = $item->toArray();
            $item->delete();
            AuditLogger::logModel('delete', $item, $old);
            $deleted++;
        }

        $this->reset('selectedIds', 'selectAll');

        $this->dispatch('toast', type: $deleted > 0 ? 'success' : 'error', message: "Hapus {$deleted} barang, {$skipped} dilewati.");
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
        if ($this->selectedIds === []) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data terpilih.');

            return;
        }

        $count = 0;

        foreach (Item::whereIn('id', $this->selectedIds)->get() as $item) {
            try {
                $this->authorize('update', $item);
            } catch (\Illuminate\Auth\Access\AuthorizationException) {
                continue;
            }

            $old = $item->toArray();
            $item->update(['status' => $status]);
            AuditLogger::logModel('update', $item, $old, $item->fresh()->toArray());
            $count++;
        }

        $this->reset('selectedIds', 'selectAll');

        $this->dispatch('toast', type: 'success', message: "{$count} barang diperbarui menjadi {$status}.");
    }

    public function openImportModal(): void
    {
        $this->authorize('create', Item::class);

        $this->reset('importFile', 'importErrors', 'importedCount', 'updatedCount');
        $this->showImportModal = true;
    }

    public function import(): void
    {
        $this->authorize('create', Item::class);

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        $disk = 'local';
        $fileName = 'imports/'.uniqid('items_', true).'.'.($this->importFile->getClientOriginalExtension() ?: 'xlsx');
        $storedPath = $this->importFile->storeAs(path: $fileName, options: ['disk' => $disk]);

        if ($storedPath === false || $storedPath === null) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan file import.');

            return;
        }

        AuditLogger::log('IMPORT', 'items', null, null, ['queued' => true, 'file' => $storedPath]);

        ImportItemsJob::dispatch($storedPath, (int) auth()->id(), $disk);

        $this->reset('importFile', 'importErrors', 'importedCount', 'updatedCount');
        $this->showImportModal = false;

        $this->dispatch('toast', type: 'success', message: 'Import dijadwalkan — Anda akan menerima notifikasi saat selesai.');
    }

    public function downloadImportTemplate(): StreamedResponse
    {
        $this->authorize('viewAny', Item::class);

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ItemsImport::headings());
            fputcsv($handle, ['BRG-001', '8991234567890', 'Wireless Mouse', 'ELEC', 'PCS', 'Logitech', '30', '300', 'SUP001', 'active']);
            fclose($handle);
        }, 'items-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function export(): StreamedResponse
    {
        $this->authorize('viewAny', Item::class);

        $rows = $this->baseQuery()->orderBy($this->sortField, $this->sortDirection)->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU', 'Barcode', 'Nama', 'Kategori', 'Satuan', 'Min', 'Max', 'Status']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->sku,
                    $row->barcode,
                    $row->name,
                    $row->category?->name,
                    $row->unit?->name,
                    $row->minimum_stock,
                    $row->maximum_stock,
                    $row->status,
                ]);
            }

            fclose($handle);
        }, 'items-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    protected function baseQuery(): Builder
    {
        return Item::query()
            ->with(['category', 'unit', 'primarySupplier'])
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('sku', 'like', $term)
                        ->orWhere('barcode', 'like', $term)
                        ->orWhere('name', 'like', $term);
                });
            })
            ->when($this->categoryFilter !== '', fn (Builder $query) => $query->where('category_id', $this->categoryFilter))
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter));
    }

    public function render()
    {
        return view('livewire.master-data.item-index', [
            'items' => $this->baseQuery()->orderBy($this->sortField, $this->sortDirection)->paginate($this->perPage),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
